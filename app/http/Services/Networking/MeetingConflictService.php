<?php

namespace App\Services\Networking;

use App\Models\Meeting;
use App\Models\MeetingRequest;
use Illuminate\Database\Query\Builder;

class MeetingConflictService
{
     /*
     |--------------------------------------------------------------------------
     | Apply Meeting Conflict Rules
     |--------------------------------------------------------------------------
     |
     | Removes candidate pair-slot combinations when either participant is
     | already occupied by an active meeting request or active meeting in the
     | same event time slot.
     |
     | Conflict detection is direction-independent.
     |--------------------------------------------------------------------------
     */
     public function apply(
          Builder $query
     ): Builder {

          return $query
               ->tap(
                    fn (Builder $query) =>
                         $this->excludeMeetingRequests($query)
               )
               ->tap(
                    fn (Builder $query) =>
                         $this->excludeMeetings($query)
               );
     }

     /*
     |--------------------------------------------------------------------------
     | Existing Meeting Requests
     |--------------------------------------------------------------------------
     |
     | Exclude the candidate opportunity when either participant already has
     | an active meeting request in the same time slot.
     |--------------------------------------------------------------------------
     */
     protected function excludeMeetingRequests(
          Builder $query
     ): void {

          $query->whereNotExists(
               function ($subQuery) {

                    $subQuery
                         ->selectRaw('1')

                         ->from(
                              'meeting_requests as mr'
                         )

                         /*
                         |--------------------------------------------------------------------------
                         | Same Time Slot
                         |--------------------------------------------------------------------------
                         */
                         ->whereColumn(
                              'mr.event_time_slot_id',
                              'event_time_slots.id'
                         )

                         /*
                         |--------------------------------------------------------------------------
                         | Active Request Status
                         |--------------------------------------------------------------------------
                         */
                         ->whereIn(
                              'mr.status',
                              $this->activeMeetingRequestStatuses()
                         )

                         /*
                         |--------------------------------------------------------------------------
                         | Either Candidate Is Already Occupied
                         |--------------------------------------------------------------------------
                         */
                         ->where(
                              function ($participant) {

                                   $participant

                                        /*
                                        |--------------------------------------------------------------------------
                                        | Candidate A
                                        |--------------------------------------------------------------------------
                                        */
                                        ->whereColumn(
                                             'mr.sender_participant_id',
                                             'ep1.id'
                                        )

                                        ->orWhereColumn(
                                             'mr.receiver_participant_id',
                                             'ep1.id'
                                        )

                                        /*
                                        |--------------------------------------------------------------------------
                                        | Candidate B
                                        |--------------------------------------------------------------------------
                                        */
                                        ->orWhereColumn(
                                             'mr.sender_participant_id',
                                             'ep2.id'
                                        )

                                        ->orWhereColumn(
                                             'mr.receiver_participant_id',
                                             'ep2.id'
                                        );
                              }
                         );
               }
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Existing Meetings
     |--------------------------------------------------------------------------
     |
     | Exclude the candidate opportunity when either participant already has
     | an active meeting in the same time slot.
     |--------------------------------------------------------------------------
     */
     protected function excludeMeetings(
          Builder $query
     ): void {

          $query->whereNotExists(
               function ($subQuery) {

                    $subQuery
                         ->selectRaw('1')

                         ->from(
                              'meetings as m'
                         )

                         /*
                         |--------------------------------------------------------------------------
                         | Same Time Slot
                         |--------------------------------------------------------------------------
                         */
                         ->whereColumn(
                              'm.event_time_slot_id',
                              'event_time_slots.id'
                         )

                         /*
                         |--------------------------------------------------------------------------
                         | Active Meeting Status
                         |--------------------------------------------------------------------------
                         */
                         ->whereIn(
                              'm.status',
                              $this->activeMeetingStatuses()
                         )

                         /*
                         |--------------------------------------------------------------------------
                         | Either Candidate Is Already Occupied
                         |--------------------------------------------------------------------------
                         */
                         ->where(
                              function ($participant) {

                                   $participant

                                        /*
                                        |--------------------------------------------------------------------------
                                        | Candidate A
                                        |--------------------------------------------------------------------------
                                        */
                                        ->whereColumn(
                                             'm.sender_participant_id',
                                             'ep1.id'
                                        )

                                        ->orWhereColumn(
                                             'm.receiver_participant_id',
                                             'ep1.id'
                                        )

                                        /*
                                        |--------------------------------------------------------------------------
                                        | Candidate B
                                        |--------------------------------------------------------------------------
                                        */
                                        ->orWhereColumn(
                                             'm.sender_participant_id',
                                             'ep2.id'
                                        )

                                        ->orWhereColumn(
                                             'm.receiver_participant_id',
                                             'ep2.id'
                                        );
                              }
                         );
               }
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Active Meeting Request Statuses
     |--------------------------------------------------------------------------
     */
     protected function activeMeetingRequestStatuses(): array
     {
          return [
               MeetingRequest::STATUS_PENDING,
               MeetingRequest::STATUS_ACCEPTED,
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Active Meeting Statuses
     |--------------------------------------------------------------------------
     */
     protected function activeMeetingStatuses(): array
     {
          return [
               Meeting::STATUS_PENDING,
               Meeting::STATUS_ACCEPTED,
               Meeting::STATUS_COMPLETED,
          ];
     }
}