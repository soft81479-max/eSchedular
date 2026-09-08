<?php

namespace App\Services\MyEvents;

use Carbon\Carbon;

use Illuminate\Support\Collection;

use App\Models\Event;
use App\Models\Meeting;
use App\Models\EventTimeSlot;
use App\Models\EventParticipant;
use App\Models\ParticipantAvailability;

class AvailabilityService {
      /*
     |--------------------------------------------------------------------------
     | Slot States
     |--------------------------------------------------------------------------
     */
     protected const STATE_MEETING = 'meeting';
     protected const STATE_BLOCKED = 'blocked';

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
     | Workspace
     |--------------------------------------------------------------------------
     */
     public function workspace(array $workspace): array {
          $event = $workspace['event'];
          $participant = $workspace['participant'];
          return [
               'availabilityStatistics' => $this->availabilityStatistics(
                    $event,
                    $participant
               ),
               'calendar' => $this->calendar(
                    $event,
                    $participant
               ),
               'slots' => $this->slots(
                    $event,
                    $participant
               ),
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Availability Statistics
     |--------------------------------------------------------------------------
     */
     public function availabilityStatistics(Event $event,EventParticipant $participant,): array {
          /*
          |--------------------------------------------------------------------------
          | Slots
          |--------------------------------------------------------------------------
          */
          $slots = $this->slots(
               event: $event,
               participant: $participant
          );

          /*
          |--------------------------------------------------------------------------
          | Counts
          |--------------------------------------------------------------------------
          */
          $total = $slots->count();

          $blocked = $slots
               ->where('state', self::STATE_BLOCKED)
               ->count();

          $bookable = $total - $blocked;

          $meetings = $slots
               ->where('state', self::STATE_MEETING)
               ->count();

          $preferred = $slots
               ->where('state', ParticipantAvailability::STATUS_PREFERRED)
               ->count();

          $available = $slots
               ->where('state', ParticipantAvailability::STATUS_AVAILABLE)
               ->count();

          $unavailable = $slots
               ->where('state', ParticipantAvailability::STATUS_UNAVAILABLE)
               ->count();

          /*
          |--------------------------------------------------------------------------
          | Availability %
          |--------------------------------------------------------------------------
          */
          $availability = $bookable > 0
               ? round(
                    (
                         $available
                         + $preferred
                         + $meetings
                    ) / $bookable * 100
               )
               : 0;

          /*
          |--------------------------------------------------------------------------
          | Statistics
          |--------------------------------------------------------------------------
          */
          return [
               'availability' => $availability,
               'total' => $total,
               'meetings' => $meetings,
               'preferred' => $preferred,
               'available' => $available,
               'unavailable' => $unavailable,
               'blocked'      => $blocked,
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Calendar
     |--------------------------------------------------------------------------
     */
     public function calendar(Event $event,EventParticipant $participant,?Carbon $month = null,): array {
          $month ??= now();
          $month = $month->copy()->startOfMonth();

          /*
          |--------------------------------------------------------------------------
          | Slot Statistics
          |--------------------------------------------------------------------------
          */
          $days = $this->slotQuery(
               $event,
               $participant
          )
          ->whereBetween(
               'start_at',
               [
                    $month->copy()->startOfMonth(),
                    $month->copy()->endOfMonth(),
               ]
          )
          ->orderBy('start_at')
          ->get()
          ->map(fn (EventTimeSlot $slot) =>
               $this->slotState(
                    $slot,
                    $participant
               )
          )
          ->groupBy(fn ($slot) =>
               $slot['slot']->start_at->toDateString()
          );

          /*
          |--------------------------------------------------------------------------
          | Calendar
          |--------------------------------------------------------------------------
          */
          $calendar = collect();

          for ($date = $month->copy(); $date->lte($month->copy()->endOfMonth()); $date->addDay()) {

               $slots = $days->get(
                    $date->toDateString(),
                    collect()
               );

               $statistics = $slots->isNotEmpty()
                    ? $this->dayStatistics($slots)
                    : null;

               $calendar->push([
                    'date' => $date->toDateString(),
                    'day' => $date->day,
                    'hasSlots' => $slots->isNotEmpty(),
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
               'days' => $calendar,
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Slots
     |--------------------------------------------------------------------------
     */
     public function slots(Event $event,EventParticipant $participant,?Carbon $date = null,): Collection {
          $query = $this->slotQuery(
               $event,
               $participant
          );

          if ($date) {

               $query->whereDate(
                    'start_at',
                    $date
               );

          }

          return $query
               ->orderBy('start_at')
               ->get()
               ->map(fn (EventTimeSlot $slot) =>
                    $this->slotState(
                         $slot,
                         $participant
                    )
               );
     }

     /*
     |--------------------------------------------------------------------------
     | Slot Query
     |--------------------------------------------------------------------------
     */
     protected function slotQuery(Event $event,EventParticipant $participant,) {
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
                    'availabilities' => function ($query) use ($participant) {
                         $query->where(
                              'event_participant_id',
                              $participant->id
                         );
                    },
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
     }

     /*
     |--------------------------------------------------------------------------
     | Slot
     |--------------------------------------------------------------------------
     */
     public function slot(EventTimeSlot $slot,EventParticipant $participant,): array {
          /*
          |--------------------------------------------------------------------------
          | Relationships
          |--------------------------------------------------------------------------
          */
          $slot->load([
               'availabilities' => function ($query) use ($participant) {
                    $query->where(
                         'event_participant_id',
                         $participant->id
                    );
               },
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
          | Slot State
          |--------------------------------------------------------------------------
          */
          return $this->slotState(
               $slot,
               $participant
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Update
     |--------------------------------------------------------------------------
     */
     public function update(EventTimeSlot $slot,EventParticipant $participant,array $attributes,): array {
          /*
          |--------------------------------------------------------------------------
          | Validate
          |--------------------------------------------------------------------------
          */
          abort_unless(
               isset($attributes['status'])
               && in_array(
                    $attributes['status'],
                    ParticipantAvailability::statuses()
               ),
               422,
               'Invalid availability status.'
          );

          /*
          |--------------------------------------------------------------------------
          | Current State
          |--------------------------------------------------------------------------
          */
          $state = $this->slot(
               slot: $slot,
               participant: $participant
          );

          /*
          |--------------------------------------------------------------------------
          | Editable
          |--------------------------------------------------------------------------
          */
          abort_unless(
               $state['actions']['edit'],
               422,
               $state['lock_message']
          );

          /*
          |--------------------------------------------------------------------------
          | Save
          |--------------------------------------------------------------------------
          */
          ParticipantAvailability::updateOrCreate(
               [
                    'event_participant_id' => $participant->id,
                    'event_time_slot_id'   => $slot->id,
               ],
               [
                    'status' => $attributes['status'],
               ]
          );

          /*
          |--------------------------------------------------------------------------
          | Refresh
          |--------------------------------------------------------------------------
          */
          return $this->slot(
               slot: $slot,
               participant: $participant
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Slot State
     |--------------------------------------------------------------------------
     */
     protected function slotState(EventTimeSlot $slot,EventParticipant $participant,): array {
          $meeting = $slot->meetings->first();
          $availability = $slot->availabilities->first();

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
                    availability: $availability,
                    icon: 'ph-lock',
                    color: 'secondary',
                    label: 'Blocked',
                    actions: $this->actions(),
                    lockReason: 'not_bookable',
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Accepted / Completed
          |--------------------------------------------------------------------------
          */
          if (
               $meeting &&
               in_array(
                    $meeting->status,
                    [
                         Meeting::STATUS_ACCEPTED,
                         Meeting::STATUS_COMPLETED,
                    ]
               )
          ) {
               return $this->state(
                    slot: $slot,
                    state: self::STATE_MEETING,
                    meeting: $meeting,
                    availability: $availability,
                    icon: 'ph-handshake',
                    color: 'primary',
                    label: 'Meeting',
                    actions: $this->actions(),
                    lockReason: 'meeting_locked',
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Pending Meeting
          |--------------------------------------------------------------------------
          */
          if ($meeting) {
               return $this->state(
                    slot: $slot,
                    state: self::STATE_MEETING,
                    meeting: $meeting,
                    availability: $availability,
                    icon: 'ph-handshake',
                    color: 'primary',
                    label: 'Meeting',
                    actions: $this->actions(
                         cancel: true,
                         complete: true,
                         noShow: true,
                    ),
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Preferred
          |--------------------------------------------------------------------------
          */
          if ($availability?->isPreferred()) {
               return $this->state(
                    slot: $slot,
                    state: ParticipantAvailability::STATUS_PREFERRED,
                    meeting: null,
                    availability: $availability,
                    icon: 'ph-star',
                    color: 'warning',
                    label: 'Preferred',
                    actions: $this->actions(
                         edit: true,
                         meeting: true,
                    ),
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Available
          |--------------------------------------------------------------------------
          */
          if ($availability?->isAvailable()) {
               return $this->state(
                    slot: $slot,
                    state: ParticipantAvailability::STATUS_AVAILABLE,
                    meeting: null,
                    availability: $availability,
                    icon: 'ph-check-circle',
                    color: 'success',
                    label: 'Available',
                    actions: $this->actions(
                         edit: true,
                         meeting: true,
                    ),
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Unavailable
          |--------------------------------------------------------------------------
          */
          return $this->state(
               slot: $slot,
               state: ParticipantAvailability::STATUS_UNAVAILABLE,
               meeting: null,
               availability: $availability,
               icon: 'ph-prohibit',
               color: 'danger',
               label: 'Unavailable',
               actions: $this->actions(
                    edit: true,
               ),
          );
     }

     /*
     |--------------------------------------------------------------------------
     | State
     |--------------------------------------------------------------------------
     */
     protected function state(EventTimeSlot $slot,string $state,?Meeting $meeting,?ParticipantAvailability $availability,string $icon,string $color,string $label,array $actions,?string $lockReason = null,): array {
          return [
               'slot' => $slot,

               'start_time'   => $slot->start_at->format('h:i A'),
               'end_time'     => $slot->end_at->format('h:i A'),
               'date'         => $slot->start_at->format('d M Y'),

               'state'        => $state,
               'meeting'      => $meeting,
               'availability' => $availability,
               'icon'         => $icon,
               'color'        => $color,
               'label'        => $label,
               'actions'      => $actions,
               'lock_reason'  => $lockReason,
               'lock_message' => $lockReason
                    ? $this->lockMessage($lockReason)
                    : null,
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Actions
     |--------------------------------------------------------------------------
     */
     protected function actions(bool $edit = false,bool $meeting = false,bool $cancel = false,bool $complete = false,bool $noShow = false,): array {
          return [
               'edit'      => $edit,
               'meeting'   => $meeting,
               'cancel'    => $cancel,
               'complete'  => $complete,
               'no_show'   => $noShow,
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Lock Message
     |--------------------------------------------------------------------------
     */
     protected function lockMessage(?string $reason): string {
          return match ($reason) {
               'not_bookable' => 'This slot has been blocked by the organizer.',
               'meeting_locked' => 'This meeting has already been accepted or completed and can no longer be modified.',
               default => 'This slot cannot be modified.',
          };
     }

     /*
     |--------------------------------------------------------------------------
     | Day Statistics
     |--------------------------------------------------------------------------
     */
     protected function dayStatistics(Collection $slots,): array {
          $total = $slots->count();
          $meetings = $slots
               ->where('state', self::STATE_MEETING)
               ->count();

          $preferred = $slots
               ->where(
                    'state',
                    ParticipantAvailability::STATUS_PREFERRED
               )
               ->count();

          $available = $slots
               ->where(
                    'state',
                    ParticipantAvailability::STATUS_AVAILABLE
               )
               ->count();

          $unavailable = $slots
               ->where(
                    'state',
                    ParticipantAvailability::STATUS_UNAVAILABLE
               )
               ->count();

          $percentage = $total > 0
               ? round(
                    (
                         ($meetings + $preferred + $available)
                         / $total
                    ) * 100
               )
               : 0;

          return [
               'total' => $total,
               'meetings' => $meetings,
               'preferred' => $preferred,
               'available' => $available,
               'unavailable' => $unavailable,
               'percentage' => $percentage,
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Calendar Indicator
     |--------------------------------------------------------------------------
     */
     protected function calendarIndicator(array $statistics,): array {
          $percentage = $statistics['percentage'];
          if ($percentage >= 80) {
               return [
                    'color' => 'success',
                    'icon' => 'ph-check-circle',
               ];
          }

          if ($percentage >= 50) {
               return [
                    'color' => 'warning',
                    'icon' => 'ph-warning-circle',
               ];
          }

          return [
               'color' => 'danger',
               'icon' => 'ph-x-circle',
          ];
     }
}