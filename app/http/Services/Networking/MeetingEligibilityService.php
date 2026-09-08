<?php

namespace App\Services\Networking;

use App\Models\Event;
use App\Models\EventOrganization;
use App\Models\EventParticipant;
use App\Models\EventTimeSlot;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use Illuminate\Support\Facades\DB;
use App\Models\ParticipantAvailability;
use Illuminate\Validation\ValidationException;

class MeetingEligibilityService
{
     /*
     |--------------------------------------------------------------------------
     | Validate Meeting Request
     |--------------------------------------------------------------------------
     |
     | Authoritative write-time validation for every meeting request source.
     |
     | Used by:
     |
     | - Matchmaking
     | - Direct meeting opportunities
     |
     | The source changes how the request was discovered. It does not bypass
     | the event's networking, matching, availability, or conflict rules.
     |--------------------------------------------------------------------------
     */
     public function validate(
          Event $event,
          EventParticipant $sender,
          EventParticipant $receiver,
          EventTimeSlot $timeSlot,
          string $source
     ): void {

          /*
          |--------------------------------------------------------------------------
          | Request Source
          |--------------------------------------------------------------------------
          */
          $this->validateSource(
               source: $source
          );

          /*
          |--------------------------------------------------------------------------
          | Participants
          |--------------------------------------------------------------------------
          */
          $this->validateParticipants(
               event: $event,
               sender: $sender,
               receiver: $receiver
          );

          /*
          |--------------------------------------------------------------------------
          | Networking Eligibility
          |--------------------------------------------------------------------------
          */
          $this->validateNetworkingEligibility(
               sender: $sender,
               receiver: $receiver
          );

          /*
          |--------------------------------------------------------------------------
          | Configured Match Rule
          |--------------------------------------------------------------------------
          */
          $this->validateMatchRule(
               event: $event,
               sender: $sender,
               receiver: $receiver
          );

          /*
          |--------------------------------------------------------------------------
          | Time Slot
          |--------------------------------------------------------------------------
          */
          $this->validateTimeSlot(
               event: $event,
               timeSlot: $timeSlot
          );

          /*
          |--------------------------------------------------------------------------
          | Availability
          |--------------------------------------------------------------------------
          */
          $this->validateAvailability(
               sender: $sender,
               receiver: $receiver,
               timeSlot: $timeSlot
          );

          /*
          |--------------------------------------------------------------------------
          | Slot Conflicts
          |--------------------------------------------------------------------------
          */
          $this->validateSlotConflicts(
               sender: $sender,
               receiver: $receiver,
               timeSlot: $timeSlot
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Validate Source
     |--------------------------------------------------------------------------
     */
     protected function validateSource(
          string $source
     ): void {

          if (
               ! in_array(
                    $source,
                    MeetingRequest::sources(),
                    true
               )
          ) {

               throw ValidationException::withMessages([
                    'source' =>
                         'The selected meeting request source is invalid.',
               ]);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Validate Participants
     |--------------------------------------------------------------------------
     */
     protected function validateParticipants(
          Event $event,
          EventParticipant $sender,
          EventParticipant $receiver
     ): void {

          /*
          |--------------------------------------------------------------------------
          | Different Participants
          |--------------------------------------------------------------------------
          */
          if ($sender->id === $receiver->id) {

               throw ValidationException::withMessages([
                    'receiver_participant_id' =>
                         'A participant cannot request a meeting with themselves.',
               ]);
          }

          /*
          |--------------------------------------------------------------------------
          | Event Organizations
          |--------------------------------------------------------------------------
          */
          if (
               ! $sender->eventOrganization
               ||
               ! $receiver->eventOrganization
          ) {

               throw ValidationException::withMessages([
                    'participants' =>
                         'The selected participants are not attached to valid event organizations.',
               ]);
          }

          /*
          |--------------------------------------------------------------------------
          | Same Event
          |--------------------------------------------------------------------------
          */
          if (
               $sender->eventOrganization->event_id !== $event->id
               ||
               $receiver->eventOrganization->event_id !== $event->id
          ) {

               throw ValidationException::withMessages([
                    'participants' =>
                         'The selected participants do not belong to this event.',
               ]);
          }

          /*
          |--------------------------------------------------------------------------
          | Different Organizations
          |--------------------------------------------------------------------------
          */
          if (
               $sender->eventOrganization->organization_id
               ===
               $receiver->eventOrganization->organization_id
          ) {

               throw ValidationException::withMessages([
                    'participants' =>
                         'Participants from the same organization cannot request a meeting.',
               ]);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Validate Networking Eligibility
     |--------------------------------------------------------------------------
     */
     protected function validateNetworkingEligibility(
          EventParticipant $sender,
          EventParticipant $receiver
     ): void {

          /*
          |--------------------------------------------------------------------------
          | Confirmed Participants
          |--------------------------------------------------------------------------
          */
          if (
               $sender->status !== EventParticipant::STATUS_CONFIRMED
               ||
               $receiver->status !== EventParticipant::STATUS_CONFIRMED
          ) {

               throw ValidationException::withMessages([
                    'participants' =>
                         'Both participants must be confirmed before a meeting can be requested.',
               ]);
          }

          /*
          |--------------------------------------------------------------------------
          | Participant Networking Enabled
          |--------------------------------------------------------------------------
          */
          if (
               ! $sender->matchmaking_enabled
               ||
               ! $receiver->matchmaking_enabled
          ) {

               throw ValidationException::withMessages([
                    'participants' =>
                         'Networking is not enabled for both participants.',
               ]);
          }

          /*
          |--------------------------------------------------------------------------
          | Confirmed Event Organizations
          |--------------------------------------------------------------------------
          */
          if (
               $sender->eventOrganization->status
                    !== EventOrganization::STATUS_CONFIRMED
               ||
               $receiver->eventOrganization->status
                    !== EventOrganization::STATUS_CONFIRMED
          ) {

               throw ValidationException::withMessages([
                    'participants' =>
                         'Both event organizations must be confirmed before a meeting can be requested.',
               ]);
          }

          /*
          |--------------------------------------------------------------------------
          | Organization Networking Enabled
          |--------------------------------------------------------------------------
          */
          if (
               ! $sender->eventOrganization->matchmaking_enabled
               ||
               ! $receiver->eventOrganization->matchmaking_enabled
          ) {

               throw ValidationException::withMessages([
                    'participants' =>
                         'Networking is not enabled for both organizations.',
               ]);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Validate Configured Match Rule
     |--------------------------------------------------------------------------
     |
     | EventOrganization stores event_participant_type_id.
     |
     | event_participant_types.id
     |     → participant_type_id
     |     → event_type_match_rules source / target participant type
     |--------------------------------------------------------------------------
     */
     protected function validateMatchRule(
          Event $event,
          EventParticipant $sender,
          EventParticipant $receiver
     ): void {

          /*
          |--------------------------------------------------------------------------
          | Sender Participant Type
          |--------------------------------------------------------------------------
          */
          $senderParticipantTypeId = DB::table(
               'event_participant_types'
          )
               ->where(
                    'id',
                    $sender->eventOrganization->event_participant_type_id
               )
               ->value(
                    'participant_type_id'
               );

          /*
          |--------------------------------------------------------------------------
          | Receiver Participant Type
          |--------------------------------------------------------------------------
          */
          $receiverParticipantTypeId = DB::table(
               'event_participant_types'
          )
               ->where(
                    'id',
                    $receiver->eventOrganization->event_participant_type_id
               )
               ->value(
                    'participant_type_id'
               );

          /*
          |--------------------------------------------------------------------------
          | Participant Types Resolved
          |--------------------------------------------------------------------------
          */
          if (
               ! $senderParticipantTypeId
               ||
               ! $receiverParticipantTypeId
          ) {

               throw ValidationException::withMessages([
                    'participants' =>
                         'The participant types could not be resolved for this meeting request.',
               ]);
          }

          /*
          |--------------------------------------------------------------------------
          | Configured Match Rule
          |--------------------------------------------------------------------------
          */
          $allowed = DB::table(
               'event_type_match_rules'
          )
               ->where(
                    'event_type_id',
                    $event->event_type_id
               )
               ->where(
                    'source_participant_type_id',
                    $senderParticipantTypeId
               )
               ->where(
                    'target_participant_type_id',
                    $receiverParticipantTypeId
               )
               ->where(
                    'is_match_allowed',
                    true
               )
               ->exists();

          /*
          |--------------------------------------------------------------------------
          | Match Not Allowed
          |--------------------------------------------------------------------------
          */
          if (! $allowed) {

               throw ValidationException::withMessages([
                    'participants' =>
                         'This participant pairing is not allowed by the event networking configuration.',
               ]);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Validate Time Slot
     |--------------------------------------------------------------------------
     */
     protected function validateTimeSlot(
          Event $event,
          EventTimeSlot $timeSlot
     ): void {

          /*
          |--------------------------------------------------------------------------
          | Event Time Slot
          |--------------------------------------------------------------------------
          */
          if ($timeSlot->schedule?->event_id !== $event->id) {

               throw ValidationException::withMessages([
                    'event_time_slot_id' =>
                         'The selected time slot does not belong to this event.',
               ]);
          }

          /*
          |--------------------------------------------------------------------------
          | Meeting Slot
          |--------------------------------------------------------------------------
          */
          if ($timeSlot->type !== 'meeting') {

               throw ValidationException::withMessages([
                    'event_time_slot_id' =>
                         'Meetings cannot be requested in this time slot.',
               ]);
          }

          /*
          |--------------------------------------------------------------------------
          | Bookable Slot
          |--------------------------------------------------------------------------
          */
          if (! $timeSlot->is_bookable) {

               throw ValidationException::withMessages([
                    'event_time_slot_id' =>
                         'The selected time slot is not bookable.',
               ]);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Validate Availability
     |--------------------------------------------------------------------------
     */
     protected function validateAvailability(
          EventParticipant $sender,
          EventParticipant $receiver,
          EventTimeSlot $timeSlot
     ): void {

          if (
               ! $this->isAvailable(
                    participant: $sender,
                    timeSlot: $timeSlot
               )
          ) {

               throw ValidationException::withMessages([
                    'sender_participant_id' =>
                         'The sender is not available for this time slot.',
               ]);
          }

          if (
               ! $this->isAvailable(
                    participant: $receiver,
                    timeSlot: $timeSlot
               )
          ) {

               throw ValidationException::withMessages([
                    'receiver_participant_id' =>
                         'The receiver is not available for this time slot.',
               ]);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Participant Available
     |--------------------------------------------------------------------------
     */
     protected function isAvailable(
          EventParticipant $participant,
          EventTimeSlot $timeSlot
     ): bool {

          return ParticipantAvailability::query()

               ->where(
                    'event_participant_id',
                    $participant->id
               )

               ->where(
                    'event_time_slot_id',
                    $timeSlot->id
               )

               ->whereIn(
                    'status',
                    ParticipantAvailability::meetingEligibleStatuses()
               )

               ->exists();
     }

     /*
     |--------------------------------------------------------------------------
     | Validate Slot Conflicts
     |--------------------------------------------------------------------------
     */
     protected function validateSlotConflicts(
          EventParticipant $sender,
          EventParticipant $receiver,
          EventTimeSlot $timeSlot
     ): void {

          /*
          |--------------------------------------------------------------------------
          | Sender Request Conflict
          |--------------------------------------------------------------------------
          */
          if (
               $this->hasActiveRequest(
                    participant: $sender,
                    timeSlot: $timeSlot
               )
          ) {

               throw ValidationException::withMessages([
                    'sender_participant_id' =>
                         'The sender already has an active meeting request in this time slot.',
               ]);
          }

          /*
          |--------------------------------------------------------------------------
          | Receiver Request Conflict
          |--------------------------------------------------------------------------
          */
          if (
               $this->hasActiveRequest(
                    participant: $receiver,
                    timeSlot: $timeSlot
               )
          ) {

               throw ValidationException::withMessages([
                    'receiver_participant_id' =>
                         'The receiver already has an active meeting request in this time slot.',
               ]);
          }

          /*
          |--------------------------------------------------------------------------
          | Sender Meeting Conflict
          |--------------------------------------------------------------------------
          */
          if (
               $this->hasActiveMeeting(
                    participant: $sender,
                    timeSlot: $timeSlot
               )
          ) {

               throw ValidationException::withMessages([
                    'sender_participant_id' =>
                         'The sender already has a meeting in this time slot.',
               ]);
          }

          /*
          |--------------------------------------------------------------------------
          | Receiver Meeting Conflict
          |--------------------------------------------------------------------------
          */
          if (
               $this->hasActiveMeeting(
                    participant: $receiver,
                    timeSlot: $timeSlot
               )
          ) {

               throw ValidationException::withMessages([
                    'receiver_participant_id' =>
                         'The receiver already has a meeting in this time slot.',
               ]);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Active Meeting Request
     |--------------------------------------------------------------------------
     */
     protected function hasActiveRequest(
          EventParticipant $participant,
          EventTimeSlot $timeSlot
     ): bool {

          return MeetingRequest::query()

               ->where(
                    'event_time_slot_id',
                    $timeSlot->id
               )

               ->where(
                    function ($query) use ($participant) {

                         $query
                              ->where(
                                   'sender_participant_id',
                                   $participant->id
                              )

                              ->orWhere(
                                   'receiver_participant_id',
                                   $participant->id
                              );
                    }
               )

               ->whereIn(
                    'status',
                    $this->activeRequestStatuses()
               )

               ->exists();
     }

     /*
     |--------------------------------------------------------------------------
     | Active Meeting
     |--------------------------------------------------------------------------
     */
     protected function hasActiveMeeting(
          EventParticipant $participant,
          EventTimeSlot $timeSlot
     ): bool {

          return Meeting::query()

               ->where(
                    'event_time_slot_id',
                    $timeSlot->id
               )

               ->where(
                    function ($query) use ($participant) {

                         $query
                              ->where(
                                   'sender_participant_id',
                                   $participant->id
                              )

                              ->orWhere(
                                   'receiver_participant_id',
                                   $participant->id
                              );
                    }
               )

               ->whereIn(
                    'status',
                    $this->activeMeetingStatuses()
               )

               ->exists();
     }

     /*
     |--------------------------------------------------------------------------
     | Active Request Statuses
     |--------------------------------------------------------------------------
     */
     protected function activeRequestStatuses(): array
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