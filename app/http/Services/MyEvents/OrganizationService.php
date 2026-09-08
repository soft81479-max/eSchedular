<?php

namespace App\Services\MyEvents;

use App\Models\Event;
use App\Models\EventOrganization;
use App\Models\EventParticipant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrganizationService {
     /*
     |--------------------------------------------------------------------------
     | Workspace
     |--------------------------------------------------------------------------
     */
     public function workspace(array $workspace,): array {
          return [
               'organizations' => $this->organizations(
                    event: $workspace['event'],
               ),
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Organizations
     |--------------------------------------------------------------------------
     */
     protected function organizations(Event $event,): LengthAwarePaginator {
          return EventOrganization::query()
               /*
               |--------------------------------------------------------------------------
               | Event
               |--------------------------------------------------------------------------
               */
               ->where(
                    'event_id',
                    $event->id
               )

               /*
               |--------------------------------------------------------------------------
               | Confirmed
               |--------------------------------------------------------------------------
               */
               ->confirmed()

               /*
               |--------------------------------------------------------------------------
               | Relationships
               |--------------------------------------------------------------------------
               */
               ->with([
                    'organization',
                    'participantType',
               ])

               /*
               |--------------------------------------------------------------------------
               | Representatives Count
               |--------------------------------------------------------------------------
               */
               ->withCount([
                    'participants as representatives_count' => function ($query) {
                         $query->workspaceAccessible();
                    },
               ])

               /*
               |--------------------------------------------------------------------------
               | Primary Representative
               |--------------------------------------------------------------------------
               */
               ->with([
                    'participants' => function ($query) {
                         $query
                              ->workspaceAccessible()
                              ->where('is_primary', true)
                              ->with([
                              'organizationUser.user',
                              ]);
                    },
               ])

               /*
               |--------------------------------------------------------------------------
               | Order
               |--------------------------------------------------------------------------
               */
               ->orderBy('id')

               /*
               |--------------------------------------------------------------------------
               | Pagination
               |--------------------------------------------------------------------------
               */
               ->paginate(12);
          }
}