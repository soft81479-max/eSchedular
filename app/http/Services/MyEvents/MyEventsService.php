<?php

namespace App\Services\MyEvents;

use Illuminate\Support\Collection;
use App\Services\Shared\EventAccessService;

use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\ParticipantAvailability;

class MyEventsService {
     public function __construct(
          protected EventAccessService $eventAccessService,
     ) {
     }

     /*
     |--------------------------------------------------------------------------
     | Cards
     |--------------------------------------------------------------------------
     */
     public function cards(): Collection {
          $userId = auth()->id();

          /*
          |--------------------------------------------------------------------------
          | Events
          |--------------------------------------------------------------------------
          */
          $events = $this->eventAccessService
               ->accessibleEvents()
               ->with([
                    /*
                    |--------------------------------------------------------------------------
                    | Event
                    |--------------------------------------------------------------------------
                    */
                    'eventType',
                    'organizer',

                    /*
                    |--------------------------------------------------------------------------
                    | My Event Organization
                    |--------------------------------------------------------------------------
                    */
                    'organizations' => function ($query) use ($userId) {
                         $query
                              ->whereHas('participants.organizationUser', function ($query) use ($userId) {
                                   $query->where(
                                        'user_id',
                                        $userId
                                   );
                              })
                              ->with([
                                   'organization',
                                   'participants' => function ($query) use ($userId) {
                                        $query
                                             ->whereHas('organizationUser', function ($query) use ($userId) {
                                                  $query->where(
                                                       'user_id',
                                                       $userId
                                                  );
                                             })
                                             ->with([
                                                  'organizationUser.user',
                                             ]);
                                   },
                              ]);
                    },
               ])
               ->orderBy('start_date')
               ->get();
          return $this->buildCards($events);
     }

     /*
     |--------------------------------------------------------------------------
     | Build Cards
     |--------------------------------------------------------------------------
     */
     protected function buildCards(Collection $events): Collection {
          return $events->map(function ($event) {
               $eventOrganization = $event->organizations->first();
               $participant = $eventOrganization?->participants->first();
               return [
                    /*
                    |--------------------------------------------------------------------------
                    | Event
                    |--------------------------------------------------------------------------
                    */
                    'event' => $event,

                    /*
                    |--------------------------------------------------------------------------
                    | Context
                    |--------------------------------------------------------------------------
                    */
                    'organization' => $eventOrganization?->organization,
                    'eventOrganization' => $eventOrganization,
                    'participant' => $participant,

                    /*
                    |--------------------------------------------------------------------------
                    | Statistics
                    |--------------------------------------------------------------------------
                    */
                    'statistics' => [
                         /*
                         |--------------------------------------------------------------------------
                         | Meetings
                         |--------------------------------------------------------------------------
                         */
                         'meetings' => Meeting::query()
                              ->where(function ($query) use ($participant) {
                                   $query
                                        ->where('sender_participant_id', $participant?->id)
                                        ->orWhere('receiver_participant_id', $participant?->id);
                              })
                              ->accepted()
                              ->count(),

                         /*
                         |--------------------------------------------------------------------------
                         | Pending Requests
                         |--------------------------------------------------------------------------
                         */
                         'requests' => MeetingRequest::query()
                              ->pending()
                              ->where('receiver_participant_id', $participant?->id)
                              ->count(),

                         /*
                         |--------------------------------------------------------------------------
                         | Availability
                         |--------------------------------------------------------------------------
                         */
                         'availability' => $this->availabilityPercentage($participant),
                    ],
               ];
          });
     }

     /*
     |--------------------------------------------------------------------------
     | Availability Percentage
     |--------------------------------------------------------------------------
     */
     protected function availabilityPercentage($participant): int {
          if (! $participant) {
               return 0;
          }

          $total = ParticipantAvailability::query()
               ->where(
                    'event_participant_id',
                    $participant->id
               )
               ->count();

          if ($total === 0) {
               return 0;
          }

          $available = ParticipantAvailability::query()
               ->where(
                    'event_participant_id',
                    $participant->id
               )
               ->whereIn('status', [
                    ParticipantAvailability::STATUS_AVAILABLE,
                    ParticipantAvailability::STATUS_PREFERRED,
               ])
               ->count();
          return (int) round(($available / $total) * 100);
     }
}