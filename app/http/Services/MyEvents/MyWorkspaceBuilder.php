<?php

namespace App\Services\MyEvents;

use App\Models\Event;
use Illuminate\Support\Str;

class MyWorkspaceBuilderX {
     /*
     |--------------------------------------------------------------------------
     | Workspace
     |--------------------------------------------------------------------------
     */
     public function get(Event $event): array {
          $method = Str::of($event->eventType->slug)
               ->replace(['-', '_'], ' ')
               ->camel()
               ->toString();

          if (! method_exists($this, $method)) {
               return $this->default();
          }

          return $this->{$method}();
     }

     /*
     |--------------------------------------------------------------------------
     | Workshop
     |--------------------------------------------------------------------------
     */
     protected function workshop(): array {
          return [
               $this->module('overview'),
               $this->module('availability'),
               $this->module('matchmaking'),
               $this->module('meeting_requests'),
               $this->module('meetings'),
               $this->module('schedule'),
               $this->module('participants'),
               $this->module('organizations'),
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Conference
     |--------------------------------------------------------------------------
     */
     protected function conference(): array {
          return [
               $this->module('overview'),
               $this->module('meeting_requests'),
               $this->module('meetings'),
               $this->module('schedule'),
               $this->module('participants'),
               $this->module('organizations'),
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Seminar
     |--------------------------------------------------------------------------
     */
     protected function seminar(): array {
          return [
               $this->module('overview'),
               $this->module('meeting_requests'),
               $this->module('meetings'),
               $this->module('schedule'),
               $this->module('participants'),
               $this->module('organizations'),
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Expo
     |--------------------------------------------------------------------------
     */
     protected function expo(): array {
          return [
               $this->module('overview'),
               $this->module('meeting_requests'),
               $this->module('meetings'),
               $this->module('participants'),
               $this->module('organizations'),
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Default
     |--------------------------------------------------------------------------
     */
     protected function default(): array {
          return [
               $this->module('overview'),
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Module
     |--------------------------------------------------------------------------
     */
     protected function module(string $key): array {
          return match ($key) {
               'overview' => [
                    'key'        => 'overview',
                    'title'      => 'Overview',
                    'icon'       => 'ph-house',
                    'route_name' => 'my-events.overview.index',
               ],

               'availability' => [
                    'key'        => 'availability',
                    'title'      => 'Availability',
                    'icon'       => 'ph-calendar-check',
                    'route_name' => 'my-events.availability.index',
               ],

               'matchmaking' => [
                    'key'        => 'matchmaking',
                    'title'      => 'Matchmaking',
                    'icon'       => 'ph-users-three',
                    'route_name' => 'my-events.matchmaking.index',
               ],

               'meeting_requests' => [
                    'key'        => 'meeting_requests',
                    'title'      => 'Requests',
                    'icon'       => 'ph-envelope-simple',
                    'route_name' => 'my-events.meeting-requests.index',
               ],

               'meetings' => [
                    'key'        => 'meetings',
                    'title'      => 'Meetings',
                    'icon'       => 'ph-handshake',
                    'route_name' => 'my-events.meetings.index',
               ],

               'schedule' => [
                    'key'        => 'schedule',
                    'title'      => 'Schedule',
                    'icon'       => 'ph-calendar',
                    'route_name' => 'my-events.schedule.index',
               ],

               'participants' => [
                    'key'        => 'participants',
                    'title'      => 'Participants',
                    'icon'       => 'ph-users',
                    'route_name' => 'my-events.participants.index',
               ],

               'organizations' => [
                    'key'        => 'organizations',
                    'title'      => 'Organizations',
                    'icon'       => 'ph-buildings',
                    'route_name' => 'my-events.organizations.index',
               ],
               default => [],
          };
     }
}