<?php

namespace App\Services\Networking;

use App\Models\Event;
use App\Models\EventParticipant;

class CurrentParticipantService
{
     /*
     |--------------------------------------------------------------------------
     | Current Participant
     |--------------------------------------------------------------------------
     */
     public function current(Event $event): EventParticipant
     {
          $participant = auth()->user()
               ->eventParticipants()
               ->with([
                    'organizationUser.organization',
                    'organizationUser.user',
                    'eventOrganization',
               ])
               ->whereHas('eventOrganization', function ($query) use ($event) {
                    $query->where('event_id', $event->id);
               })
               ->first();

          abort_unless(
               $participant,
               403,
               'You are not registered as a participant for this event.'
          );

          return $participant;
     }
}