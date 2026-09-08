<?php

namespace App\Services\MyEvents;

use App\Models\Event;

use App\Models\User;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\EventParticipant;
use App\Models\ParticipantAvailability;
use App\Services\Shared\EventAccessService;
use App\Services\Shared\MyWorkspaceBuilder;
use App\Services\Networking\ParticipantRecommendationService;

class MyEventWorkspaceService {
     public function __construct(
          protected EventAccessService $eventAccessService,
          protected MyWorkspaceBuilder $workspaceBuilder,
          protected ParticipantRecommendationService $recommendationService,
     ) {
     }

     /*
     |--------------------------------------------------------------------------
     | Workspace
     |--------------------------------------------------------------------------
     */
     public function workspace(Event $event): array {
          /*
          |--------------------------------------------------------------------------
          | Event
          |--------------------------------------------------------------------------
          */
          $event = $this->eventAccessService->show($event);

          /*
          |--------------------------------------------------------------------------
          | Participant
          |--------------------------------------------------------------------------
          */
          $participant = $this->participant($event)
               ->load([
                    'organizationUser.user',
                    'organizationUser.organization',
                    'eventOrganization.organization',
               ]);

          /*
          |--------------------------------------------------------------------------
          | Context
          |--------------------------------------------------------------------------
          */
          $eventOrganization = $participant->eventOrganization;
          $organization = $eventOrganization->organization;

          /*
          |--------------------------------------------------------------------------
          | Workspace
          |--------------------------------------------------------------------------
          */
          return [
               'event'             => $event,
               'participant'       => $participant,
               'eventOrganization' => $eventOrganization,
               'organization'      => $organization,
               'toolbar' => $this->workspaceBuilder->buildForUser($event, auth()->user()),

               /*
               |--------------------------------------------------------------------------
               | Overview
               |--------------------------------------------------------------------------
               */
               'statistics'         => $this->statistics($participant),
               'myDay'              => $this->myDay($participant),
               'meetingRequests'    => $this->meetingRequests($participant),
               'meetings'           => $this->meetings($participant),
               'availability'       => $this->availability($participant),
               'organizationWidget' => $this->organization($participant),
               'announcements'      => $this->announcements($event),
               'recommendedMatches' => $this->recommendedMatches($event,$participant),
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Statistics
     |--------------------------------------------------------------------------
     */
     protected function statistics(EventParticipant $participant): array {
          /*
          |--------------------------------------------------------------------------
          | Meetings
          |--------------------------------------------------------------------------
          */
          $meetings = Meeting::query()
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
               ->accepted()
               ->count();

          /*
          |--------------------------------------------------------------------------
          | Pending Requests
          |--------------------------------------------------------------------------
          */
          $requests = MeetingRequest::query()
               ->where(
                    'receiver_participant_id',
                    $participant->id
               )
               ->pending()
               ->count();

          /*
          |--------------------------------------------------------------------------
          | Availability
          |--------------------------------------------------------------------------
          */
          $availabilityQuery = ParticipantAvailability::query()
               ->where(
                    'event_participant_id',
                    $participant->id
               );

          $totalSlots = (clone $availabilityQuery)->count();
          $availableSlots = (clone $availabilityQuery)
               ->whereIn(
                    'status',
                    ParticipantAvailability::meetingEligibleStatuses()
               )
               ->count();

          $availability = $totalSlots > 0
               ? round(($availableSlots / $totalSlots) * 100)
               : 0;

          /*
          |--------------------------------------------------------------------------
          | Profile
          |--------------------------------------------------------------------------
          */
          $profile = 100;

          /*
          |--------------------------------------------------------------------------
          | Return
          |--------------------------------------------------------------------------
          */
          return [
               'meetings'     => $meetings,
               'requests'     => $requests,
               'availability' => $availability,
               'profile'      => $profile,
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | My Day
     |--------------------------------------------------------------------------
     */
     protected function myDay(EventParticipant $participant): array {
          /*
          |--------------------------------------------------------------------------
          | Meetings
          |--------------------------------------------------------------------------
          */
          $meetings = Meeting::query()
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
               ->accepted()
               ->whereHas('timeSlot', function ($query) {
                    $query->whereDate(
                         'start_at',
                         today()
                    );
               })
               ->with([
                    'senderParticipant.organizationUser.user',
                    'senderParticipant.organizationUser.organization',
                    'receiverParticipant.organizationUser.user',
                    'receiverParticipant.organizationUser.organization',
                    'timeSlot',
               ])
               ->get()
               ->sortBy(function ($meeting) {
                    return $meeting->timeSlot->start_at;
               })
               ->values();

          /*
          |--------------------------------------------------------------------------
          | Next Meeting
          |--------------------------------------------------------------------------
          */
          $nextMeeting = $meetings->first();

          /*
          |--------------------------------------------------------------------------
          | Return
          |--------------------------------------------------------------------------
          */
          return [
               'date' => today(),
               'meetings' => $meetings,
               'nextMeeting' => $nextMeeting,
               'totalMeetings' => $meetings->count(),
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Meeting Requests
     |--------------------------------------------------------------------------
     */
     protected function meetingRequests(EventParticipant $participant) {
          return MeetingRequest::query()
               ->where(
                    'receiver_participant_id',
                    $participant->id
               )
               ->pending()
               ->with([
                    'senderParticipant.organizationUser.user',
                    'senderParticipant.organizationUser.organization',
                    'timeSlot',
               ])
               ->orderBy('event_time_slot_id')
               ->limit(5)
               ->get();
     }

     /*
     |--------------------------------------------------------------------------
     | Meetings
     |--------------------------------------------------------------------------
     */
     protected function meetings(EventParticipant $participant) {
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
               | Status
               |--------------------------------------------------------------------------
               */
               ->accepted()

               /*
               |--------------------------------------------------------------------------
               | Upcoming Only
               |--------------------------------------------------------------------------
               */
               ->whereHas('timeSlot', function ($query) {
                    $query->where(
                         'start_at',
                         '>=',
                         now()
                    );
               })

               /*
               |--------------------------------------------------------------------------
               | Relationships
               |--------------------------------------------------------------------------
               */
               ->with([
                    'senderParticipant.organizationUser.user',
                    'senderParticipant.organizationUser.organization',
                    'receiverParticipant.organizationUser.user',
                    'receiverParticipant.organizationUser.organization',
                    'timeSlot',
               ])

               /*
               |--------------------------------------------------------------------------
               | Load & Sort
               |--------------------------------------------------------------------------
               */
               ->get()
               ->sortBy(function ($meeting) {
                    return $meeting->timeSlot->start_at;
               })

               /*
               |--------------------------------------------------------------------------
               | Top 5
               |--------------------------------------------------------------------------
               */
               ->take(5)
               ->values();
     }

     /*
     |--------------------------------------------------------------------------
     | Availability
     |--------------------------------------------------------------------------
     */
     protected function availability(EventParticipant $participant): array {
          /*
          |--------------------------------------------------------------------------
          | Query
          |--------------------------------------------------------------------------
          */
          $query = ParticipantAvailability::query()
               ->where(
                    'event_participant_id',
                    $participant->id
               );

          /*
          |--------------------------------------------------------------------------
          | Counts
          |--------------------------------------------------------------------------
          */
          $total = (clone $query)->count();
          $available = (clone $query)
               ->available()
               ->count();
          $preferred = (clone $query)
               ->preferred()
               ->count();
          $unavailable = (clone $query)
               ->unavailable()
               ->count();

          /*
          |--------------------------------------------------------------------------
          | Percentage
          |--------------------------------------------------------------------------
          */
          $percentage = $total > 0
               ? round((($available + $preferred) / $total) * 100)
               : 0;

          /*
          |--------------------------------------------------------------------------
          | Return
          |--------------------------------------------------------------------------
          */
          return [
               'total'        => $total,
               'available'    => $available,
               'preferred'    => $preferred,
               'unavailable'  => $unavailable,
               'percentage'   => $percentage,
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Organization
     |--------------------------------------------------------------------------
     */
     protected function organization(EventParticipant $participant): array {
          /*
          |--------------------------------------------------------------------------
          | Organization
          |--------------------------------------------------------------------------
          */
          $organization = $participant
               ->eventOrganization
               ->organization;

          /*
          |--------------------------------------------------------------------------
          | Statistics
          |--------------------------------------------------------------------------
          */
          $participants = EventParticipant::query()
               ->where(
                    'event_organization_id',
                    $participant->event_organization_id
               )
               ->count();

          /*
          |--------------------------------------------------------------------------
          | Booth
          |--------------------------------------------------------------------------
          */
          $booth = null;
          if (method_exists($participant->eventOrganization, 'booth')) {
               $booth = optional(
                    $participant->eventOrganization->booth
               )->code;
          }

          /*
          |--------------------------------------------------------------------------
          | Return
          |--------------------------------------------------------------------------
          */
          return [
               'model' => $organization,
               'participants' => $participants,
               'representatives' => $participants,
               'booth' => $booth,
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Announcements
     |--------------------------------------------------------------------------
     */
     protected function announcements(Event $event) {
          return collect();
     }

     /*
     |--------------------------------------------------------------------------
     | Recommended Matches
     |--------------------------------------------------------------------------
     */
     protected function recommendedMatches(Event $event,EventParticipant $participant) {
          return $this->recommendationService
               ->forParticipant(
                    event: $event,
                    participant: $participant
               )
               //->take(5)
               ->values();
     }

     /*
     |--------------------------------------------------------------------------
     | Logged-in Participant
     |--------------------------------------------------------------------------
     */
     public function participant(Event $event): EventParticipant {
          return EventParticipant::query()
               ->whereHas('eventOrganization', function ($query) use ($event) {
                    $query->where('event_id', $event->id);
               })
               ->whereHas('organizationUser', function ($query) {
                    $query->where(
                         'user_id',
                         auth()->id()
                    );
               })
               ->firstOrFail();
     }
}