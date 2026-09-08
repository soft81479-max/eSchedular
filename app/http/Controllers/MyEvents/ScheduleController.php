<?php

namespace App\Http\Controllers\MyEvents;

use Carbon\Carbon;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

use App\Models\Event;
use App\Models\EventTimeSlot;

use App\Services\MyEvents\ScheduleService;

class ScheduleController extends WorkspaceController {
     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     */
     public function index(Event $event,ScheduleService $scheduleService,): View {
          $workspace = $this->workspace($event);

          $workspace['initialMonth'] = $scheduleService->initialMonth(
               event: $workspace['event']
          );

          $workspace['initialDate'] = $scheduleService->initialDate(
               event: $workspace['event']
          );

          $workspace = array_merge(
               $workspace,
               $scheduleService->workspace($workspace)
          );

          return view(
               'my_events.schedule.index',
               $workspace
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Calendar
     |--------------------------------------------------------------------------
     */
     public function calendar(Request $request,Event $event,ScheduleService $scheduleService,): JsonResponse {
          $workspace = $this->workspace($event);
          return response()->json(
               $scheduleService->calendar(
                    event: $workspace['event'],
                    participant: $workspace['participant'],
                    month: $request->filled('month')
                         ? Carbon::parse($request->month)
                         : null,
               )
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Timeline
     |--------------------------------------------------------------------------
     */
     public function timeline(Request $request,Event $event,ScheduleService $scheduleService,): JsonResponse {
          $workspace = $this->workspace($event);
          return response()->json(
               $scheduleService->timeline(
                    event: $workspace['event'],
                    participant: $workspace['participant'],
                    date: $request->filled('date')
                         ? Carbon::parse($request->date)
                         : null,
               )
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Show
     |--------------------------------------------------------------------------
     */
     public function show(Event $event,EventTimeSlot $eventTimeSlot,ScheduleService $scheduleService,): View {
          /*
          |--------------------------------------------------------------------------
          | Event Scope
          |--------------------------------------------------------------------------
          */
          $this->ensureTimeSlotBelongsToEvent(
               event: $event,
               slot: $eventTimeSlot,
          );

          /*
          |--------------------------------------------------------------------------
          | Workspace
          |--------------------------------------------------------------------------
          */
          $workspace = $this->workspace($event);

          /*
          |--------------------------------------------------------------------------
          | Item
          |--------------------------------------------------------------------------
          */
          $item = $scheduleService->item(
               slot: $eventTimeSlot,
               participant: $workspace['participant'],
          );

          /*
          |--------------------------------------------------------------------------
          | View
          |--------------------------------------------------------------------------
          */
          return view(
               'my_events.schedule.partials.content',
               [
                    'event' => $event,
                    'participant' => $workspace['participant'],
                    'item' => $item,
               ]
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Ensure Time Slot Belongs To Event
     |--------------------------------------------------------------------------
     */
     protected function ensureTimeSlotBelongsToEvent(Event $event,EventTimeSlot $slot,): void {
          abort_unless(
               $slot->schedule()
                    ->where(
                         'event_id',
                         $event->id
                    )
                    ->exists(),
               404
          );
     }
}