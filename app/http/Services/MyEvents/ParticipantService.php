<?php

namespace App\Services\MyEvents;

use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ParticipantService {
     /*
     |--------------------------------------------------------------------------
     | Workspace
     |--------------------------------------------------------------------------
     */
     public function workspace(array $workspace): array {
          return [
               'participants' => $this->participants(
               event: $workspace['event'],
               participant: $workspace['participant'],
               ),
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Participants
     |--------------------------------------------------------------------------
     */
     protected function participants(Event $event,EventParticipant $participant,): LengthAwarePaginator {
          return EventParticipant::query()
               /*
               |--------------------------------------------------------------------------
               | Event
               |--------------------------------------------------------------------------
               */
               ->whereHas(
                    'eventOrganization',
                    fn ($query) => $query->where(
                         'event_id',
                         $event->id
                    )
               )

               /*
               |--------------------------------------------------------------------------
               | Workspace Accessible
               |--------------------------------------------------------------------------
               */
               ->workspaceAccessible()

               /*
               |--------------------------------------------------------------------------
               | Relationships
               |--------------------------------------------------------------------------
               */
               ->with([
                    'organizationUser.user',
                    'organizationUser.organization',
                    'eventOrganization.organization',
                    'eventOrganization.participantType',
               ])

               /*
               |--------------------------------------------------------------------------
               | Order
               |--------------------------------------------------------------------------
               */
               ->orderBy('badge_name')

               /*
               |--------------------------------------------------------------------------
               | Pagination
               |--------------------------------------------------------------------------
               */
               ->paginate(12);
     }
}