<?php

namespace App\Services\Networking;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventTimeSlot;
use App\Models\MeetingRequest;
use Illuminate\Support\Facades\DB;

class MeetingRequestServiceX {
     /*
     |--------------------------------------------------------------------------
     | Constructor
     |--------------------------------------------------------------------------
     */
     public function __construct(
          protected MeetingEligibilityService $eligibilityService
     ) {
     }

     /*
     |--------------------------------------------------------------------------
     | Create Meeting Request
     |--------------------------------------------------------------------------
     */
     public function create(
          Event $event,
          int $senderParticipantId,
          int $receiverParticipantId,
          int $eventTimeSlotId,
          string $meetingMode,
          ?string $message = null,
          ?int $recommendationScore = null,
          string $source = MeetingRequest::SOURCE_DIRECT
     ): MeetingRequest {
          /*
          |--------------------------------------------------------------------------
          | Transaction
          |--------------------------------------------------------------------------
          */
          return DB::transaction(
               function () use (
                    $event,
                    $senderParticipantId,
                    $receiverParticipantId,
                    $eventTimeSlotId,
                    $meetingMode,
                    $message,
                    $recommendationScore,
                    $source
               ) {

                    /*
                    |--------------------------------------------------------------------------
                    | Sender Participant
                    |--------------------------------------------------------------------------
                    */
                    $sender = EventParticipant::query()

                         ->with([
                              'eventOrganization',
                         ])

                         ->findOrFail(
                              $senderParticipantId
                         );

                    /*
                    |--------------------------------------------------------------------------
                    | Receiver Participant
                    |--------------------------------------------------------------------------
                    */
                    $receiver = EventParticipant::query()

                         ->with([
                              'eventOrganization',
                         ])

                         ->findOrFail(
                              $receiverParticipantId
                         );

                    /*
                    |--------------------------------------------------------------------------
                    | Event Time Slot
                    |--------------------------------------------------------------------------
                    */
                    $timeSlot = EventTimeSlot::query()

                         ->with([
                              'schedule',
                         ])

                         ->findOrFail(
                              $eventTimeSlotId
                         );

                    /*
                    |--------------------------------------------------------------------------
                    | Meeting Eligibility
                    |--------------------------------------------------------------------------
                    |
                    | Authoritative write-time validation.
                    |--------------------------------------------------------------------------
                    */
                    $this->eligibilityService->validate(
                         event: $event,
                         sender: $sender,
                         receiver: $receiver,
                         timeSlot: $timeSlot,
                         source: $source
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Matchmaking Recommendation
                    |--------------------------------------------------------------------------
                    */
                    $isRecommended =
                         $source === MeetingRequest::SOURCE_MATCHMAKING;

                    /*
                    |--------------------------------------------------------------------------
                    | Meeting Request
                    |--------------------------------------------------------------------------
                    */
                    return MeetingRequest::create([

                         /*
                         |--------------------------------------------------------------------------
                         | Time Slot
                         |--------------------------------------------------------------------------
                         */
                         'event_time_slot_id' =>
                              $timeSlot->id,

                         /*
                         |--------------------------------------------------------------------------
                         | Participants
                         |--------------------------------------------------------------------------
                         */
                         'sender_participant_id' =>
                              $sender->id,

                         'receiver_participant_id' =>
                              $receiver->id,

                         /*
                         |--------------------------------------------------------------------------
                         | Meeting Mode
                         |--------------------------------------------------------------------------
                         */
                         'meeting_mode' =>
                              $meetingMode,

                         /*
                         |--------------------------------------------------------------------------
                         | Source
                         |--------------------------------------------------------------------------
                         */
                         'source' =>
                              $source,

                         /*
                         |--------------------------------------------------------------------------
                         | Recommendation
                         |--------------------------------------------------------------------------
                         */
                         'is_recommended' =>
                              $isRecommended,

                         'recommendation_score' =>
                              $isRecommended
                                   ? $recommendationScore
                                   : null,

                         /*
                         |--------------------------------------------------------------------------
                         | Message
                         |--------------------------------------------------------------------------
                         */
                         'message' =>
                              $message,

                         /*
                         |--------------------------------------------------------------------------
                         | Status
                         |--------------------------------------------------------------------------
                         */
                         'status' =>
                              MeetingRequest::STATUS_PENDING,

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
     | Meeting Request Statistics
     |--------------------------------------------------------------------------
     */
     public  function meetingRequestStatistics(EventParticipant $participant,): array {
          $requests = MeetingRequest::query()
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
               ->get();

          return [
               /*
               |--------------------------------------------------------------------------
               | Pending
               |--------------------------------------------------------------------------
               */
               'pending' => $requests
                    ->where('status', MeetingRequest::STATUS_PENDING)
                    ->count(),

               /*
               |--------------------------------------------------------------------------
               | Accepted
               |--------------------------------------------------------------------------
               */
               'accepted' => $requests
                    ->where('status', MeetingRequest::STATUS_ACCEPTED)
                    ->count(),

               /*
               |--------------------------------------------------------------------------
               | Rejected
               |--------------------------------------------------------------------------
               */
               'rejected' => $requests
                    ->where('status', MeetingRequest::STATUS_REJECTED)
                    ->count(),

               /*
               |--------------------------------------------------------------------------
               | Cancelled
               |--------------------------------------------------------------------------
               */
               'cancelled' => $requests
                    ->where('status', MeetingRequest::STATUS_CANCELLED)
                    ->count(),
          ];
     }
}