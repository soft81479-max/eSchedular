<?php

namespace App\Services\Networking;

use App\Models\Event;
use App\Models\MeetingRequest;
use Illuminate\Database\Eloquent\Builder;

class MeetingQueryService
{
     /*
     |--------------------------------------------------------------------------
     | Query
     |--------------------------------------------------------------------------
     |
     | Returns the persisted meeting lifecycle for an event.
     |
     | The query begins with MeetingRequest because every meeting originates
     | from a request. A request may or may not yet have an actual Meeting.
     |--------------------------------------------------------------------------
     */
     public function query(
          Event $event,
          array $filters = []
     ): Builder {

          $query = MeetingRequest::query()

               /*
               |--------------------------------------------------------------------------
               | Relationships
               |--------------------------------------------------------------------------
               */
               ->with([

                    /*
                    |--------------------------------------------------------------------------
                    | Meeting
                    |--------------------------------------------------------------------------
                    */
                    'meeting',

                    /*
                    |--------------------------------------------------------------------------
                    | Time Slot / Schedule
                    |--------------------------------------------------------------------------
                    */
                    'timeSlot.schedule',

                    /*
                    |--------------------------------------------------------------------------
                    | Sender
                    |--------------------------------------------------------------------------
                    */
                    'senderParticipant.organizationUser.user',
                    'senderParticipant.eventOrganization.organization',
                    'senderParticipant.eventOrganization.participantType',

                    /*
                    |--------------------------------------------------------------------------
                    | Receiver
                    |--------------------------------------------------------------------------
                    */
                    'receiverParticipant.organizationUser.user',
                    'receiverParticipant.eventOrganization.organization',
                    'receiverParticipant.eventOrganization.participantType',
               ])

               /*
               |--------------------------------------------------------------------------
               | Event Scope
               |--------------------------------------------------------------------------
               |
               | A meeting request belongs to the event through:
               |
               | MeetingRequest
               |     → EventTimeSlot
               |     → EventSchedule
               |     → Event
               |--------------------------------------------------------------------------
               */
               ->whereHas(
                    'timeSlot.schedule',
                    function (Builder $query) use ($event) {

                         $query->where(
                              'event_id',
                              $event->id
                         );
                    }
               );

          /*
          |--------------------------------------------------------------------------
          | Filters
          |--------------------------------------------------------------------------
          */
          $this->applyFilters(
               query: $query,
               filters: $filters
          );

          /*
          |--------------------------------------------------------------------------
          | Ordering
          |--------------------------------------------------------------------------
          */
          return $query

               ->orderByDesc(
                    'created_at'
               )

               ->orderByDesc(
                    'id'
               );
     }

     /*
     |--------------------------------------------------------------------------
     | Apply Filters
     |--------------------------------------------------------------------------
     */
     protected function applyFilters(
          Builder $query,
          array $filters = []
     ): void {

          /*
          |--------------------------------------------------------------------------
          | Effective Status
          |--------------------------------------------------------------------------
          |
          | Before a Meeting exists:
          |
          |     MeetingRequest.status
          |
          | After a Meeting exists:
          |
          |     Meeting.status
          |
          | becomes the effective operational lifecycle status.
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['status'])) {

               $status = $filters['status'];

               $query->where(
                    function (Builder $query) use ($status) {

                         /*
                         |--------------------------------------------------------------------------
                         | Request Without Meeting
                         |--------------------------------------------------------------------------
                         */
                         $query
                              ->where(
                                   function (Builder $query) use ($status) {

                                        $query
                                             ->whereDoesntHave(
                                                  'meeting'
                                             )

                                             ->where(
                                                  'status',
                                                  $status
                                             );
                                   }
                              )

                              /*
                              |--------------------------------------------------------------------------
                              | Actual Meeting
                              |--------------------------------------------------------------------------
                              */
                              ->orWhereHas(
                                   'meeting',
                                   function (Builder $query) use ($status) {

                                        $query->where(
                                             'status',
                                             $status
                                        );
                                   }
                              );
                    }
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Schedule
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['schedule'])) {

               $query->whereHas(
                    'timeSlot',
                    function (Builder $query) use ($filters) {

                         $query->where(
                              'event_schedule_id',
                              $filters['schedule']
                         );
                    }
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Event Time Slot
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
          | Participant
          |--------------------------------------------------------------------------
          |
          | Match either side of the persisted meeting request.
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['participant_id'])) {

               $participantId =
                    (int) $filters['participant_id'];

               $query->where(
                    function (Builder $query) use ($participantId) {

                         $query
                              ->where(
                                   'sender_participant_id',
                                   $participantId
                              )

                              ->orWhere(
                                   'receiver_participant_id',
                                   $participantId
                              );
                    }
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Organization
          |--------------------------------------------------------------------------
          |
          | Match either sender or receiver organization.
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['organization_id'])) {

               $organizationId =
                    (int) $filters['organization_id'];

               $query->where(
                    function (Builder $query) use ($organizationId) {

                         $query

                              /*
                              |--------------------------------------------------------------------------
                              | Sender Organization
                              |--------------------------------------------------------------------------
                              */
                              ->whereHas(
                                   'senderParticipant.eventOrganization',
                                   function (Builder $query) use ($organizationId) {

                                        $query->where(
                                             'organization_id',
                                             $organizationId
                                        );
                                   }
                              )

                              /*
                              |--------------------------------------------------------------------------
                              | Receiver Organization
                              |--------------------------------------------------------------------------
                              */
                              ->orWhereHas(
                                   'receiverParticipant.eventOrganization',
                                   function (Builder $query) use ($organizationId) {

                                        $query->where(
                                             'organization_id',
                                             $organizationId
                                        );
                                   }
                              );
                    }
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Meeting Mode
          |--------------------------------------------------------------------------
          |
          | Once an actual Meeting exists, its mode is authoritative.
          |
          | Before that, the requested meeting mode remains on MeetingRequest.
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['meeting_mode'])) {

               $meetingMode =
                    $filters['meeting_mode'];

               $query->where(
                    function (Builder $query) use ($meetingMode) {

                         /*
                         |--------------------------------------------------------------------------
                         | Request Without Meeting
                         |--------------------------------------------------------------------------
                         */
                         $query
                              ->where(
                                   function (Builder $query) use ($meetingMode) {

                                        $query
                                             ->whereDoesntHave(
                                                  'meeting'
                                             )

                                             ->where(
                                                  'meeting_mode',
                                                  $meetingMode
                                             );
                                   }
                              )

                              /*
                              |--------------------------------------------------------------------------
                              | Actual Meeting
                              |--------------------------------------------------------------------------
                              */
                              ->orWhereHas(
                                   'meeting',
                                   function (Builder $query) use ($meetingMode) {

                                        $query->where(
                                             'meeting_mode',
                                             $meetingMode
                                        );
                                   }
                              );
                    }
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Source
          |--------------------------------------------------------------------------
          |
          | Examples:
          |
          | - matchmaking
          | - direct
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['source'])) {

               $query->where(
                    'source',
                    $filters['source']
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Recommended
          |--------------------------------------------------------------------------
          */
          if (
               array_key_exists(
                    'is_recommended',
                    $filters
               )
               &&
               $filters['is_recommended'] !== ''
               &&
               $filters['is_recommended'] !== null
          ) {

               $query->where(
                    'is_recommended',
                    filter_var(
                         $filters['is_recommended'],
                         FILTER_VALIDATE_BOOLEAN
                    )
               );
          }
     }
}