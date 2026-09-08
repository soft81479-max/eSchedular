<?php

namespace App\Services\Shared;

use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Shared\OrganizerService;

class EventAccessService {
    public function __construct(
            protected OrganizerService $organizerService,
        ) {
    }
    /*
    |--------------------------------------------------------------------------
    | Query
    |--------------------------------------------------------------------------
    */
    public function query(): Builder {
        $user = auth()->user();

        $query = $this->baseQuery();

        /*
        |--------------------------------------------------------------------------
        | Super Administrator / Administrator
        |--------------------------------------------------------------------------
        */
        if ($user->hasRole([
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

        if ($user->hasRole('organizer')) {
            return $this->applyOrganizerScope($query, $user->id);
        }

        /*
        |--------------------------------------------------------------------------
        | Organization
        |--------------------------------------------------------------------------
        */

        if ($user->hasRole('organization')) {
            return $this->applyOrganizationScope($query, $user->id);
        }

        /*
        |--------------------------------------------------------------------------
        | Representative
        |--------------------------------------------------------------------------
        */

        if ($user->hasRole('representative')) {
            return $this->applyRepresentativeScope($query, $user->id);
        }

        /*
        |--------------------------------------------------------------------------
        | No Access
        |--------------------------------------------------------------------------
        */

        return $this->applyNoAccessScope($query);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessible Events
    |--------------------------------------------------------------------------
    */
    public function accessibleEvents() {
        return $this->query();
    }

    /*
    |--------------------------------------------------------------------------
    | Can Access
    |--------------------------------------------------------------------------
    */
    public function canAccess(Event $event): bool {
        return $this->query()
            ->whereKey($event->id)
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Base Query
    |--------------------------------------------------------------------------
    */
    private function baseQuery(): Builder {
        return Event::query()
            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */
            ->with([
                'organizer:id,name',
                'eventType:id,name',
                'networkingMode:id,name,slug',
                'matchingStrategy:id,name',
                'creator:id,name',
            ])

            /*
            |--------------------------------------------------------------------------
            | Counts
            |--------------------------------------------------------------------------
            */
            ->withCount([
                'participantTypes',
                'organizations',
                'participants',
                'booths',
                'schedules',
            ])

            /*
            |--------------------------------------------------------------------------
            | Active Events
            |--------------------------------------------------------------------------
            */
            ->whereIn('status', Event::activeStatuses())

            /*
            |--------------------------------------------------------------------------
            | Latest First
            |--------------------------------------------------------------------------
            */
            ->latest();
    }

    /*
    |--------------------------------------------------------------------------
    | Organizer Scope
    |--------------------------------------------------------------------------
    */
    private function applyOrganizerScope(Builder $query, int $userId): Builder {
        return $query->whereExists(function ($subQuery) use ($userId) {
            $subQuery
                ->selectRaw(1)
                ->from('organizer_users')
                ->whereColumn(
                    'organizer_users.organizer_id',
                    'events.organizer_id'
                )
                ->where(
                    'organizer_users.user_id',
                    $userId
                );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Organization Scope
    |--------------------------------------------------------------------------
    */
    private function applyOrganizationScope(Builder $query, int $userId): Builder {
        return $query->whereExists(function ($subQuery) use ($userId) {
            $subQuery
                ->selectRaw(1)
                ->from('organization_users')
                ->join(
                    'event_organizations',
                    'event_organizations.organization_id',
                    '=',
                    'organization_users.organization_id'
                )
                ->whereColumn(
                    'event_organizations.event_id',
                    'events.id'
                )
                ->where(
                    'organization_users.user_id',
                    $userId
                );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Representative Scope
    |--------------------------------------------------------------------------
    */
    private function applyRepresentativeScope(Builder $query, int $userId): Builder {
        return $query->whereExists(function ($subQuery) use ($userId) {
            $subQuery
                ->selectRaw(1)
                ->from('organization_users')
                ->join(
                    'event_participants',
                    'event_participants.organization_user_id',
                    '=',
                    'organization_users.id'
                )
                ->join(
                    'event_organizations',
                    'event_organizations.id',
                    '=',
                    'event_participants.event_organization_id'
                )
                ->whereColumn(
                    'event_organizations.event_id',
                    'events.id'
                )
                ->where(
                    'organization_users.user_id',
                    $userId
                );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | No Access Scope
    |--------------------------------------------------------------------------
    */
    private function applyNoAccessScope(Builder $query): Builder {
        return $query->whereRaw('1 = 0');
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */
    public function show(Event $event): Event {
        $event = $this->query()
            ->with([
                /*
                |--------------------------------------------------------------------------
                | Event
                |--------------------------------------------------------------------------
                */
                'organizer',
                'eventType',
                'networkingMode',
                'matchingStrategy',

                /*
                |--------------------------------------------------------------------------
                | Marketplace
                |--------------------------------------------------------------------------
                */
                'participantTypes',
                'matchRules.sourceParticipantType',
                'matchRules.targetParticipantType',

                /*
                |--------------------------------------------------------------------------
                | Organizations
                |--------------------------------------------------------------------------
                */
                'organizations.organization',
                'organizations.participantType',
                'organizations.booth',
                'organizations.participants.organizationUser.user',

                /*
                |--------------------------------------------------------------------------
                | Event Structure
                |--------------------------------------------------------------------------
                */
                'schedules',
                'tracks',
                'sessions',
                'timeSlots',

                /*
                |--------------------------------------------------------------------------
                | Audit
                |--------------------------------------------------------------------------
                */
                'creator',
                'updater',
            ])
            ->withCount([
                'participantTypes',
                'organizations',
                'participants',
                'schedules',
                'tracks',
                'sessions',
                'timeSlots',
                'booths',
            ])
            ->findOrFail($event->id);

        /*
        |--------------------------------------------------------------------------
        | Organizer Statistics
        |--------------------------------------------------------------------------
        */
        $event->organizer->statistics = $this->organizerService->statistics(
            $event->organizer
        );

        return $event;
    }
}