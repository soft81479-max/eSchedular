<?php

namespace App\Services\Networking;

use App\Models\EventOrganization;
use App\Models\EventParticipant;
use Illuminate\Database\Query\Builder;

class NetworkingEligibilityService
{
     /*
     |--------------------------------------------------------------------------
     | Apply Networking Eligibility Rules
     |--------------------------------------------------------------------------
     |
     | Applies universal participant and organization eligibility rules.
     |
     | These rules are shared by:
     |
     | - Matchmaking recommendations
     | - Direct meeting opportunities
     |
     | This service does NOT decide:
     |
     | - Participant-type matching compatibility
     | - Sender / receiver direction
     | - Meeting conflicts
     | - Recommendation scoring
     | - Recommendation ranking
     |--------------------------------------------------------------------------
     */
     public function apply(
          Builder $query
     ): Builder {

          return $query
               ->tap(
                    fn (Builder $query) =>
                         $this->participantRules($query)
               )
               ->tap(
                    fn (Builder $query) =>
                         $this->organizationRules($query)
               );
     }

     /*
     |--------------------------------------------------------------------------
     | Participant Rules
     |--------------------------------------------------------------------------
     */
     protected function participantRules(
          Builder $query
     ): void {

          $query

               /*
               |--------------------------------------------------------------------------
               | Networking Enabled
               |--------------------------------------------------------------------------
               */
               ->where(
                    'ep1.matchmaking_enabled',
                    true
               )

               ->where(
                    'ep2.matchmaking_enabled',
                    true
               )

               /*
               |--------------------------------------------------------------------------
               | Confirmed Participants
               |--------------------------------------------------------------------------
               */
               ->where(
                    'ep1.status',
                    EventParticipant::STATUS_CONFIRMED
               )

               ->where(
                    'ep2.status',
                    EventParticipant::STATUS_CONFIRMED
               );
     }

     /*
     |--------------------------------------------------------------------------
     | Organization Rules
     |--------------------------------------------------------------------------
     */
     protected function organizationRules(
          Builder $query
     ): void {

          $query

               /*
               |--------------------------------------------------------------------------
               | Networking Enabled
               |--------------------------------------------------------------------------
               */
               ->where(
                    'eo1.matchmaking_enabled',
                    true
               )

               ->where(
                    'eo2.matchmaking_enabled',
                    true
               )

               /*
               |--------------------------------------------------------------------------
               | Confirmed Event Organizations
               |--------------------------------------------------------------------------
               */
               ->where(
                    'eo1.status',
                    EventOrganization::STATUS_CONFIRMED
               )

               ->where(
                    'eo2.status',
                    EventOrganization::STATUS_CONFIRMED
               )

               /*
               |--------------------------------------------------------------------------
               | Different Organizations
               |--------------------------------------------------------------------------
               */
               ->whereColumn(
                    'eo1.organization_id',
                    '!=',
                    'eo2.organization_id'
               );
     }
}