<?php

namespace App\Services\Representative;

use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Support\Facades\Auth;

class RepresentativeWorkspaceService
{
     /*
     |--------------------------------------------------------------------------
     | Constructor
     |--------------------------------------------------------------------------
     */
     public function __construct(
          protected RepresentativeToolbarBuilder $toolbarBuilder,
          protected RepresentativeHeroService $heroService,
     ) {
     }

     /*
     |--------------------------------------------------------------------------
     | Workspace
     |--------------------------------------------------------------------------
     */
     public function workspace(Event $event): array
     {
          /*
          |--------------------------------------------------------------------------
          | Authenticated User
          |--------------------------------------------------------------------------
          */
          $user = Auth::user();

          /*
          |--------------------------------------------------------------------------
          | Event Participant
          |--------------------------------------------------------------------------
          */
          $participant = EventParticipant::query()
               ->with([
                    'representative.organization.organizationType',
                    'representative.user',
                    'eventOrganization.participantType',
                    'eventOrganization.organization',
               ])
               ->where('user_id', $user->id)
               ->whereHas('eventOrganization', function ($query) use ($event) {
                    $query->where('event_id', $event->id);
               })
               ->firstOrFail();

          /*
          |--------------------------------------------------------------------------
          | Event Organization
          |--------------------------------------------------------------------------
          */
          $eventOrganization = $participant->eventOrganization;

          /*
          |--------------------------------------------------------------------------
          | Organization
          |--------------------------------------------------------------------------
          */
          $organization = $eventOrganization->organization;

          /*
          |--------------------------------------------------------------------------
          | Representative
          |--------------------------------------------------------------------------
          */
          $representative = $participant->representative;

          /*
          |--------------------------------------------------------------------------
          | Hero
          |--------------------------------------------------------------------------
          */
          $hero = $this->heroService->hero(
               event: $event,
               participant: $participant
          );

          /*
          |--------------------------------------------------------------------------
          | Toolbar
          |--------------------------------------------------------------------------
          */
          $toolbar = $this->toolbarBuilder->get(
               event: $event,
               participant: $participant
          );

          /*
          |--------------------------------------------------------------------------
          | Workspace
          |--------------------------------------------------------------------------
          */
          return compact(
               'event',
               'participant',
               'eventOrganization',
               'organization',
               'representative',
               'hero',
               'toolbar'
          );
     }
}