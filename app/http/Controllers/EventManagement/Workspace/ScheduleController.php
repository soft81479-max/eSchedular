<?php

namespace App\Http\Controllers\EventManagement\Workspace;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\Models\Event;
use App\Models\EventSchedule;

class ScheduleController extends WorkspaceController {
     /*
     |--------------------------------------------------------------------------
     | Schedule Workspace
     |--------------------------------------------------------------------------
     */
     public function index(Event $event, Request $request): View {
          /*
          |--------------------------------------------------------------------------
          | Event Schedules
          |--------------------------------------------------------------------------
          */
          $event->load([
               'schedules' => function ($query) {
                    $query->withCount('timeSlots')
                         ->orderBy('schedule_date')
                         ->orderBy('sort_order')
                         ->orderBy('title');
               }
          ]);

          /*
          |--------------------------------------------------------------------------
          | Selected Schedule
          |--------------------------------------------------------------------------
          */
          $selectedSchedule = null;

          if ($request->filled('schedule')) {
               $selectedSchedule = $event->schedules()
                    ->with([
                         'timeSlots' => function ($query) {
                              $query->withCount('meetingRequests')
                              ->orderBy('start_at');
                         }
                    ])
                    ->withCount('timeSlots')
                    ->find($request->schedule);
          } elseif ($event->schedules->isNotEmpty()) {
               $selectedSchedule = $event->schedules()
                    ->with([
                         'timeSlots' => function ($query) {
                              $query->withCount('meetingRequests')
                              ->orderBy('start_at');
                         }
                    ])
                    ->withCount('timeSlots')
                    ->first();
          }

          return view(
               'event_management.workspace.schedules.index',
               array_merge(
                    $this->workspace($event),
                    compact('selectedSchedule')
               )
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Create
     |--------------------------------------------------------------------------
     */
     public function create(Event $event): View {
          return view(
               'event_management.workspace.schedules.partials.form',
               [
                    'event'    => $event,
                    'schedule' => null,
                    'isEdit'   => false,
               ]
          );
     }

     public function store(Request $request, Event $event) {
          $validator = Validator::make($request->all(), [
               'title' => 'required|string|max:255',
               'description' => 'nullable|string',
               'schedule_date' => [
                    'required',
                    'date',
                    'after_or_equal:' . optional($event->start_date)->format('Y-m-d'),
                    'before_or_equal:' . optional($event->end_date)->format('Y-m-d'),
               ],

               'start_time' => 'required|date_format:H:i',
               'end_time'   => 'required|date_format:H:i|after:start_time',

               'timezone' => 'required|string|max:100',

               'color' => 'nullable|string|max:50',

               'visibility' => 'nullable|in:' . implode(',', EventSchedule::visibilities()),
          ]);

          if ($validator->fails()) {
               return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors(),
               ], 422);
          }

          DB::beginTransaction();

          try {
               EventSchedule::create([
                    'event_id' => $event->id,

                    'title' => $request->title,
                    'description' => $request->description,

                    'schedule_date' => $request->schedule_date,

                    'start_time' => $request->start_time,
                    'end_time'   => $request->end_time,

                    'timezone' => $request->timezone ?: $event->timezone,
                    'color' => $request->color ?: 'primary',
                    'visibility' => $request->visibility ?: EventSchedule::VISIBILITY_PUBLIC,
                    'sort_order' => 0,
                    'created_by' => auth()->id(),
               ]);

               DB::commit();
               return response()->json([
                    'success' => true,
                    'message' => 'Schedule created successfully.',
               ]);
          } catch (\Throwable $e) {
               DB::rollBack();
               report($e);
               return response()->json([
                    'success' => false,
                    'message' => 'Failed to create schedule.',
               ], 500);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Edit
     |--------------------------------------------------------------------------
     */
     public function edit(Event $event, EventSchedule $schedule) {
          abort_if(
               $schedule->event_id !== $event->id,
               404
          );

          return view('event_management.workspace.schedules.partials.form',['event'=> $event,'schedule'=> $schedule,'isEdit'=> true,]);
     }

     public function update(Request $request, Event $event, EventSchedule $schedule) {
          if ($schedule->event_id !== $event->id) {
               abort(404);
          }
          
          $validator = Validator::make($request->all(), [
               'title' => 'required|string|max:255',
               'description' => 'nullable|string',
               'schedule_date' => [
                    'required',
                    'date',
                    'after_or_equal:' . optional($event->start_date)->format('Y-m-d'),
                    'before_or_equal:' . optional($event->end_date)->format('Y-m-d'),
               ],
               'start_time' => 'required|date_format:H:i',
               'end_time' => 'required|date_format:H:i|after:start_time',
               'color' => 'nullable|string|max:50',
               'timezone' => 'required|string|max:50',
               'visibility' => 'nullable|in:' . implode(',', EventSchedule::visibilities()),
          ]);

          if ($validator->fails()) {
               return response()->json(['success' => false,'errors' => $validator->errors()
               ], 422);
          }

          DB::beginTransaction();
          try {
               if ($schedule->timeSlots()->exists() && $schedule->schedule_date->format('Y-m-d') !== $request->schedule_date) {
                    return response()->json([
                         'success' => false,
                         'message' => 'Cannot change schedule date after slots have been created.'
                    ], 422);
               }

               $schedule->update([
                    'title' => $request->title,
                    'description' => $request->description,
                    'schedule_date' => $request->schedule_date,
                    'start_time' => $request->start_time,
                    'end_time' => $request->end_time,
                    'color' => $request->color ?: 'primary',
                    'timezone' => $request->timezone ?: $event->event_timezone,
                    'visibility' => $request->visibility ?: EventSchedule::VISIBILITY_PUBLIC,
                    'updated_by' => auth()->id(),
               ]);

               DB::commit();
               return response()->json(['success' => true,'message' => 'Schedule updated successfully']);
          } catch (\Throwable $e) {
               DB::rollBack();
               report($e);
               return response()->json(['success' => false,'message' => 'Failed to update schedule'], 500);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Delete
     |--------------------------------------------------------------------------
     */
     public function destroy(Event $event, EventSchedule $schedule) {
          abort_if(
               $schedule->event_id !== $event->id,
               404
          );

          if (! $schedule->canBeDeleted()) {
               return response()->json([
                    'success' => false,
                    'message' => 'This schedule cannot be deleted because it is already in use.',
               ], 422);
          }

          DB::beginTransaction();

          try {
               $schedule->timeSlots()->delete();
               $schedule->delete();

               DB::commit();
               return response()->json([
                    'success' => true,
                    'message' => 'Schedule deleted successfully.',
               ]);
          } catch (\Throwable $e) {
               DB::rollBack();
               report($e);
               return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete schedule.',
               ], 500);
          }
     }
}