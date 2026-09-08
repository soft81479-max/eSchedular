<?php

namespace App\Http\Controllers\EventManagement\Workspace;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use Carbon\Carbon;

use App\Models\Event;
use App\Models\EventSchedule;
use App\Models\EventTimeSlot;
use App\Models\MeetingRequest;

class TimeSlotController extends WorkspaceController {
     public function createGenerate(Event $event,EventSchedule $schedule) {
          if (! auth()->user()->canPermission('schedules.view')) {
               abort(404);
          }

          abort_if($schedule->event_id !== $event->id,
               404
          );

          return view(
               'event_management.workspace.schedules.partials.generate-slots-form',
               $this->workspace($event),
               [
                    'schedule' => $schedule,
                    'defaultSlotDuration' => $event->default_slot_duration,
                    'slotDurations'       => EventTimeSlot::durations(),
               ]
          );
     }

     public function storeGenerate(Request $request, Event $event, EventSchedule $schedule) {
          abort_if(
               $schedule->event_id !== $event->id,
               404
          );

          $validator = Validator::make($request->all(), [
               'fixed_slots' => ['required', 'array', 'min:1'],
               'fixed_slots.*.start' => ['required', 'date_format:H:i'],
               'fixed_slots.*.end' => [
                    'required',
                    'date_format:H:i',
               ],

               'fixed_slots.*.title' => [
                    'nullable',
                    'string',
                    'max:255',
               ],

               'fixed_slots.*.slot_type' => [
                    'required',
                    Rule::in(EventTimeSlot::types()),
               ],

          ]);

          /*
          |--------------------------------------------------------------------------
          | Validate Timeline
          |--------------------------------------------------------------------------
          */

          $validator->after(function ($validator) use ($request, $schedule) {
               $parsedSlots = [];
               foreach ($request->fixed_slots ?? [] as $index => $slot) {
                    if (empty($slot['start']) || empty($slot['end'])) {
                         continue;
                    }

                    $startAt = Carbon::parse(
                         $schedule->schedule_date->format('Y-m-d') . ' ' . $slot['start']
                    );

                    $endAt = Carbon::parse(
                         $schedule->schedule_date->format('Y-m-d') . ' ' . $slot['end']
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | End must be after Start
                    |--------------------------------------------------------------------------
                    */
                    if ($endAt <= $startAt) {
                         $validator->errors()->add(
                              "fixed_slots.$index.end",
                              'End time must be after start time.'
                         );

                         continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Prevent overlaps inside submitted rows
                    |--------------------------------------------------------------------------
                    */
                    foreach ($parsedSlots as $existingSlot) {
                         if ($startAt < $existingSlot['end'] && $endAt > $existingSlot['start']) {
                              $validator->errors()->add(
                              "fixed_slots.$index.start",
                              'This slot overlaps another generated slot.'
                              );
                         }
                    }

                    $parsedSlots[] = [
                         'start' => $startAt,
                         'end' => $endAt,
                         'index' => $index,
                    ];
               }

          });

          if ($validator->fails()) {
               return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
               ], 422);
          }

          DB::beginTransaction();

          try {
               $created = 0;
               $capacity = (int) (
                    $event->default_slot_capacity ?? 1
               );

               $slotMode = match ($event->networking_mode) {
                    'networking' => EventTimeSlot::MODE_NETWORKING,
                    'presentation' => EventTimeSlot::MODE_PRESENTATION,
                    'private' => EventTimeSlot::MODE_PRIVATE,
                    default => EventTimeSlot::MODE_FREE,
               };

               foreach ($request->fixed_slots as $slot) {
                    $startAt = Carbon::parse(
                         $schedule->schedule_date->format('Y-m-d') . ' ' . $slot['start']
                    );
                    $endAt = Carbon::parse(
                         $schedule->schedule_date->format('Y-m-d') . ' ' . $slot['end']
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Collision Detection
                    |--------------------------------------------------------------------------
                    */
                    $overlaps = EventTimeSlot::query()
                         ->where(
                              'event_schedule_id',
                              $schedule->id
                         )

                         ->where(
                              'start_at',
                              '<',
                              $endAt
                         )

                         ->where(
                              'end_at',
                              '>',
                              $startAt
                         )
                         ->exists();

                    if ($overlaps) {
                         continue;
                    }

                    $slotType = $slot['slot_type'];
                    $isBookable = in_array(
                         $slotType,
                         [
                              EventTimeSlot::TYPE_MEETING,
                              EventTimeSlot::TYPE_SESSION,
                         ]
                    );

                    EventTimeSlot::create([
                         'event_schedule_id' => $schedule->id,
                         'start_at' => $startAt,
                         'end_at' => $endAt,
                         'capacity' => $capacity,
                         'type' => $slotType,
                         'mode' => $slotMode,
                         'title' => $slot['title'] ?? null,
                         'description' => null,
                         'color' => null,
                         'is_bookable' => $isBookable,
                         'visibility' => EventTimeSlot::VISIBILITY_PUBLIC,
                         'sort_order' => ++$created,
                         'created_by' => auth()->id(),
                    ]);
                    $created++;
               }

               DB::commit();
               return response()->json([
                    'success' => true,
                    'message' => "{$created} time slots generated successfully.",
               ]);
          } catch (\Throwable $e) {
               DB::rollBack();
               report($e);
               return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate time slots.',
               ], 500);
          }
     }

     public function editGenerate(Event $event, EventSchedule $schedule) {
          abort_if(
               $schedule->event_id !== $event->id,
               404
          );

          $schedule->load([
               'timeSlots' => fn ($query) => $query->orderBy('start_at')
          ]);
          $slots = $schedule->timeSlots->values();

          /*
          |--------------------------------------------------------------------------
          | Detect Slot Duration
          |--------------------------------------------------------------------------
          */
          $slotDuration = $slots->isNotEmpty()
               ? $slots->first()->duration
               : $event->eventType->default_slot_duration;

          /*
          |--------------------------------------------------------------------------
          | Detect Slot Gap
          |--------------------------------------------------------------------------
          */
          $slotGap = 0;

          if ($slots->count() > 1) {
               $slotGap = abs(
                    $slots[1]
                         ->start_at
                         ->diffInMinutes(
                              $slots[0]->end_at,
                              false
                         )
               );
          }

          return view(
               'event_management.workspace.schedules.partials.update-slots-form',
               array_merge(
                    $this->workspace($event), [
                         'schedule' => $schedule,
                         'slots' => $slots,
                         'slotDuration' => $slotDuration,
                         'slotGap' => $slotGap,
                         'slotDurations' => EventTimeSlot::durations(),
                         'slotGaps' => EventTimeSlot::gaps(),
                    ]
               )
          );
     }

     public function updateGenerate(Request $request, Event $event, EventSchedule $schedule) {
          abort_if(
               $schedule->event_id !== $event->id,
               404
          );

          $validator = Validator::make($request->all(), [
               'fixed_slots' => ['required', 'array', 'min:1'],
               'fixed_slots.*.start' => ['required', 'date_format:H:i'],
               'fixed_slots.*.end' => ['required', 'date_format:H:i'],
               'fixed_slots.*.title' => ['nullable', 'string', 'max:255'],
               'fixed_slots.*.slot_type' => [
                    'required',
                    Rule::in(EventTimeSlot::types()),
               ],
          ]);

          /*
          |--------------------------------------------------------------------------
          | Validate Timeline
          |--------------------------------------------------------------------------
          */
          $validator->after(function ($validator) use ($request, $schedule) {
               $parsedSlots = [];
               foreach ($request->fixed_slots ?? [] as $index => $slot) {
                    if (empty($slot['start']) || empty($slot['end'])) {
                         continue;
                    }

                    $startAt = Carbon::parse(
                         $schedule->schedule_date->format('Y-m-d') . ' ' . $slot['start']
                    );

                    $endAt = Carbon::parse(
                         $schedule->schedule_date->format('Y-m-d') . ' ' . $slot['end']
                    );

                    if ($endAt <= $startAt) {
                         $validator->errors()->add(
                              "fixed_slots.$index.end",
                              'End time must be after start time.'
                         );
                         continue;
                    }

                    foreach ($parsedSlots as $existingSlot) {
                         if ($startAt < $existingSlot['end'] && $endAt > $existingSlot['start']) {
                              $validator->errors()->add(
                              "fixed_slots.$index.start",
                              'This slot overlaps another generated slot.'
                              );
                         }
                    }

                    $parsedSlots[] = [
                         'start' => $startAt,
                         'end'   => $endAt,
                         'index' => $index,
                    ];
               }
          });

          if ($validator->fails()) {
               return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
               ], 422);
          }

          /*
          |--------------------------------------------------------------------------
          | Prevent Regeneration After Bookings
          |--------------------------------------------------------------------------
          */
          $hasBookedMeetings = MeetingRequest::query()
               ->whereIn(
                    'event_time_slot_id',
                    EventTimeSlot::query()
                         ->where('event_schedule_id', $schedule->id)
                         ->select('id')
               )
               ->exists();

          if ($hasBookedMeetings) {
               return response()->json([
                    'success' => false,
                    'message' => 'Cannot regenerate time slots because meetings already exist.',
               ], 422);
          }

          DB::beginTransaction();

          try {
               /*
               |--------------------------------------------------------------------------
               | Safe Reset
               |--------------------------------------------------------------------------
               */
               $schedule->timeSlots()->delete();

               $capacity = (int) (
                    $event->default_slot_capacity ?? 1
               );

               $slotMode = match ($event->networking_mode) {
                    'networking' => EventTimeSlot::MODE_NETWORKING,
                    'presentation' => EventTimeSlot::MODE_PRESENTATION,
                    'private' => EventTimeSlot::MODE_PRIVATE,
                    default => EventTimeSlot::MODE_FREE,
               };

               $created = 0;

               foreach ($request->fixed_slots as $slot) {
                    $startAt = Carbon::parse(
                         $schedule->schedule_date->format('Y-m-d') . ' ' . $slot['start']
                    );

                    $endAt = Carbon::parse(
                         $schedule->schedule_date->format('Y-m-d') . ' ' . $slot['end']
                    );

                    $slotType = $slot['slot_type'];

                    $isBookable = in_array(
                         $slotType,
                         [
                              EventTimeSlot::TYPE_MEETING,
                              EventTimeSlot::TYPE_SESSION,
                         ]
                    );

                    EventTimeSlot::create([
                         'event_schedule_id' => $schedule->id,
                         'start_at' => $startAt,
                         'end_at' => $endAt,
                         'capacity' => $capacity,
                         'type' => $slotType,
                         'mode' => $slotMode,
                         'title' => $slot['title'] ?? null,
                         'description' => null,
                         'color' => null,
                         'is_bookable' => $isBookable,
                         'visibility' => EventTimeSlot::VISIBILITY_PUBLIC,
                         'sort_order' => ++$created,
                         'created_by' => auth()->id(),
                    ]);
               }

               DB::commit();

               return response()->json([
                    'success' => true,
                    'message' => 'Time slots updated successfully.',
               ]);
          } catch (\Throwable $e) {
               DB::rollBack();
               report($e);
               return response()->json([
                    'success' => false,
                    'message' => 'Failed to update time slots.',
               ], 500);
          }
     }
}