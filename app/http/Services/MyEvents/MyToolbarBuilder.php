<?php

namespace App\Services\MyEvents;

use App\Models\Event;

class MyToolbarBuilder {
     /*
     |--------------------------------------------------------------------------
     | Toolbar
     |--------------------------------------------------------------------------
     */
     public function get(Event $event): array {
          return [
               [
                    'key'        => 'overview',
                    'title'      => 'Overview',
                    'icon'       => 'ph-house',
                    'route_name' => 'my-events.overview.index',
               ],

               [
                    'key'        => 'availability',
                    'title'      => 'Availability',
                    'icon'       => 'ph-calendar-check',
                    'route_name' => 'my-events.availability.index',
               ],

               [
                    'key'        => 'schedule',
                    'title'      => 'Schedule',
                    'icon'       => 'ph-calendar',
                    'route_name' => 'my-events.schedule.index',
               ],

               [
                    'key'        => 'participants',
                    'title'      => 'Participants',
                    'icon'       => 'ph-identification-card',
                    'route_name' => 'my-events.participants.index',
               ],

               [
                    'key'        => 'organizations',
                    'title'      => 'Organizations',
                    'icon'       => 'ph-buildings',
                    'route_name' => 'my-events.organizations.index',
               ],

               [
                    'key'        => 'matchmaking',
                    'title'      => 'Matchmaking',
                    'icon'       => 'ph-users-three',
                    'route_name' => 'my-events.matchmaking.index',
               ],

               [
                    'key'        => 'meeting_requests',
                    'title'      => 'Requests',
                    'icon'       => 'ph-envelope-simple',
                    'route_name' => 'my-events.meeting-requests.index',
               ],

               [
                    'key'        => 'meetings',
                    'title'      => 'Meetings',
                    'icon'       => 'ph-handshake',
                    'route_name' => 'my-events.meetings.index',
               ],
          ];
     }
}