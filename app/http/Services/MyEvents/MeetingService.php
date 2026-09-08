<?php

namespace App\Services\MyEvents;

use App\Models\Event;
use App\Models\Meeting;
use App\Models\EventParticipant;

class MeetingService {
     /*
     |--------------------------------------------------------------------------
     | Workspace
     |--------------------------------------------------------------------------
     */
     public function workspace(array $workspace): array {
          $event = $workspace['event'];
          $participant = $workspace['participant'];
          return [
               /*
               |--------------------------------------------------------------------------
               | Summary
               |--------------------------------------------------------------------------
               */
               'meetingStatistics' => $this->meetingStatistics(
                    $event,
                    $participant
               ),

               /*
               |--------------------------------------------------------------------------
               | Filters
               |--------------------------------------------------------------------------
               */
               'filters' => $this->filters(
                    $event,
                    $participant
               ),

               /*
               |--------------------------------------------------------------------------
               | Meetings
               |--------------------------------------------------------------------------
               */
               'meetings' => $this->meetings(
                    $event,
                    $participant
               ),
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Meeting Statistics
     |--------------------------------------------------------------------------
     */
     public function meetingStatistics(Event $event,EventParticipant $participant,): array {
          $meetings = $this->meetings(
               $event,
               $participant
          );

          return [
               /*
               |--------------------------------------------------------------------------
               | Today's Meetings
               |--------------------------------------------------------------------------
               */
               'today' => $meetings
                    ->filter(function ($meeting) {
                         return optional(
                              $meeting->timeSlot?->start_at
                         )?->isToday();
                    })
                    ->count(),

               /*
               |--------------------------------------------------------------------------
               | Accepted (Upcoming)
               |--------------------------------------------------------------------------
               */
               'accepted' => $meetings
                    ->where(
                         'status',
                         Meeting::STATUS_ACCEPTED
                    )
                    ->count(),

               /*
               |--------------------------------------------------------------------------
               | Completed
               |--------------------------------------------------------------------------
               */
               'completed' => $meetings
                    ->where(
                         'status',
                         Meeting::STATUS_COMPLETED
                    )
                    ->count(),

               /*
               |--------------------------------------------------------------------------
               | Missed
               |--------------------------------------------------------------------------
               */
               'missed' => $meetings
                    ->filter(function ($meeting) {
                         return in_array(
                              $meeting->status,
                              [
                                   Meeting::STATUS_CANCELLED,
                                   Meeting::STATUS_NO_SHOW,
                              ]
                         );

                    })
                    ->count(),
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Filters
     |--------------------------------------------------------------------------
     */
     protected function filters(Event $event,EventParticipant $participant,): array {
          return [
               /*
               |--------------------------------------------------------------------------
               | Direction
               |--------------------------------------------------------------------------
               */
               'direction' => [
                    'incoming',
                    'outgoing',
               ],

               /*
               |--------------------------------------------------------------------------
               | Status
               |--------------------------------------------------------------------------
               */
               'status' => [
                    Meeting::STATUS_ACCEPTED,
                    Meeting::STATUS_COMPLETED,
                    Meeting::STATUS_CANCELLED,
                    Meeting::STATUS_NO_SHOW,
               ],

               /*
               |--------------------------------------------------------------------------
               | Meeting Mode
               |--------------------------------------------------------------------------
               */
               'meetingMode' => [
                    Meeting::MODE_PHYSICAL,
                    Meeting::MODE_VIRTUAL,
                    Meeting::MODE_HYBRID,
               ],
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Meetings
     |--------------------------------------------------------------------------
     */
     protected function meetings(Event $event,EventParticipant $participant,) {
          return Meeting::query()
               /*
               |--------------------------------------------------------------------------
               | My Meetings
               |--------------------------------------------------------------------------
               */
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

               /*
               |--------------------------------------------------------------------------
               | Relationships
               |--------------------------------------------------------------------------
               */
               ->with([
                    'meetingRequest',

                    'senderParticipant.organizationUser.user',
                    'senderParticipant.organizationUser.organization',

                    'receiverParticipant.organizationUser.user',
                    'receiverParticipant.organizationUser.organization',

                    'timeSlot.schedule',
               ])

               /*
               |--------------------------------------------------------------------------
               | Latest First
               |--------------------------------------------------------------------------
               */
               ->latest()
               ->get()

               /*
               |--------------------------------------------------------------------------
               | Decorate
               |--------------------------------------------------------------------------
               */
               ->map(function ($meeting) use ($participant) {
                    $other = $meeting->otherParticipant(
                         $participant
                    );

                    $meeting->direction =
                         $meeting->sender_participant_id == $participant->id
                              ? 'Outgoing'
                              : 'Incoming';

                    $meeting->participant_ulid = $other->ulid;
                    $meeting->participant_name = $other->badge_name;
                    $meeting->participant_title = $other->badge_title;
                    $meeting->participant_photo = $other->avatar_url;
                    $meeting->participant_organization =
                         $other
                              ->organizationUser
                              ->organization
                              ->name;
                    return $meeting;
               })
               ->values();
     }

     /*
     |--------------------------------------------------------------------------
     | Datatable
     |--------------------------------------------------------------------------
     */
     public function meetingsForDatatable(array $workspace,array $filters = [],) {
          $meetings = $this->meetings(
               $workspace['event'],
               $workspace['participant']
          );

          /*
          |--------------------------------------------------------------------------
          | Direction
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['direction'])) {
               $meetings = $meetings->where(
                    'direction',
                    ucfirst($filters['direction'])
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Status
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['status'])) {
               $meetings = $meetings->where(
                    'status',
                    $filters['status']
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Meeting Mode
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['mode'])) {
               $meetings = $meetings->where(
                    'meeting_mode',
                    $filters['mode']
               );
          }
          return $meetings
               ->values();
     }
}