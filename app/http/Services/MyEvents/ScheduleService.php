<?php

namespace App\Services\MyEvents;

use Carbon\Carbon;

use Illuminate\Support\Collection;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventTimeSlot;
use App\Models\Meeting;
use App\Models\EventSession;

class ScheduleService {
     /*
     |--------------------------------------------------------------------------
     | State
     |--------------------------------------------------------------------------
     */
     protected const STATE_REGISTRATION = 'registration';
     protected const STATE_MEETING = 'meeting';
     protected const STATE_SESSION = 'session';
     protected const STATE_BREAK = 'break';
     protected const STATE_LUNCH = 'lunch';
     protected const STATE_DINNER = 'dinner';
     protected const STATE_BLOCKED = 'blocked';
     protected const STATE_FREE = 'free';

     /*
     |--------------------------------------------------------------------------
     | Workspace
     |--------------------------------------------------------------------------
     */
     public function workspace(array $workspace,): array {
          return [
               /*
               |--------------------------------------------------------------------------
               | Schedule Statistics
               |--------------------------------------------------------------------------
               */
               'scheduleStatistics' => $this->scheduleStatistics(
                    event: $workspace['event'],
                    participant: $workspace['participant'],
               ),
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Initial Month
     |--------------------------------------------------------------------------
     */
     public function initialMonth(Event $event,): Carbon {
          return EventTimeSlot::query()
               ->whereHas(
                    'schedule',
                    fn ($query) => $query->where(
                         'event_id',
                         $event->id
                    )
               )
               ->orderBy('start_at')
               ->value('start_at')
               ?->startOfMonth()
               ?? now()->startOfMonth();
     }

     /*
     |--------------------------------------------------------------------------
     | Initial Date
     |--------------------------------------------------------------------------
     */
     public function initialDate(Event $event,): ?string {
          return EventTimeSlot::query()
               ->whereHas(
                    'schedule',
                    fn ($query) => $query->where(
                         'event_id',
                         $event->id
                    )
               )
               ->orderBy('start_at')
               ->value('start_at')
               ?->toDateString();
     }

     /*
     |--------------------------------------------------------------------------
     | Calendar
     |--------------------------------------------------------------------------
     */
     public function calendar(Event $event,EventParticipant $participant,?Carbon $month = null,): array {
          /*
          |--------------------------------------------------------------------------
          | Month
          |--------------------------------------------------------------------------
          */
          $month ??= now()->startOfMonth();
          $month = $month->copy()->startOfMonth();

          /*
          |--------------------------------------------------------------------------
          | Slots
          |--------------------------------------------------------------------------
          */
          $days = $this->itemQuery(
               event: $event,
               participant: $participant,
          )
          ->whereBetween(
               'start_at',
               [
                    $month->copy()->startOfMonth(),
                    $month->copy()->endOfMonth(),
               ]
          )
          ->get()
          ->map(fn (EventTimeSlot $slot) =>
               $this->itemState(
                    slot: $slot,
                    participant: $participant,
               )
          )
          ->groupBy(fn ($item) =>
               $item['slot']->start_at->toDateString()
          );

          /*
          |--------------------------------------------------------------------------
          | Calendar
          |--------------------------------------------------------------------------
          */
          $calendar = collect();

          for ($date = $month->copy();$date->lte($month->copy()->endOfMonth());$date->addDay()) {
               $items = $days->get(
                    $date->toDateString(),
                    collect()
               );

               $statistics = $items->isNotEmpty()
                    ? $this->dayStatistics($items)
                    : null;

               $calendar->push([
                    'date' => $date->toDateString(),
                    'day' => $date->day,
                    'hasSlots' => $items->isNotEmpty(),
                    'statistics' => $statistics,
                    'indicator' => $statistics
                         ? $this->calendarIndicator($statistics)
                         : null,
               ]);
          }

          /*
          |--------------------------------------------------------------------------
          | Response
          |--------------------------------------------------------------------------
          */
          return [
               'month' => $month->format('Y-m'),
               'days' => $calendar->values()->all(),
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Timeline
     |--------------------------------------------------------------------------
     */
     public function timeline(Event $event,EventParticipant $participant,?Carbon $date = null,): Collection {
          /*
          |--------------------------------------------------------------------------
          | Date
          |--------------------------------------------------------------------------
          */
          $date ??= now();
          $start = $date->copy()->startOfDay();
          $end = $date->copy()->endOfDay();

          /*
          |--------------------------------------------------------------------------
          | Timeline
          |--------------------------------------------------------------------------
          */
          return $this->itemQuery(
               event: $event,
               participant: $participant,
          )
          ->whereBetween(
               'start_at',
               [
                    $start,
                    $end,
               ]
          )
          ->get()
          ->map(fn (EventTimeSlot $slot) =>
               $this->itemState(
                    slot: $slot,
                    participant: $participant,
               )
          )
          ->values();
     }

     /*
     |--------------------------------------------------------------------------
     | Item
     |--------------------------------------------------------------------------
     */
     public function item(EventTimeSlot $slot,EventParticipant $participant,): array {
          /*
          |--------------------------------------------------------------------------
          | Relationships
          |--------------------------------------------------------------------------
          */
          $slot->loadMissing([
               'schedule',
               'session',
               'meetings' => function ($query) use ($participant) {
                    $query->where(function ($query) use ($participant) {
                         $query
                              ->where(
                                   'sender_participant_id',
                                   $participant->id
                              )
                              ->orWhere(
                                   'receiver_participant_id',
                                   $participant->id
                              );
                    });
               },
          ]);

          /*
          |--------------------------------------------------------------------------
          | State
          |--------------------------------------------------------------------------
          */
          return $this->itemState(
               slot: $slot,
               participant: $participant,
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Schedule Statistics
     |--------------------------------------------------------------------------
     */
     public function scheduleStatistics(Event $event,EventParticipant $participant,): array {
          /*
          |--------------------------------------------------------------------------
          | Initial Date
          |--------------------------------------------------------------------------
          */
          $date = $this->initialDate($event);

          /*
          |--------------------------------------------------------------------------
          | Timeline
          |--------------------------------------------------------------------------
          */
          $items = $this->timeline(
               event: $event,
               participant: $participant,
               date: $date
                    ? Carbon::parse($date)
                    : null,
          );

          /*
          |--------------------------------------------------------------------------
          | Statistics
          |--------------------------------------------------------------------------
          */
          return $this->dayStatistics($items);
     }

     /*
     |--------------------------------------------------------------------------
     | Day Statistics
     |--------------------------------------------------------------------------
     */
     protected function dayStatistics(Collection $items,): array {
          /*
          |--------------------------------------------------------------------------
          | Counts
          |--------------------------------------------------------------------------
          */
          $total = $items->count();
          $meetings = $items
               ->where('state', self::STATE_MEETING)
               ->count();

          $sessions = $items
               ->where('state', self::STATE_SESSION)
               ->count();

          $blocked = $items
               ->where('state', self::STATE_BLOCKED)
               ->count();

          $free = $items
               ->where('state', self::STATE_FREE)
               ->count();

          /*
          |--------------------------------------------------------------------------
          | Busy %
          |--------------------------------------------------------------------------
          */
          $busy = $total > 0
               ? round(
                    (
                         ($meetings + $sessions)
                         / $total
                    ) * 100
               )
               : 0;

          /*
          |--------------------------------------------------------------------------
          | Return
          |--------------------------------------------------------------------------
          */
          return [
               'total' => $total,
               'meetings' => $meetings,
               'sessions' => $sessions,
               'blocked' => $blocked,
               'free' => $free,
               'busy_percentage' => $busy,
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Calendar Indicator
     |--------------------------------------------------------------------------
     */
     protected function calendarIndicator(array $statistics,): array {
          /*
          |--------------------------------------------------------------------------
          | Meeting
          |--------------------------------------------------------------------------
          */
          if ($statistics['meetings'] > 0) {
               return [
                    'icon' => 'ph-handshake',
                    'color' => 'primary',
               ];
          }

          /*
          |--------------------------------------------------------------------------
          | Session
          |--------------------------------------------------------------------------
          */
          if ($statistics['sessions'] > 0) {
               return [
                    'icon' => 'ph-presentation',
                    'color' => 'success',
               ];
          }

          /*
          |--------------------------------------------------------------------------
          | Blocked
          |--------------------------------------------------------------------------
          */
          if ($statistics['blocked'] > 0) {
               return [
                    'icon' => 'ph-lock',
                    'color' => 'secondary',
               ];
          }

          /*
          |--------------------------------------------------------------------------
          | Free
          |--------------------------------------------------------------------------
          */
          return [
               'icon' => 'ph-clock',
               'color' => 'light',
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Item Query
     |--------------------------------------------------------------------------
     */
     protected function itemQuery(Event $event,EventParticipant $participant,) {
          return EventTimeSlot::query()
               /*
               |--------------------------------------------------------------------------
               | Event
               |--------------------------------------------------------------------------
               */
               ->whereHas(
                    'schedule',
                    fn ($query) => $query->where(
                         'event_id',
                         $event->id
                    )
               )

               /*
               |--------------------------------------------------------------------------
               | Relationships
               |--------------------------------------------------------------------------
               */
               ->with([
                    /*
                    |--------------------------------------------------------------------------
                    | Schedule
                    |--------------------------------------------------------------------------
                    */
                    'schedule',

                    /*
                    |--------------------------------------------------------------------------
                    | Session
                    |--------------------------------------------------------------------------
                    */
                    'session',
                    //'session.track',
                    //'session.room',
                    //'session.speakers',

                    /*
                    |--------------------------------------------------------------------------
                    | Meetings
                    |--------------------------------------------------------------------------
                    */
                    'meetings' => function ($query) use ($participant) {
                         $query->where(function ($query) use ($participant) {
                              $query
                                   ->where(
                                        'sender_participant_id',
                                        $participant->id
                                   )
                                   ->orWhere(
                                        'receiver_participant_id',
                                        $participant->id
                                   );
                         });
                    },
               ])

               /*
               |--------------------------------------------------------------------------
               | Order
               |--------------------------------------------------------------------------
               */
               ->orderBy('start_at');
     }

     /*
     |--------------------------------------------------------------------------
     | Item State
     |--------------------------------------------------------------------------
     */
     protected function itemState(EventTimeSlot $slot,EventParticipant $participant,): array {
          $meeting = $slot->meetings->first();
          $session = $slot->session;

          /*
          |--------------------------------------------------------------------------
          | Meeting
          |--------------------------------------------------------------------------
          */
          if ($meeting) {
               return $this->state(
                    slot: $slot,
                    state: self::STATE_MEETING,
                    meeting: $meeting,
                    session: null,
                    title: $meeting->title ?? 'Scheduled Meeting',
                    icon: 'ph-handshake',
                    color: 'primary',
                    label: 'Meeting',
                    actions: $this->actions(
                         view: true,
                         meeting: true,
                    ),
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Session
          |--------------------------------------------------------------------------
          */
          if ($session) {
               return $this->state(
                    slot: $slot,
                    state: self::STATE_SESSION,
                    meeting: null,
                    session: $session,
                    title: $session->title,
                    icon: 'ph-presentation',
                    color: 'success',
                    label: 'Session',
                    actions: $this->actions(
                         view: true,
                         session: true,
                    ),
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Blocked
          |--------------------------------------------------------------------------
          */
          if (! $slot->is_bookable) {
               return $this->state(
                    slot: $slot,
                    state: self::STATE_BLOCKED,
                    meeting: null,
                    session: null,
                    title: 'Blocked Slot',
                    icon: 'ph-lock',
                    color: 'secondary',
                    label: 'Blocked',
                    actions: $this->actions(),
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Free
          |--------------------------------------------------------------------------
          */
          return $this->state(
               slot: $slot,
               state: self::STATE_FREE,
               meeting: null,
               session: null,
               title: 'Free Time',
               icon: 'ph-clock',
               color: 'light',
               label: 'Free',
               actions: $this->actions(),
          );
     }

     /*
     |--------------------------------------------------------------------------
     | State
     |--------------------------------------------------------------------------
     */
     protected function state(
          EventTimeSlot $slot,
          string $state,
          ?Meeting $meeting,
          ?EventSession $session,
          string $title,
          string $icon,
          string $color,
          string $label,
          array $actions,
     ): array {
          return [
               /*
               |--------------------------------------------------------------------------
               | Slot
               |--------------------------------------------------------------------------
               */
               'slot' => $slot,

               /*
               |--------------------------------------------------------------------------
               | Display
               |--------------------------------------------------------------------------
               */
               'start_time' => $slot->start_at->format('h:i A'),
               'end_time' => $slot->end_at->format('h:i A'),
               'date' => $slot->start_at->format('d M Y'),

               /*
               |--------------------------------------------------------------------------
               | State
               |--------------------------------------------------------------------------
               */
               'state' => $state,
               'title' => $title,
               'label' => $label,
               'icon' => $icon,
               'color' => $color,

               /*
               |--------------------------------------------------------------------------
               | Relationships
               |--------------------------------------------------------------------------
               */
               'meeting' => $meeting,
               'session' => $session,

               /*
               |--------------------------------------------------------------------------
               | Actions
               |--------------------------------------------------------------------------
               */
               'actions' => $actions,
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Actions
     |--------------------------------------------------------------------------
     */
     protected function actions(bool $view = false,bool $meeting = false,bool $session = false,bool $join = false,): array {
          return [
               'view' => $view,
               'meeting' => $meeting,
               'session' => $session,
               'join' => $join,
          ];
     }
}