<?php

namespace App\Services\Networking;

use App\Models\Meeting;
use App\Models\MeetingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeetingLifecycleService
{
     /*
     |--------------------------------------------------------------------------
     | Accept Meeting Request
     |--------------------------------------------------------------------------
     */
     public function accept(
          MeetingRequest $meetingRequest
     ): Meeting {

          return DB::transaction(
               function () use ($meetingRequest) {

                    /*
                    |--------------------------------------------------------------------------
                    | Lock Meeting Request
                    |--------------------------------------------------------------------------
                    */
                    $meetingRequest = MeetingRequest::query()

                         ->with([
                              'meeting',
                         ])

                         ->lockForUpdate()

                         ->findOrFail(
                              $meetingRequest->id
                         );

                    /*
                    |--------------------------------------------------------------------------
                    | Validate Request Status
                    |--------------------------------------------------------------------------
                    */
                    if (! $meetingRequest->isPending()) {

                         throw ValidationException::withMessages([
                              'meeting_request' =>
                                   'Only pending meeting requests can be accepted.',
                         ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Existing Meeting
                    |--------------------------------------------------------------------------
                    */
                    if ($meetingRequest->meeting) {

                         throw ValidationException::withMessages([
                              'meeting_request' =>
                                   'A meeting has already been created for this request.',
                         ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Revalidate Slot Availability
                    |--------------------------------------------------------------------------
                    |
                    | A meeting request may have been valid when created but become
                    | stale before acceptance because either participant acquired
                    | another accepted meeting in the same slot.
                    |--------------------------------------------------------------------------
                    */
                    $this->validateAcceptanceConflicts(
                         meetingRequest: $meetingRequest
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Accept Meeting Request
                    |--------------------------------------------------------------------------
                    */
                    $acceptedAt = now();

                    $meetingRequest->update([

                         'status' =>
                              MeetingRequest::STATUS_ACCEPTED,

                         'accepted_at' =>
                              $acceptedAt,

                         'updated_by' =>
                              auth()->id(),
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Create Meeting
                    |--------------------------------------------------------------------------
                    */
                    return Meeting::create([

                         /*
                         |--------------------------------------------------------------------------
                         | Meeting Request
                         |--------------------------------------------------------------------------
                         */
                         'meeting_request_id' =>
                              $meetingRequest->id,

                         /*
                         |--------------------------------------------------------------------------
                         | Time Slot
                         |--------------------------------------------------------------------------
                         */
                         'event_time_slot_id' =>
                              $meetingRequest->event_time_slot_id,

                         /*
                         |--------------------------------------------------------------------------
                         | Participants
                         |--------------------------------------------------------------------------
                         */
                         'sender_participant_id' =>
                              $meetingRequest->sender_participant_id,

                         'receiver_participant_id' =>
                              $meetingRequest->receiver_participant_id,

                         /*
                         |--------------------------------------------------------------------------
                         | Meeting Mode
                         |--------------------------------------------------------------------------
                         */
                         'meeting_mode' =>
                              $meetingRequest->meeting_mode,

                         /*
                         |--------------------------------------------------------------------------
                         | Recommendation
                         |--------------------------------------------------------------------------
                         */
                         'is_recommended' =>
                              $meetingRequest->is_recommended,

                         'recommendation_score' =>
                              $meetingRequest->recommendation_score,

                         /*
                         |--------------------------------------------------------------------------
                         | Status
                         |--------------------------------------------------------------------------
                         */
                         'status' =>
                              Meeting::STATUS_ACCEPTED,

                         'accepted_at' =>
                              $acceptedAt,

                         /*
                         |--------------------------------------------------------------------------
                         | Audit
                         |--------------------------------------------------------------------------
                         */
                         'created_by' =>
                              auth()->id(),
                    ]);
               }
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Reject Meeting Request
     |--------------------------------------------------------------------------
     */
     public function reject(
          MeetingRequest $meetingRequest
     ): MeetingRequest {

          return DB::transaction(
               function () use ($meetingRequest) {

                    /*
                    |--------------------------------------------------------------------------
                    | Lock Meeting Request
                    |--------------------------------------------------------------------------
                    */
                    $meetingRequest = MeetingRequest::query()

                         ->lockForUpdate()

                         ->findOrFail(
                              $meetingRequest->id
                         );

                    /*
                    |--------------------------------------------------------------------------
                    | Validate Status
                    |--------------------------------------------------------------------------
                    */
                    if (! $meetingRequest->isPending()) {

                         throw ValidationException::withMessages([
                              'meeting_request' =>
                                   'Only pending meeting requests can be rejected.',
                         ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Reject Meeting Request
                    |--------------------------------------------------------------------------
                    */
                    $meetingRequest->update([

                         'status' =>
                              MeetingRequest::STATUS_REJECTED,

                         'rejected_at' =>
                              now(),

                         'updated_by' =>
                              auth()->id(),
                    ]);

                    return $meetingRequest->refresh();
               }
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Cancel Meeting Request
     |--------------------------------------------------------------------------
     */
     public function cancelRequest(
          MeetingRequest $meetingRequest
     ): MeetingRequest {

          return DB::transaction(
               function () use ($meetingRequest) {

                    /*
                    |--------------------------------------------------------------------------
                    | Lock Meeting Request
                    |--------------------------------------------------------------------------
                    */
                    $meetingRequest = MeetingRequest::query()

                         ->lockForUpdate()

                         ->findOrFail(
                              $meetingRequest->id
                         );

                    /*
                    |--------------------------------------------------------------------------
                    | Validate Status
                    |--------------------------------------------------------------------------
                    */
                    if (! $meetingRequest->isPending()) {

                         throw ValidationException::withMessages([
                              'meeting_request' =>
                                   'Only pending meeting requests can be cancelled.',
                         ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Cancel Meeting Request
                    |--------------------------------------------------------------------------
                    */
                    $meetingRequest->update([

                         'status' =>
                              MeetingRequest::STATUS_CANCELLED,

                         'cancelled_at' =>
                              now(),

                         'updated_by' =>
                              auth()->id(),
                    ]);

                    return $meetingRequest->refresh();
               }
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Cancel Meeting
     |--------------------------------------------------------------------------
     */
     public function cancel(
          Meeting $meeting
     ): Meeting {

          return DB::transaction(
               function () use ($meeting) {

                    /*
                    |--------------------------------------------------------------------------
                    | Lock Meeting
                    |--------------------------------------------------------------------------
                    */
                    $meeting = Meeting::query()

                         ->with([
                              'meetingRequest',
                         ])

                         ->lockForUpdate()

                         ->findOrFail(
                              $meeting->id
                         );

                    /*
                    |--------------------------------------------------------------------------
                    | Validate Status
                    |--------------------------------------------------------------------------
                    */
                    if (
                         ! in_array(
                              $meeting->status,
                              [
                                   Meeting::STATUS_PENDING,
                                   Meeting::STATUS_ACCEPTED,
                              ],
                              true
                         )
                    ) {

                         throw ValidationException::withMessages([
                              'meeting' =>
                                   'This meeting cannot be cancelled in its current status.',
                         ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Cancel Meeting
                    |--------------------------------------------------------------------------
                    */
                    $cancelledAt = now();

                    $meeting->update([

                         'status' =>
                              Meeting::STATUS_CANCELLED,

                         'cancelled_at' =>
                              $cancelledAt,

                         'updated_by' =>
                              auth()->id(),
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Synchronize Meeting Request
                    |--------------------------------------------------------------------------
                    */
                    if ($meeting->meetingRequest) {

                         $meeting->meetingRequest->update([

                              'status' =>
                                   MeetingRequest::STATUS_CANCELLED,

                              'cancelled_at' =>
                                   $cancelledAt,

                              'updated_by' =>
                                   auth()->id(),
                         ]);
                    }

                    return $meeting->refresh();
               }
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Complete Meeting
     |--------------------------------------------------------------------------
     */
     public function complete(
          Meeting $meeting
     ): Meeting {

          return DB::transaction(
               function () use ($meeting) {

                    /*
                    |--------------------------------------------------------------------------
                    | Lock Meeting
                    |--------------------------------------------------------------------------
                    */
                    $meeting = Meeting::query()

                         ->lockForUpdate()

                         ->findOrFail(
                              $meeting->id
                         );

                    /*
                    |--------------------------------------------------------------------------
                    | Validate Status
                    |--------------------------------------------------------------------------
                    */
                    if (! $meeting->isAccepted()) {

                         throw ValidationException::withMessages([
                              'meeting' =>
                                   'Only accepted meetings can be completed.',
                         ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Complete Meeting
                    |--------------------------------------------------------------------------
                    */
                    $meeting->update([

                         'status' =>
                              Meeting::STATUS_COMPLETED,

                         'completed_at' =>
                              now(),

                         'updated_by' =>
                              auth()->id(),
                    ]);

                    return $meeting->refresh();
               }
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Mark No Show
     |--------------------------------------------------------------------------
     */
     public function noShow(
          Meeting $meeting
     ): Meeting {

          return DB::transaction(
               function () use ($meeting) {

                    /*
                    |--------------------------------------------------------------------------
                    | Lock Meeting
                    |--------------------------------------------------------------------------
                    */
                    $meeting = Meeting::query()

                         ->lockForUpdate()

                         ->findOrFail(
                              $meeting->id
                         );

                    /*
                    |--------------------------------------------------------------------------
                    | Validate Status
                    |--------------------------------------------------------------------------
                    */
                    if (! $meeting->isAccepted()) {

                         throw ValidationException::withMessages([
                              'meeting' =>
                                   'Only accepted meetings can be marked as no-show.',
                         ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Mark No Show
                    |--------------------------------------------------------------------------
                    */
                    $meeting->update([

                         'status' =>
                              Meeting::STATUS_NO_SHOW,

                         'updated_by' =>
                              auth()->id(),
                    ]);

                    return $meeting->refresh();
               }
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Validate Acceptance Conflicts
     |--------------------------------------------------------------------------
     |
     | The current request itself must not be treated as a conflict.
     |
     | Other active requests are not enough by themselves to block acceptance:
     | the authoritative hard conflict at acceptance time is an existing active
     | Meeting occupying either participant in the same slot.
     |--------------------------------------------------------------------------
     */
     protected function validateAcceptanceConflicts(
          MeetingRequest $meetingRequest
     ): void {

          $hasConflict = Meeting::query()

               /*
               |--------------------------------------------------------------------------
               | Same Time Slot
               |--------------------------------------------------------------------------
               */
               ->where(
                    'event_time_slot_id',
                    $meetingRequest->event_time_slot_id
               )

               /*
               |--------------------------------------------------------------------------
               | Active Meeting
               |--------------------------------------------------------------------------
               */
               ->whereIn(
                    'status',
                    [
                         Meeting::STATUS_PENDING,
                         Meeting::STATUS_ACCEPTED,
                         Meeting::STATUS_COMPLETED,
                    ]
               )

               /*
               |--------------------------------------------------------------------------
               | Either Participant Is Occupied
               |--------------------------------------------------------------------------
               */
               ->where(
                    function ($query) use ($meetingRequest) {

                         $query

                              /*
                              |--------------------------------------------------------------------------
                              | Sender
                              |--------------------------------------------------------------------------
                              */
                              ->where(
                                   'sender_participant_id',
                                   $meetingRequest->sender_participant_id
                              )

                              ->orWhere(
                                   'receiver_participant_id',
                                   $meetingRequest->sender_participant_id
                              )

                              /*
                              |--------------------------------------------------------------------------
                              | Receiver
                              |--------------------------------------------------------------------------
                              */
                              ->orWhere(
                                   'sender_participant_id',
                                   $meetingRequest->receiver_participant_id
                              )

                              ->orWhere(
                                   'receiver_participant_id',
                                   $meetingRequest->receiver_participant_id
                              );
                    }
               )

               ->exists();

          if ($hasConflict) {

               throw ValidationException::withMessages([
                    'meeting_request' =>
                         'One of the participants already has a meeting in this time slot.',
               ]);
          }
     }
}