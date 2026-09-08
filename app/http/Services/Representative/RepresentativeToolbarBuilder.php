<?php

namespace App\Services\Representative;

use App\Models\Event;
use App\Models\EventParticipant;

class RepresentativeToolbarBuilder
{
    /*
    |--------------------------------------------------------------------------
    | Toolbar
    |--------------------------------------------------------------------------
    */
    public function get(
        Event $event,
        EventParticipant $participant
    ): array {

        return collect([

            'dashboard',

            'availability',

            'matchmaking',

            'meeting_requests',

            'meetings',

            'schedule',

            'participants',

            'organizations',

            'sponsors',

            'profile',

        ])

        ->map(fn ($key) => $this->module(
            key: $key,
            event: $event,
            participant: $participant
        ))

        ->filter()

        ->values()

        ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Module
    |--------------------------------------------------------------------------
    */
    protected function module(
        string $key,
        Event $event,
        EventParticipant $participant
    ): ?array {

        return match ($key) {

            'dashboard' => [
                'key'    => 'dashboard',
                'title'  => 'Dashboard',
                'icon'   => 'ph-house',
                'route'  => route('representative.dashboard', $event),
                'active' => 'representative.dashboard',
            ],

            'availability' => [
                'key'    => 'availability',
                'title'  => 'Availability',
                'icon'   => 'ph-calendar-check',
                'route'  => route('representative.availability.index', $event),
                'active' => 'representative.availability.*',
            ],

            /*
            |--------------------------------------------------------------------------
            | Hide when networking mode doesn't support matchmaking
            |--------------------------------------------------------------------------
            */
            'matchmaking' => $event->networkingMode?->supports_matchmaking
                ? [
                    'key'    => 'matchmaking',
                    'title'  => 'Matchmaking',
                    'icon'   => 'ph-handshake',
                    'route'  => route('representative.matchmaking.index', $event),
                    'active' => 'representative.matchmaking.*',
                ]
                : null,

            'meeting_requests' => [
                'key'    => 'meeting_requests',
                'title'  => 'Requests',
                'icon'   => 'ph-envelope',
                'route'  => route('representative.meeting-requests.index', $event),
                'active' => 'representative.meeting-requests.*',
            ],

            'meetings' => [
                'key'    => 'meetings',
                'title'  => 'Meetings',
                'icon'   => 'ph-users-three',
                'route'  => route('representative.meetings.index', $event),
                'active' => 'representative.meetings.*',
            ],

            'schedule' => [
                'key'    => 'schedule',
                'title'  => 'Schedule',
                'icon'   => 'ph-calendar',
                'route'  => route('representative.schedule.index', $event),
                'active' => 'representative.schedule.*',
            ],

            'participants' => [
                'key'    => 'participants',
                'title'  => 'Participants',
                'icon'   => 'ph-users',
                'route'  => route('representative.participants.index', $event),
                'active' => 'representative.participants.*',
            ],

            'organizations' => [
                'key'    => 'organizations',
                'title'  => 'Organizations',
                'icon'   => 'ph-buildings',
                'route'  => route('representative.organizations.index', $event),
                'active' => 'representative.organizations.*',
            ],

            /*
            |--------------------------------------------------------------------------
            | Hide if event has no sponsors
            |--------------------------------------------------------------------------
            */
            'sponsors' => [
                'key'    => 'sponsors',
                'title'  => 'Sponsors',
                'icon'   => 'ph-medal',
                'route'  => route('representative.sponsors.index', $event),
                'active' => 'representative.sponsors.*',
            ],

            'profile' => [
                'key'    => 'profile',
                'title'  => 'Profile',
                'icon'   => 'ph-user-circle',
                'route'  => route('representative.profile.index', $event),
                'active' => 'representative.profile.*',
            ],

            default => null,
        };
    }
}