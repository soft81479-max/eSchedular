<?php

namespace App\Services\Networking;

use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

class MeetingOpportunityService {
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
     |
     | Used by Admin / event-level workspaces.
     |
     |--------------------------------------------------------------------------
     */
     public function forEvent(Event $event,array $filters = []): Collection {
          return $this->opportunities(
               event: $event,
               participant: null,
               filters: $filters,
               applyMatchRules: true,
               applyRecommendations: true
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Participant Opportunities
     |--------------------------------------------------------------------------
     |
     | Returns opportunities involving the participant.
     |
     |--------------------------------------------------------------------------
     */
     public function forParticipant(Event $event,EventParticipant $participant,array $filters = []): Collection {
          $this->ensureParticipantBelongsToEvent(
               event: $event,
               participant: $participant
          );

          return $this->opportunities(
               event: $event,
               participant: $participant,
               filters: $filters,
               applyMatchRules: true,
               applyRecommendations: true
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Participant As Sender
     |--------------------------------------------------------------------------
     |
     | The representative is always normalized as sender.
     |
     | This is important because the networking engine can return the
     | participant on either side of the canonical match direction.
     |
     |--------------------------------------------------------------------------
     */
     public function forParticipantAsSender(Event $event,EventParticipant $participant,array $filters = []): Collection {
          $this->ensureParticipantBelongsToEvent(
               event: $event,
               participant: $participant
          );

          $opportunities =
               $this->forParticipant(
                    event: $event,
                    participant: $participant,
                    filters: $filters
               );

          return $this->normalizeAsSender(
               opportunities: $opportunities,
               participant: $participant
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Direct Participant Opportunities
     |--------------------------------------------------------------------------
     |
     | Used when an event DOES NOT have a matchmaking workspace.
     |
     | There is no NetworkingMatchRule requirement here.
     |
     | The participant can directly request another participant provided
     | the candidate is eligible and the slot is not already occupied.
     |
     |--------------------------------------------------------------------------
     */
     public function forParticipantAsSenderDirect(Event $event,EventParticipant $participant,array $filters = []): Collection {
          $this->ensureParticipantBelongsToEvent(
               event: $event,
               participant: $participant
          );

          $opportunities =
               $this->opportunities(
                    event: $event,
                    participant: $participant,
                    filters: $filters,
                    applyMatchRules: false,
                    applyRecommendations: false
               );

          return $this->normalizeAsSender(
               opportunities: $opportunities,
               participant: $participant
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Core Opportunity Engine
     |--------------------------------------------------------------------------
     */
     protected function opportunities(Event $event,?EventParticipant $participant = null,array $filters = [],bool $applyMatchRules = true,bool $applyRecommendations = true): Collection {
          /*
          |--------------------------------------------------------------------------
          | Candidate Query
          |--------------------------------------------------------------------------
          */
          $query = $this->candidateQueryService->query(event: $event,participant: $participant);

          /*
          |--------------------------------------------------------------------------
          | Networking Eligibility
          |--------------------------------------------------------------------------
          |
          | Basic candidate eligibility remains active for both:
          |
          | - matchmaking
          | - direct requests
          |
          |--------------------------------------------------------------------------
          */
          $query = $this->eligibilityService->apply(query: $query);

          /*
          |--------------------------------------------------------------------------
          | Match Rules
          |--------------------------------------------------------------------------
          |
          | Match rules are ONLY relevant to matchmaking.
          |
          | Direct meeting requests deliberately bypass this layer.
          |
          |--------------------------------------------------------------------------
          */
          if ($applyMatchRules) {
               $query = $this->matchRuleService->apply(query: $query);
          }

          /*
          |--------------------------------------------------------------------------
          | Existing Meeting / Request Conflict
          |--------------------------------------------------------------------------
          |
          | Occupied participant/slot combinations are removed.
          |
          | This service remains responsible only for determining whether
          | something is currently available.
          |
          |--------------------------------------------------------------------------
          */
          $query = $this->meetingConflictService->apply(query: $query);
          /*
          |--------------------------------------------------------------------------
          | Filters
          |--------------------------------------------------------------------------
          */
          $query = $this->applyFilters(query: $query,filters: $filters);

          /*
          |--------------------------------------------------------------------------
          | Ordering
          |--------------------------------------------------------------------------
          */
          $opportunities =
               $query
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
          | Recommendations
          |--------------------------------------------------------------------------
          */
          if ($applyRecommendations) {
               return $this->recommendationService
                    ->enrich(
                         $opportunities
                    );
          }

          /*
          |--------------------------------------------------------------------------
          | Direct Opportunities
          |--------------------------------------------------------------------------
          |
          | Direct requests do not receive matchmaking scores.
          |
          |--------------------------------------------------------------------------
          */
          return $opportunities
               ->map(function ($row) {
                    $row->compatibility_score = null;
                    $row->ranking_score = null;
                    $row->auto_recommendation = false;
                    $row->match_auto_recommend = false;
                    return $row;
               });
     }

     /*
     |--------------------------------------------------------------------------
     | Normalize Opportunities So Current Participant Is Sender
     |--------------------------------------------------------------------------
     */
     protected function normalizeAsSender(Collection $opportunities,EventParticipant $participant): Collection {
          return $opportunities
               ->map(function ($row) use ($participant) {
                    /*
                    |--------------------------------------------------------------------------
                    | Already Sender
                    |--------------------------------------------------------------------------
                    */
                    if ((int) $row->sender_participant_id === (int) $participant->id) {
                         return $this->normalizeOpportunity(
                         row: $row,
                         participant: $participant
                         );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Participant Is Receiver
                    |--------------------------------------------------------------------------
                    |
                    | Swap the canonical networking direction.
                    |
                    |--------------------------------------------------------------------------
                    */
                    if ((int) $row->receiver_participant_id === (int) $participant->id) {
                         $oldSenderId = $row->sender_participant_id;
                         $oldSenderUlid = $row->sender_participant_ulid;
                         $row->sender_participant_id = $participant->id;
                         $row->sender_participant_ulid = $participant->ulid;
                         $row->receiver_participant_id = $oldSenderId;
                         $row->receiver_participant_ulid = $oldSenderUlid;

                         /*
                         |--------------------------------------------------------------------------
                         | Swap Sender / Receiver Display Fields
                         |--------------------------------------------------------------------------
                         */
                         $fields = ['name','photo','designation','organization','availability',];
                         foreach ($fields as $field) {
                         $senderField = 'sender_' . $field;
                         $receiverField = 'receiver_' . $field;
                         $oldSenderValue = $row->{$senderField} ?? null;
                         $row->{$senderField} = $row->{$receiverField} ?? null;
                         $row->{$receiverField} = $oldSenderValue;
                         }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Normalize
                    |--------------------------------------------------------------------------
                    */
                    return $this->normalizeOpportunity(
                         row: $row,
                         participant: $participant
                    );
               })
               ->filter(fn ($row) => (int) $row->sender_participant_id === (int) $participant->id)
               ->values();
     }

     /*
     |--------------------------------------------------------------------------
     | Normalize Single Opportunity
     |--------------------------------------------------------------------------
     */
     protected function normalizeOpportunity(object $row,EventParticipant $participant): object {
          /*
          |--------------------------------------------------------------------------
          | Target Participant
          |--------------------------------------------------------------------------
          */
          $row->participant_id = $row->receiver_participant_id ?? null;
          $row->participant_ulid = $row->receiver_participant_ulid ?? null;

          /*
          |--------------------------------------------------------------------------
          | Target Display
          |--------------------------------------------------------------------------
          */
          $row->participant_name = $row->receiver_name ?? null;
          $row->participant_photo = $row->receiver_photo ?? null;
          $row->participant_designation = $row->receiver_designation ?? null;
          $row->participant_organization = $row->receiver_organization ?? null;
          $row->participant_availability = $row->receiver_availability ?? null;

          /*
          |--------------------------------------------------------------------------
          | Request Fields
          |--------------------------------------------------------------------------
          |
          | Opportunity service MUST NOT know about MeetingRequest records.
          |
          |--------------------------------------------------------------------------
          */
          $row->meeting_request_id = null;
          $row->meeting_request_ulid = null;
          $row->meeting_request_status = null;
          $row->meeting_request_mode = null;
          $row->meeting_request_message = null;

          /*
          |--------------------------------------------------------------------------
          | Opportunity State
          |--------------------------------------------------------------------------
          */
          $row->has_meeting_request = false;

          /*
          |--------------------------------------------------------------------------
          | Recommendation
          |--------------------------------------------------------------------------
          */
          $row->compatibility_score = isset($row->compatibility_score) ? (int) $row->compatibility_score : null;
          $row->ranking_score = isset($row->ranking_score) ? (int) $row->ranking_score : null;
          $row->auto_recommend = (bool) ($row->auto_recommendation ?? $row->match_auto_recommend ?? false);
          $row->recommendation = $row->auto_recommend ? 'recommended' : 'available';
          return $row;
     }

     /*
     |--------------------------------------------------------------------------
     | Apply Filters
     |--------------------------------------------------------------------------
     */
     protected function applyFilters( Builder $query,array $filters = []): Builder {
          /*
          |--------------------------------------------------------------------------
          | Availability Status
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
          | Time Slot
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
                         $filters['sender_participant_id']
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
                         $filters['receiver_participant_id']
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
                              $filters[
                                   'event_participant_type_id'
                              ]
                         )
                         ->orWhere(
                              'eo2.event_participant_type_id',
                              $filters[
                                   'event_participant_type_id'
                              ]
                         );
                    }
               );
          }

          return $query;
     }

     /*
     |--------------------------------------------------------------------------
     | Participant Event Validation
     |--------------------------------------------------------------------------
     */
     protected function ensureParticipantBelongsToEvent(Event $event,EventParticipant $participant): void {
          abort_unless(
               $participant->eventOrganization?->event_id === $event->id,
               404
          );
     }
}