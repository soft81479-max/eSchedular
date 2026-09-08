<?php

namespace App\Services\Shared;

use App\Models\EventParticipant;
use App\Models\OrganizationUser;
use App\Models\Organizer;

class OrganizerService {
     /*
     |--------------------------------------------------------------------------
     | Statistics
     |--------------------------------------------------------------------------
     */
     public function statistics(Organizer $organizer): object {
          return (object) [
               'events' => $organizer->events()->count(),

               'representatives' => OrganizationUser::query()
                    ->whereHas('organization', function ($query) use ($organizer) {
                         $query->where('organizer_id', $organizer->id);
                    })
                    ->count(),

               'participants' => EventParticipant::query()
                    ->whereHas('organizationUser.organization', function ($query) use ($organizer) {
                         $query->where('organizer_id', $organizer->id);
                    })
                    ->count(),
          ];
     }
}