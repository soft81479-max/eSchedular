<?php

namespace App\Services\Networking;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventTimeSlot;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeetingRequestService {
     public function __construct(
          protected MeetingOpportunityService $meetingOpportunityService
     ) {
     }

     /*
     |--------------------------------------------------------------------------
     | Workspace Rows
     |--------------------------------------------------------------------------
     |
     | Builds the Requests tab.
     |
     | The result can contain:
     |
     | - opportunity
     | - pending outgoing request
     | - pending incoming request
     |
     | Accepted requests are NOT returned here.
     |
     |--------------------------------------------------------------------------
     */

     public function workspaceRows(Event $event,EventParticipant $participant,array $filters = [],bool $includeOpportunities = true): Collection {
          /*
          |--------------------------------------------------------------------------
          | Validate Participant
          |--------------------------------------------------------------------------
          */
          $this->ensureParticipantBelongsToEvent(
               event: $event,
               participant: $participant
          );

          /*
          |--------------------------------------------------------------------------
          | Pending Requests
          |--------------------------------------------------------------------------
          */
          $requests =
               $this->pendingForParticipant(
                    event: $event,
                    participant: $participant,
                    filters: $filters
               );

          /*
          |--------------------------------------------------------------------------
          | Normalize Requests
          |--------------------------------------------------------------------------
          */
          $requestRows =
               $requests
                    ->map(
                         fn (MeetingRequest $meetingRequest) =>
                         $this->normalizeRequestRow(
                              meetingRequest: $meetingRequest,
                              participant: $participant
                         )
                    )
                    ->values();

          /*
          |--------------------------------------------------------------------------
          | Opportunities
          |--------------------------------------------------------------------------
          |
          | For matchmaking events the dedicated Matchmaking workspace
          | owns recommendations.
          |
          | For non-matchmaking events we still need direct opportunities
          | from which the participant can initiate a request.
          |
          */
          $opportunityRows = collect();

          if ($includeOpportunities) {
               $opportunities =
                    $this->meetingOpportunityService
                         ->forParticipantAsSender(
                         event: $event,
                         participant: $participant,
                         filters: [
                              'event_time_slot_id' =>
                                   $filters['event_time_slot_id'] ?? null,
                         ]
                         );

               $opportunityRows =
                    $opportunities
                         ->map(
                         fn ($row) =>
                              $this->normalizeOpportunityRow(
                                   row: $row,
                                   participant: $participant
                              )
                         )
                         ->values();
          }

          /*
          |--------------------------------------------------------------------------
          | Remove Duplicate Opportunity
          |--------------------------------------------------------------------------
          |
          | A participant/slot combination must never appear as an
          | available opportunity when a pending request already exists.
          |
          */
          $pendingKeys =
               $requestRows
                    ->map(
                         fn ($row) =>
                         $this->pairSlotKey(
                              senderParticipantId:
                                   $row->sender_participant_id,
                              receiverParticipantId:
                                   $row->receiver_participant_id,
                              eventTimeSlotId:
                                   $row->event_time_slot_id
                         )
                    )
                    ->flip();

          $opportunityRows =
               $opportunityRows
                    ->reject(
                         function ($row) use ($pendingKeys) {
                         $key =
                              $this->pairSlotKey(
                                   senderParticipantId: $row->sender_participant_id,
                                   receiverParticipantId: $row->receiver_participant_id,
                                   eventTimeSlotId: $row->event_time_slot_id
                              );
                         return $pendingKeys->has($key);
                         }
                    )
                    ->values();

          /*
          |--------------------------------------------------------------------------
          | Combine
          |--------------------------------------------------------------------------
          */
          return $opportunityRows
               ->concat($requestRows)
               ->sortBy([
                    ['start_at', 'asc'],
                    ['participant_name', 'asc'],
               ])
               ->values();
     }

     /*
     |--------------------------------------------------------------------------
     | Pending Requests
     |--------------------------------------------------------------------------
     */
     public function pendingForParticipant(Event $event,EventParticipant $participant,array $filters = []): Collection {
          $this->ensureParticipantBelongsToEvent(
               event: $event,
               participant: $participant
          );

          $query =
               MeetingRequest::query()
                    ->where('status', MeetingRequest::STATUS_PENDING)
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
                    })
                    ->whereHas(
                         'timeSlot.schedule',
                         function ($query) use ($event) {
                         $query->where(
                              'event_id',
                              $event->id
                         );
                         }
                    );

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
          | Relations
          |--------------------------------------------------------------------------
          */
          return $query
               ->with([
                    'senderParticipant.organizationUser.user',
                    'senderParticipant.eventOrganization.organization',

                    'receiverParticipant.organizationUser.user',
                    'receiverParticipant.eventOrganization.organization',

                    'timeSlot',
                    'meeting',
               ])
               ->orderByDesc('created_at')
               ->get();
     }

     /*
     |--------------------------------------------------------------------------
     | Meetings
     |--------------------------------------------------------------------------
     |
     | Only MeetingRequests which have actually produced a Meeting.
     |
     | Therefore:
     |
     | pending     -> Requests tab
     | accepted    -> Meetings tab once Meeting exists
     |
     |--------------------------------------------------------------------------
     */
     public function meetingsForParticipant(Event $event,EventParticipant $participant,array $filters = []): Collection {
          $this->ensureParticipantBelongsToEvent(
               event: $event,
               participant: $participant
          );

          $query =
               MeetingRequest::query()
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
                    })
                    ->whereHas(
                         'meeting'
                    )
                    ->whereHas(
                         'timeSlot.schedule',
                         function ($query) use ($event) {
                         $query->where(
                              'event_id',
                              $event->id
                         );
                         }
                    );

          /*
          |--------------------------------------------------------------------------
          | Request Status Filter
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['status'])) {
               $query->where(
                    'status',
                    $filters['status']
               );
          }

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
          | Relations
          |--------------------------------------------------------------------------
          */
          return $query
               ->with([
                    'senderParticipant.organizationUser.user',
                    'senderParticipant.eventOrganization.organization',

                    'receiverParticipant.organizationUser.user',
                    'receiverParticipant.eventOrganization.organization',

                    'timeSlot.schedule',

                    'meeting',
               ])
               ->orderBy(
                    'event_time_slot_id'
               )
               ->orderByDesc('created_at')
               ->get();
     }

     /*
     |--------------------------------------------------------------------------
     | Available Slots
     |--------------------------------------------------------------------------
     |
     | Used when the representative clicks "Request".
     |
     |--------------------------------------------------------------------------
     */
     public function availableSlotsForRequest(Event $event,EventParticipant $sender,EventParticipant $receiver): Collection {
          $this->ensureParticipantBelongsToEvent(
               event: $event,
               participant: $sender
          );

          $this->ensureParticipantBelongsToEvent(
               event: $event,
               participant: $receiver
          );

          abort_if(
               (int) $sender->id === (int) $receiver->id,
               422
          );

          /*
          |--------------------------------------------------------------------------
          | Candidate Opportunities
          |--------------------------------------------------------------------------
          |
          | Use the existing opportunity engine to determine the slots
          | currently available to this pair.
          |
          */
          $opportunities =
               $this->meetingOpportunityService
                    ->forParticipantAsSender(
                         event: $event,
                         participant: $sender
                    );

          /*
          |--------------------------------------------------------------------------
          | Target Pair
          |--------------------------------------------------------------------------
          */
          return $opportunities
               ->filter(
                    function ($row) use ($receiver) {
                         return
                         (int) $row->receiver_participant_id ===
                         (int) $receiver->id;
                    }
               )
               ->map(
                    function ($row) {
                         return EventTimeSlot::query()
                         ->with('schedule')
                         ->find(
                              $row->event_time_slot_id
                         );
                    }
               )
               ->filter()
               ->unique('id')
               ->values();
     }

     /*
     |--------------------------------------------------------------------------
     | Create
     |--------------------------------------------------------------------------
     |
     | The sender is supplied by MeetingController from the authenticated
     | workspace participant.
     |
     | This service performs the final server-side validation.
     |
     |--------------------------------------------------------------------------
     */
     public function create(
          Event $event,
          int $senderParticipantId,
          int $receiverParticipantId,
          int $eventTimeSlotId,
          ?string $meetingMode = null,
          ?string $message = null,
          ?string $source = null
     ): MeetingRequest {
          return DB::transaction(
               function () use (
                    $event,
                    $senderParticipantId,
                    $receiverParticipantId,
                    $eventTimeSlotId,
                    $meetingMode,
                    $message,
                    $source
               ) {
                    /*
                    |--------------------------------------------------------------------------
                    | Sender
                    |--------------------------------------------------------------------------
                    */
                    $sender =
                         EventParticipant::query()
                         ->with([
                              'eventOrganization',
                         ])
                         ->findOrFail(
                              $senderParticipantId
                         );

                    /*
                    |--------------------------------------------------------------------------
                    | Receiver
                    |--------------------------------------------------------------------------
                    */
                    $receiver =
                         EventParticipant::query()
                         ->with([
                              'eventOrganization',
                         ])
                         ->findOrFail(
                              $receiverParticipantId
                         );

                    /*
                    |--------------------------------------------------------------------------
                    | Event Scope
                    |--------------------------------------------------------------------------
                    */
                    $this->ensureParticipantBelongsToEvent(
                         event: $event,
                         participant: $sender
                    );

                    $this->ensureParticipantBelongsToEvent(
                         event: $event,
                         participant: $receiver
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Cannot Request Yourself
                    |--------------------------------------------------------------------------
                    */
                    if ((int) $sender->id === (int) $receiver->id) {
                         throw ValidationException::withMessages([
                         'receiver_participant_id' =>
                              'You cannot request a meeting with yourself.',
                         ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Time Slot
                    |--------------------------------------------------------------------------
                    */
                    $timeSlot =
                         EventTimeSlot::query()
                         ->with('schedule')
                         ->findOrFail(
                              $eventTimeSlotId
                         );

                    /*
                    |--------------------------------------------------------------------------
                    | Slot Event Scope
                    |--------------------------------------------------------------------------
                    */
                    if (! $timeSlot->schedule || (int) $timeSlot->schedule->event_id !== (int) $event->id) {
                         throw ValidationException::withMessages([
                         'event_time_slot_id' =>
                              'The selected meeting slot does not belong to this event.',
                         ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Bookable
                    |--------------------------------------------------------------------------
                    */
                    if (! (bool) $timeSlot->is_bookable) {
                         throw ValidationException::withMessages([
                         'event_time_slot_id' =>
                              'The selected meeting slot is not bookable.',
                         ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Existing Pending Request
                    |--------------------------------------------------------------------------
                    */
                    $existingPending =
                         MeetingRequest::query()
                         ->where(
                              'event_time_slot_id',
                              $eventTimeSlotId
                         )
                         ->where(
                              'status',
                              MeetingRequest::STATUS_PENDING
                         )
                         ->where(
                              function ($query) use (
                                   $senderParticipantId,
                                   $receiverParticipantId
                              ) {
                                   $query
                                        ->where(function ($query) use (
                                             $senderParticipantId,
                                             $receiverParticipantId
                                        ) {
                                             $query
                                             ->where(
                                                  'sender_participant_id',
                                                  $senderParticipantId
                                             )
                                             ->where(
                                                  'receiver_participant_id',
                                                  $receiverParticipantId
                                             );
                                        })
                                        ->orWhere(function ($query) use (
                                             $senderParticipantId,
                                             $receiverParticipantId
                                        ) {
                                             $query
                                             ->where(
                                                  'sender_participant_id',
                                                  $receiverParticipantId
                                             )
                                             ->where(
                                                  'receiver_participant_id',
                                                  $senderParticipantId
                                             );
                                        });
                              }
                         )
                         ->lockForUpdate()
                         ->first();

                    if ($existingPending) {
                         throw ValidationException::withMessages([
                         'receiver_participant_id' =>
                              'A pending meeting request already exists for this participant and time slot.',
                         ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Existing Meeting
                    |--------------------------------------------------------------------------
                    */
                    $existingMeeting =
                         MeetingRequest::query()
                         ->where(
                              'event_time_slot_id',
                              $eventTimeSlotId
                         )
                         ->whereHas('meeting')
                         ->where(function ($query) use (
                              $senderParticipantId,
                              $receiverParticipantId
                         ) {
                              $query
                                   ->where(function ($query) use (
                                        $senderParticipantId,
                                        $receiverParticipantId
                                   ) {
                                        $query
                                             ->where(
                                             'sender_participant_id',
                                             $senderParticipantId
                                             )
                                             ->where(
                                             'receiver_participant_id',
                                             $receiverParticipantId
                                             );
                                   })
                                   ->orWhere(function ($query) use (
                                        $senderParticipantId,
                                        $receiverParticipantId
                                   ) {
                                        $query
                                             ->where(
                                             'sender_participant_id',
                                             $receiverParticipantId
                                             )
                                             ->where(
                                             'receiver_participant_id',
                                             $senderParticipantId
                                             );
                                   });
                         })
                         ->lockForUpdate()
                         ->exists();

                    if ($existingMeeting) {
                         throw ValidationException::withMessages([
                         'event_time_slot_id' =>
                              'A meeting already exists for this participant pair and time slot.',
                         ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Participant Availability
                    |--------------------------------------------------------------------------
                    */
                    $this->ensureParticipantAvailability(
                         participant: $sender,
                         timeSlot: $timeSlot,
                         field: 'sender_participant_id'
                    );

                    $this->ensureParticipantAvailability(
                         participant: $receiver,
                         timeSlot: $timeSlot,
                         field: 'receiver_participant_id'
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Meeting Mode
                    |--------------------------------------------------------------------------
                    */
                    $meetingMode = $meetingMode ?: $timeSlot->mode ?: 'networking';

                    /*
                    |--------------------------------------------------------------------------
                    | Create Request
                    |--------------------------------------------------------------------------
                    */
                    $meetingRequest =
                         MeetingRequest::create([
                         'event_time_slot_id' => $eventTimeSlotId,
                         'sender_participant_id' => $senderParticipantId,
                         'receiver_participant_id' => $receiverParticipantId,
                         'meeting_mode' => $meetingMode,
                         'message' => $message,
                         'source' => $source ?: MeetingRequest::SOURCE_DIRECT,
                         'status' => MeetingRequest::STATUS_PENDING,
                         ]);

                    return $meetingRequest;
               }
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Normalize Request Row
     |--------------------------------------------------------------------------
     */
     protected function normalizeRequestRow(MeetingRequest $meetingRequest,EventParticipant $participant): object {
          $isSender = (int) $meetingRequest->sender_participant_id === (int) $participant->id;
          $target = $isSender ? $meetingRequest->receiverParticipant : $meetingRequest->senderParticipant;
          $row = new \stdClass();

          /*
          |--------------------------------------------------------------------------
          | Row Type
          |--------------------------------------------------------------------------
          */
          $row->row_type = 'request';

          /*
          |--------------------------------------------------------------------------
          | Direction
          |--------------------------------------------------------------------------
          */
          $row->direction = $isSender ? 'outgoing' : 'incoming';

          /*
          |--------------------------------------------------------------------------
          | Participants
          |--------------------------------------------------------------------------
          */
          $row->sender_participant_id = $meetingRequest->sender_participant_id;
          $row->sender_participant_ulid = $meetingRequest->senderParticipant?->ulid;
          $row->receiver_participant_id = $meetingRequest->receiver_participant_id;
          $row->receiver_participant_ulid = $meetingRequest->receiverParticipant?->ulid;
          $row->targetParticipant = $target;

          /*
          |--------------------------------------------------------------------------
          | Participant Display
          |--------------------------------------------------------------------------
          */
          $row->participant_id = $target?->id;
          $row->participant_ulid = $target?->ulid;
          $row->participant_name = $target?->organizationUser?->user?->name;
          $row->participant_photo = $target?->organizationUser?->user?->avatar;
          $row->participant_designation = $target?->organizationUser?->designation;
          $row->participant_organization = $target?->eventOrganization?->organization?->name;

          /*
          |--------------------------------------------------------------------------
          | Slot
          |--------------------------------------------------------------------------
          */
          $row->event_time_slot_id = $meetingRequest->event_time_slot_id;
          $row->start_at = $meetingRequest->timeSlot?->start_at;
          $row->end_at = $meetingRequest->timeSlot?->end_at;
          $row->type = $meetingRequest->timeSlot?->type;
          $row->mode = $meetingRequest->timeSlot?->mode;
          $row->capacity = $meetingRequest->timeSlot?->capacity;

          /*
          |--------------------------------------------------------------------------
          | Request
          |--------------------------------------------------------------------------
          */
          $row->meeting_request_id = $meetingRequest->id;
          $row->meeting_request_ulid = $meetingRequest->ulid;
          $row->meeting_request_status = $meetingRequest->status;
          $row->meeting_request_mode = $meetingRequest->meeting_mode;
          $row->meeting_request_message = $meetingRequest->message;

          /*
          |--------------------------------------------------------------------------
          | Compatibility / Ranking
          |--------------------------------------------------------------------------
          |
          | Requests are historical request records.
          | Do not recalculate their recommendation score.
          |
          */
          $row->compatibility_score = 
               $meetingRequest->recommendation_score !== null
                    ? (int) $meetingRequest->recommendation_score
                    : null;

          $row->ranking_score =
               $meetingRequest->recommendation_score !== null
                    ? (int) $meetingRequest->recommendation_score
                    : null;

          $row->auto_recommend =
               (bool) (
                    $meetingRequest->was_recommended
                    ?? false
               );

          $row->recommendation = $meetingRequest->status;

          /*
          |--------------------------------------------------------------------------
          | Meeting
          |--------------------------------------------------------------------------
          */
          $row->meeting = $meetingRequest->meeting;
          $row->has_meeting = $meetingRequest->meeting !== null;
          return $row;
     }

     /*
     |--------------------------------------------------------------------------
     | Normalize Opportunity Row
     |--------------------------------------------------------------------------
     */
     protected function normalizeOpportunityRow(object $row,EventParticipant $participant): object {
          /*
          |--------------------------------------------------------------------------
          | Effective Sender
          |--------------------------------------------------------------------------
          */
          $row->row_type = 'opportunity';
          $row->direction = 'available';

          /*
          |--------------------------------------------------------------------------
          | Current Participant Is Sender
          |--------------------------------------------------------------------------
          */
          $row->sender_participant_id = $participant->id;
          $row->sender_participant_ulid = $participant->ulid;

          /*
          |--------------------------------------------------------------------------
          | Receiver
          |--------------------------------------------------------------------------
          */
          $row->receiver_participant_id = $row->receiver_participant_id ?? $row->participant_id ?? null;
          $row->receiver_participant_ulid = $row->receiver_participant_ulid ?? $row->participant_ulid ?? null;

          /*
          |--------------------------------------------------------------------------
          | Target Participant
          |--------------------------------------------------------------------------
          */
          $target = $row->receiverParticipant ?? null;
          if (! $target && $row->receiver_participant_id) {
               $target =
                    EventParticipant::query()
                         ->with([
                         'organizationUser.user',
                         'eventOrganization.organization',
                         ])
                         ->find(
                         $row->receiver_participant_id
                         );
          }
          $row->targetParticipant = $target;

          /*
          |--------------------------------------------------------------------------
          | Participant Display
          |--------------------------------------------------------------------------
          */
          $row->participant_id = $target?->id ?? $row->receiver_participant_id;
          $row->participant_ulid = $target?->ulid ?? $row->receiver_participant_ulid;
          $row->participant_name = $target?->organizationUser?->user?->name ?? $row->participant_name ?? null;
          $row->participant_photo = $target?->organizationUser?->user?->avatar ?? $row->participant_photo ?? null;
          $row->participant_designation = $target?->organizationUser?->designation ?? $row->participant_designation ?? null;
          $row->participant_organization = $target?->eventOrganization?->organization?->name ?? $row->participant_organization ?? null;

          /*
          |--------------------------------------------------------------------------
          | Request State
          |--------------------------------------------------------------------------
          */
          $row->meeting_request_id = null;
          $row->meeting_request_ulid = null;
          $row->meeting_request_status = null;
          $row->meeting_request_mode = null;
          $row->meeting_request_message = null;
          $row->meeting = null;
          $row->has_meeting = false;

          /*
          |--------------------------------------------------------------------------
          | Scores
          |--------------------------------------------------------------------------
          */
          $row->compatibility_score = isset($row->compatibility_score) ? (int) $row->compatibility_score: 0;
          $row->ranking_score = isset($row->ranking_score) ? (int) $row->ranking_score : 0;
          $row->auto_recommend = (bool) ($row->auto_recommendation ?? $row->match_auto_recommend ?? false);
          $row->recommendation = $row->auto_recommend ? 'recommended' : 'available';
          return $row;
     }

     /*
     |--------------------------------------------------------------------------
     | Participant Availability Validation
     |--------------------------------------------------------------------------
     */
     protected function ensureParticipantAvailability(EventParticipant $participant,EventTimeSlot $timeSlot,string $field): void {
          $availability =
               $participant
                    ->availabilities()
                    ->where(
                         'event_time_slot_id',
                         $timeSlot->id
                    )
                    ->value('status');

          /*
          |--------------------------------------------------------------------------
          | No Explicit Availability
          |--------------------------------------------------------------------------
          |
          | Do not reject here if the event uses implicit availability.
          |
          */
          if ($availability === null) {
               return;
          }

          /*
          |--------------------------------------------------------------------------
          | Unavailable
          |--------------------------------------------------------------------------
          */
          if (
               in_array(
                    strtolower((string) $availability),
                    [
                         'unavailable',
                         'blocked',
                         'busy',
                    ],
                    true
               )
          ) {
               throw ValidationException::withMessages([
                    $field => 'The participant is not available for the selected time slot.',
               ]);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Participant Event Scope
     |--------------------------------------------------------------------------
     */
     protected function ensureParticipantBelongsToEvent(Event $event,EventParticipant $participant): void {
          abort_unless(
               $participant->eventOrganization?->event_id === $event->id,
               404
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Pair / Slot Key
     |--------------------------------------------------------------------------
     */
     protected function pairSlotKey(int $senderParticipantId,int $receiverParticipantId,int $eventTimeSlotId): string {
          $participants = [
               $senderParticipantId,
               $receiverParticipantId,
          ];

          sort($participants);

          return implode(
               ':',
               [
                    $participants[0],
                    $participants[1],
                    $eventTimeSlotId,
               ]
          );
     }
}