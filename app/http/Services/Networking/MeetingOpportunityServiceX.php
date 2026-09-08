<?php

namespace App\Services\Networking;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\MeetingRequest;
use App\Models\ParticipantAvailability;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

class MeetingOpportunityServiceX {
     /*
     |--------------------------------------------------------------------------
     | Constructor
     |--------------------------------------------------------------------------
     */
     public function __construct(
          protected CandidateQueryService $candidateQueryService,
          protected NetworkingEligibilityService $eligibilityService,
          protected NetworkingMatchRuleService $matchRuleService,
          protected MeetingConflictService $meetingConflictService,
          protected MeetingRecommendationService $recommendationService
     ) {
     }


     /*
     |--------------------------------------------------------------------------
     | Event Opportunities
     |--------------------------------------------------------------------------
     */
     public function forEvent(Event $event,array $filters = []): Collection {
          return $this->opportunities(
               event: $event,
               participant: null,
               filters: $filters
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Participant Opportunities
     |--------------------------------------------------------------------------
     */
     public function forParticipant(Event $event,EventParticipant $participant,array $filters = []): Collection {
          abort_unless(
               $participant->eventOrganization?->event_id === $event->id,
               404
          );

          return $this->opportunities(
               event: $event,
               participant: $participant,
               filters: $filters
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Participant Opportunities As Sender
     |--------------------------------------------------------------------------
     |
     | Representative workspace.
     |
     | Current participant is ALWAYS presented as sender.
     |
     | The universal networking engine remains untouched.
     |
     | Existing meeting requests are merged back into the representative
     | workspace after the opportunity/conflict pipeline.
     |
     |--------------------------------------------------------------------------
     */
     public function forParticipantAsSender(Event $event,EventParticipant $participant,array $filters = []): Collection {
          abort_unless(
               $participant->eventOrganization?->event_id === $event->id,
               404
          );

          /*
          |--------------------------------------------------------------------------
          | Available Opportunities
          |--------------------------------------------------------------------------
          */
          $opportunities = $this->forParticipant(
               event: $event,
               participant: $participant,
               filters: $filters
          );

          /*
          |--------------------------------------------------------------------------
          | Normalize Available Opportunities
          |--------------------------------------------------------------------------
          */
          $opportunities = $opportunities
               ->map(function ($row) use ($participant) {
                    /*
                    |--------------------------------------------------------------------------
                    | Current Participant Already Sender
                    |--------------------------------------------------------------------------
                    */
                    if ((int) $row->sender_participant_id === (int) $participant->id) {
                         return $this->normalizeRepresentativeOpportunity(
                              row: $row,
                              participant: $participant
                         );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Current Participant Is Canonical Receiver
                    |--------------------------------------------------------------------------
                    */
                    if ((int) $row->receiver_participant_id === (int) $participant->id) {
                         /*
                         |--------------------------------------------------------------
                         | Save Original Sender
                         |--------------------------------------------------------------
                         */
                         $oldSenderId = $row->sender_participant_id;
                         $oldSenderUlid = $row->sender_participant_ulid;

                         /*
                         |--------------------------------------------------------------
                         | Swap Participant IDs
                         |--------------------------------------------------------------
                         */
                         $row->sender_participant_id = $participant->id;
                         $row->sender_participant_ulid = $participant->ulid;
                         $row->receiver_participant_id = $oldSenderId;
                         $row->receiver_participant_ulid = $oldSenderUlid;

                         /*
                         |--------------------------------------------------------------
                         | Swap Participant Information
                         |--------------------------------------------------------------
                         */
                         $fields = [
                              'name',
                              'photo',
                              'designation',
                              'organization',
                              'availability',
                         ];

                         foreach ($fields as $field) {
                              $senderField = 'sender_'.$field;
                              $receiverField = 'receiver_'.$field;
                              $oldSenderValue = $row->{$senderField} ?? null;
                              $row->{$senderField} = $row->{$receiverField} ?? null;
                              $row->{$receiverField} = $oldSenderValue;
                         }
                    }

                    return $this->normalizeRepresentativeOpportunity(
                         row: $row,
                         participant: $participant
                    );
               })
               ->values();

          /*
          |--------------------------------------------------------------------------
          | Existing Meeting Requests
          |--------------------------------------------------------------------------
          |
          | These are intentionally fetched separately because the conflict
          | engine removes occupied opportunities from the opportunity pool.
          |
          |--------------------------------------------------------------------------
          */
          $requests = $this->participantMeetingRequests(
               event: $event,
               participant: $participant,
               filters: $filters
          );

          /*
          |--------------------------------------------------------------------------
          | Convert Existing Requests Into Workspace Rows
          |--------------------------------------------------------------------------
          */
          $requestRows = $requests
               ->map(function (MeetingRequest $meetingRequest) use ($participant) {
                    return $this->normalizeRepresentativeRequest(
                         meetingRequest: $meetingRequest,
                         participant: $participant
                    );
               })
               ->values();

          /*
          |--------------------------------------------------------------------------
          | Merge
          |--------------------------------------------------------------------------
          */
          return $opportunities
               ->concat($requestRows)
               ->sortBy([
                    ['start_at', 'asc'],
                    ['participant_name', 'asc'],
               ])
               ->values();
     }

     /*
     |--------------------------------------------------------------------------
     | Existing Participant Meeting Requests
     |--------------------------------------------------------------------------
     |
     | Retrieves active/history requests involving the current participant.
     |
     | For the representative workspace we primarily need pending/accepted
     | requests because those are occupied and therefore absent from the
     | opportunity engine.
     |--------------------------------------------------------------------------
     */
     protected function participantMeetingRequests(Event $event,EventParticipant $participant,array $filters = []): Collection {
          $query = MeetingRequest::query()
               ->whereHas(
                    'timeSlot',
                    function ($query) use ($event) {
                         $query->whereHas(
                              'schedule',
                              function ($schedule) use ($event) {
                                   $schedule->where(
                                        'event_id',
                                        $event->id
                                   );
                              }
                         );
                    }
               )
               ->where(function ($query) use ($participant) {
                    $query
                         ->where(
                              'sender_participant_id',
                              $participant->id
                         )
                         ->orWhere(
                              'receiver_participant_id',
                              $participant->id
                         );
               });

          /*
          |--------------------------------------------------------------------------
          | Slot Filter
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['event_time_slot_id'])) {
               $query->where(
                    'event_time_slot_id',
                    $filters['event_time_slot_id']
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Status Filter
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['status'])) {
               /*
               |--------------------------------------------------------------
               | Workspace status filter
               |--------------------------------------------------------------
               */
               $query->where(
                    'status',
                    $filters['status']
               );
          }

          return $query
               ->with([
                    'senderParticipant.organizationUser.user',
                    'senderParticipant.eventOrganization.organization',

                    'receiverParticipant.organizationUser.user',
                    'receiverParticipant.eventOrganization.organization',

                    'timeSlot',
               ])
               ->orderBy(
                    'created_at',
                    'desc'
               )
               ->get();
     }

     /*
     |--------------------------------------------------------------------------
     | Normalize Existing Meeting Request
     |--------------------------------------------------------------------------
     |
     | Converts an existing request into exactly the same row contract used
     | by the representative DataTable.
     |--------------------------------------------------------------------------
     */
     protected function normalizeRepresentativeRequest(MeetingRequest $meetingRequest,EventParticipant $participant): object {
          /*
          |--------------------------------------------------------------------------
          | Determine Target
          |--------------------------------------------------------------------------
          */
          if ((int) $meetingRequest->sender_participant_id === (int) $participant->id) {
               $target = $meetingRequest->receiverParticipant;
          } else {
               $target = $meetingRequest->senderParticipant;
          }

          /*
          |--------------------------------------------------------------------------
          | Target User
          |--------------------------------------------------------------------------
          */
          $targetUser = $target?->organizationUser?->user;

          /*
          |--------------------------------------------------------------------------
          | Target Organization
          |--------------------------------------------------------------------------
          */
          $targetOrganization = $target?->eventOrganization?->organization;

          /*
          |--------------------------------------------------------------------------
          | Row
          |--------------------------------------------------------------------------
          */
          $row = new \stdClass();

          /*
          |--------------------------------------------------------------------------
          | Participant
          |--------------------------------------------------------------------------
          */
          $row->participant_id = $target?->id;
          $row->participant_ulid = $target?->ulid;
          $row->participant_name = $targetUser?->name;
          $row->participant_photo = $targetUser?->avatar;
          $row->participant_designation = $target?->organizationUser?->designation;
          $row->participant_organization = $targetOrganization?->name;

          /*
          |--------------------------------------------------------------------------
          | Availability
          |--------------------------------------------------------------------------
          |
          | Even though this row is now an existing meeting request, we still
          | display the participant's availability for this exact meeting slot.
          |--------------------------------------------------------------------------
          */
          $row->participant_availability =
               ParticipantAvailability::query()
                    ->where(
                         'event_participant_id',
                         $target?->id
                    )
                    ->where(
                         'event_time_slot_id',
                         $meetingRequest->event_time_slot_id
                    )
                    ->value('status');

          /*
          |--------------------------------------------------------------------------
          | Sender / Receiver
          |--------------------------------------------------------------------------
          */
          $row->sender_participant_id = $participant->id;
          $row->sender_participant_ulid = $participant->ulid;
          $row->receiver_participant_id = $target?->id;
          $row->receiver_participant_ulid = $target?->ulid;

          /*
          |--------------------------------------------------------------------------
          | Slot
          |--------------------------------------------------------------------------
          */
          $slot = $meetingRequest->timeSlot;
          $row->event_time_slot_id = $meetingRequest->event_time_slot_id;
          $row->start_at = $slot?->start_at;
          $row->end_at = $slot?->end_at;
          $row->type = $slot?->type;
          $row->mode = $slot?->mode;
          $row->capacity = $slot?->capacity;

          /*
          |--------------------------------------------------------------------------
          | Meeting Request
          |--------------------------------------------------------------------------
          */
          $row->meeting_request_id = $meetingRequest->id;
          $row->meeting_request_ulid = $meetingRequest->ulid;
          $row->meeting_request_status = $meetingRequest->status;
          $row->meeting_request_mode = $meetingRequest->meeting_mode;
          $row->meeting_request_message = $meetingRequest->message;

          /*
          |--------------------------------------------------------------------------
          | Recommendation
          |--------------------------------------------------------------------------
          |
          | These values are historical values when available.
          |--------------------------------------------------------------------------
          */
          $row->compatibility_score = (int) ($meetingRequest->recommendation_score ?? 0);
          $row->ranking_score = (int) ($meetingRequest->recommendation_score ?? 0);
          $row->auto_recommend = (bool) ($meetingRequest->was_recommended ?? false);

          /*
          |--------------------------------------------------------------------------
          | Recommendation State
          |--------------------------------------------------------------------------
          */
          $row->recommendation = $meetingRequest->status;

          /*
          |--------------------------------------------------------------------------
          | Existing Request Marker
          |--------------------------------------------------------------------------
          */
          $row->has_meeting_request = true;

          return $row;
     }

     /*
     |--------------------------------------------------------------------------
     | Normalize Representative Opportunity
     |--------------------------------------------------------------------------
     */
     protected function normalizeRepresentativeOpportunity(object $row,EventParticipant $participant): object {
          /*
          |--------------------------------------------------------------------------
          | Target
          |--------------------------------------------------------------------------
          */
          $row->participant_id = $row->receiver_participant_id;
          $row->participant_ulid = $row->receiver_participant_ulid;
          $row->participant_name = $row->receiver_name;
          $row->participant_photo = $row->receiver_photo;
          $row->participant_designation = $row->receiver_designation;
          $row->participant_organization = $row->receiver_organization;
          $row->participant_availability = $row->receiver_availability;

          /*
          |--------------------------------------------------------------------------
          | No Existing Request
          |--------------------------------------------------------------------------
          */
          $row->meeting_request_id = null;
          $row->meeting_request_ulid = null;
          $row->meeting_request_status = null;
          $row->meeting_request_mode = null;
          $row->meeting_request_message = null;
          $row->has_meeting_request = false;

          /*
          |--------------------------------------------------------------------------
          | Recommendation
          |--------------------------------------------------------------------------
          */
          $row->compatibility_score = (int) ($row->compatibility_score ?? 0);
          $row->ranking_score = (int) ($row->ranking_score ?? 0);
          $row->auto_recommend = 
               (bool) (
                    $row->auto_recommendation
                    ?? $row->match_auto_recommend
                    ?? false
               );

          /*
          |--------------------------------------------------------------------------
          | Recommendation State
          |--------------------------------------------------------------------------
          */
          $row->recommendation =
               $row->auto_recommend
                    ? 'recommended'
                    : 'compatible';

          return $row;
     }

     /*
     |--------------------------------------------------------------------------
     | Opportunity Pipeline
     |--------------------------------------------------------------------------
     */
     protected function opportunities(Event $event,?EventParticipant $participant = null,array $filters = []): Collection {
          /*
          |--------------------------------------------------------------------------
          | Candidate Pool
          |--------------------------------------------------------------------------
          */
          $query = $this->candidateQueryService->query(
               event: $event,
               participant: $participant
          );

          /*
          |--------------------------------------------------------------------------
          | Eligibility
          |--------------------------------------------------------------------------
          */
          $query = $this->eligibilityService->apply(
               query: $query
          );

          /*
          |--------------------------------------------------------------------------
          | Match Rules
          |--------------------------------------------------------------------------
          */
          $query = $this->matchRuleService->apply(
               query: $query
          );

          /*
          |--------------------------------------------------------------------------
          | Meeting Conflicts
          |--------------------------------------------------------------------------
          */
          $query = $this->meetingConflictService->apply(
               query: $query
          );

          /*
          |--------------------------------------------------------------------------
          | Filters
          |--------------------------------------------------------------------------
          */
          $query = $this->applyFilters(
               query: $query,
               filters: $filters
          );

          /*
          |--------------------------------------------------------------------------
          | Get Opportunities
          |--------------------------------------------------------------------------
          */
          $opportunities = $query
               ->orderBy(
                    'event_time_slots.start_at'
               )
               ->orderBy(
                    'user1.name'
               )
               ->orderBy(
                    'user2.name'
               )
               ->get();

          /*
          |--------------------------------------------------------------------------
          | Recommendation Enrichment
          |--------------------------------------------------------------------------
          */
          return $this->recommendationService
               ->enrich(
                    $opportunities
               );
     }

     /*
     |--------------------------------------------------------------------------
     | Apply Filters
     |--------------------------------------------------------------------------
     */
     protected function applyFilters(Builder $query,array $filters = []): Builder {
          /*
          |--------------------------------------------------------------------------
          | Availability
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['status'])) {
               $query
                    ->where(
                         'pa1.status',
                         $filters['status']
                    )
                    ->where(
                         'pa2.status',
                         $filters['status']
                    );
          }

          /*
          |--------------------------------------------------------------------------
          | Event Time Slot
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['event_time_slot_id'])) {
               $query->where(
                    'event_time_slots.id',
                    $filters['event_time_slot_id']
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Sender
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['sender_participant_id'])) {
               $query->whereRaw(
                    "
                    CASE
                         WHEN networking_forward_rule.is_match_allowed = 1
                              THEN ep1.id

                         WHEN networking_reverse_rule.is_match_allowed = 1
                              THEN ep2.id
                    END = ?
                    ",
                    [
                         $filters['sender_participant_id'],
                    ]
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Receiver
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['receiver_participant_id'])) {
               $query->whereRaw(
                    "
                    CASE
                         WHEN networking_forward_rule.is_match_allowed = 1
                              THEN ep2.id

                         WHEN networking_reverse_rule.is_match_allowed = 1
                              THEN ep1.id
                    END = ?
                    ",
                    [
                         $filters['receiver_participant_id'],
                    ]
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Organization
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['organization_id'])) {
               $query->where(
                    function (Builder $scope) use ($filters) {
                         $scope
                              ->where(
                                   'eo1.organization_id',
                                   $filters['organization_id']
                              )
                              ->orWhere(
                                   'eo2.organization_id',
                                   $filters['organization_id']
                              );
                    }
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Participant Type
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['event_participant_type_id'])) {
               $query->where(
                    function (Builder $scope) use ($filters) {
                         $scope
                              ->where(
                                   'eo1.event_participant_type_id',
                                   $filters['event_participant_type_id']
                              )
                              ->orWhere(
                                   'eo2.event_participant_type_id',
                                   $filters['event_participant_type_id']
                              );
                    }
               );
          }

          return $query;
     }
}