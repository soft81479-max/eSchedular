<?php

namespace App\Services\Shared;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrganizationAccessService {
     /*
     |--------------------------------------------------------------------------
     | Current Organizer
     |--------------------------------------------------------------------------
     */
     protected function organizerId(): ?int {
          return auth()->user()
               ->organizers()
               ->value('organizers.id');
     }

     /*
     |--------------------------------------------------------------------------
     | Visible Organizations
     |--------------------------------------------------------------------------
     */
     public function visibleOrganizations(): Builder {
          $query = Organization::query()
               ->with([
                    'organizer',
                    'owner',
                    'organizationType',
                    'country',
                    'state',
                    'city',
               ])
               ->withCount([
                    'organizationUsers',
                    'eventOrganizations',
               ]);

          /*
          |--------------------------------------------------------------------------
          | Super Administrator / Administrator
          |--------------------------------------------------------------------------
          */
          if (auth()->user()->hasRole([
               'super_admin',
               'admin',
          ])) {
               return $query;
          }

          /*
          |--------------------------------------------------------------------------
          | Organizer
          |--------------------------------------------------------------------------
          */
          return $query->where(
               'organizer_id',
               $this->organizerId()
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Find
     |--------------------------------------------------------------------------
     */
     public function find(string $ulid): Organization {
          return $this->visibleOrganizations()
               ->where('ulid', $ulid)
               ->firstOrFail();
     }

     /*
     |--------------------------------------------------------------------------
     | Has Access
     |--------------------------------------------------------------------------
     */
     public function hasAccess(Organization $organization): bool {
          return $this->visibleOrganizations()
               ->whereKey($organization->id)
               ->exists();
     }

     /*
     |--------------------------------------------------------------------------
     | Show
     |--------------------------------------------------------------------------
     */
     public function show(Organization $organization): Organization {
          if (! $this->hasAccess($organization)) {
               throw new NotFoundHttpException();
          }
          
          return Organization::query()
               ->with([
                    /*
                    |--------------------------------------------------------------------------
                    | Organization
                    |--------------------------------------------------------------------------
                    */
                    'organizer',
                    'organizationType',
                    'owner',
                    'country',
                    'state',
                    'city',

                    /*
                    |--------------------------------------------------------------------------
                    | Event Participation
                    |--------------------------------------------------------------------------
                    */
                    'eventOrganizations.event',
                    'eventOrganizations.participantType',
                    'eventOrganizations.booth',

                    /*
                    |--------------------------------------------------------------------------
                    | Representatives
                    |--------------------------------------------------------------------------
                    */
                    'eventOrganizations.participants.organizationUser.user',

                    /*
                    |--------------------------------------------------------------------------
                    | Audit
                    |--------------------------------------------------------------------------
                    */
                    'creator',
                    'updater',
                    'approver',
               ])
               ->withCount([
                    'organizationUsers',
                    'eventOrganizations',
                    'primaryParticipants',
                    'secondaryParticipants',
               ])
               ->findOrFail($organization->id);
     }
}