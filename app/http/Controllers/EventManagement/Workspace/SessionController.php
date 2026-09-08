<?php

namespace App\Http\Controllers\EventManagement\Workspace;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use Yajra\DataTables\Facades\DataTables;

use App\Models\Event;
use App\Models\EventTrack;
use App\Models\EventSession;
use App\Models\EventTimeSlot;

class SessionController extends WorkspaceController {
     /*
     |--------------------------------------------------------------------------
     | Session Workspace
     |--------------------------------------------------------------------------
     */
     public function index(Event $event): View {
          return view(
               'event_management.workspace.sessions.index',
               $this->workspace($event)
          );
     }

     /*
     |--------------------------------------------------------------------------
     | DataTable
     |--------------------------------------------------------------------------
     */
     public function datatable(Event $event, Request $request) {
          if (! auth()->user()->canPermission('events.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied',
               ], 403);
          }

          if ($request->ajax()) {
               $addButtonHtml = '';
               if (auth()->user()->canPermission('events.create')) {
                    $addButtonHtml = action_button('add', [
                         'modal-size' => 'modal-lg',
                         'label'      => 'ADD',
                         'title'      => 'Add Session',
                         'icon'       => '',
                         'color'      => 'warning',
                         'createUrl'  => route('events.sessions.create', $event),
                         'storeUrl'   => route('events.sessions.store', $event),
                         'table'      => 'sessionsTable',
                    ]);
               }

               $query = EventSession::query()
                    ->where('event_id', $event->id)
                    ->with([
                         'track',
                         'timeSlot.schedule',
                    ])
                    ->orderBy('event_time_slot_id')
                    ->orderBy('sort_order');

               return DataTables::eloquent($query)
                    ->addIndexColumn()

                    /*
                    |--------------------------------------------------------------------------
                    | Session
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('session', function ($row) {
                         $color = $row->color ?: 'primary';
                         $icon  = $row->track?->icon ?: 'ph-presentation-chart';
                         return '
                              <div class="d-flex align-items-center">
                                   <div class="me-2">
                                        <span class="badge bg-'.$color.' p-2">
                                             <i class="'.$icon.' ph-sm"></i>
                                        </span>
                                   </div>
                                   <div>
                                        <div class="fw-semibold fs-md">'.e($row->title).'</div>
                                        <div class="text-muted fs-xs">'.e($row->slug).'</div>
                                   </div>
                              </div>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Description
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('description', function ($row) {
                         return '
                              <div class="text-wrap">
                              '.e($row->description ?: '-').'
                              </div>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Track
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('track', function ($row) {
                         if (! $row->track) {
                              return '<span class="text-muted">-</span>';
                         }

                         return '
                              <span class="badge fs-xs bg-'.($row->track->color ?: 'secondary').'">
                              '.e($row->track->name).'
                              </span>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Schedule / Time
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('slot', function ($row) {
                         if (! $row->timeSlot) {
                              return '<span class="text-muted">-</span>';
                         }

                         return '
                              <div class="fw-semibold fs-sm">
                              '.$row->timeSlot->start_at->format('h:i A').'
                              -
                              '.$row->timeSlot->end_at->format('h:i A').'
                              </div>

                              <div class="text-muted fs-xs">'.e($row->timeSlot->schedule?->title).'</div>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Status
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('status', function ($row) {
                         return '
                              <span class="badge fs-xs '.$row->status_badge_class.'">
                              '.$row->status_label.'
                              </span>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Sort
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('sort', function ($row) {
                         return '
                              <span class="badge bg-light border text-dark">
                              '.$row->sort_order.'
                              </span>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Created
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('created', function ($row) {
                         return '
                              <div class="fw-semibold fs-sm">'.$row->created_at?->format('d M Y').'</div>
                              <div class="text-muted fs-xs">'.$row->created_at?->format('h:i A').'</div>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Actions
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('actions', function ($row) use ($event) {
                         $buttons = '';

                         if (auth()->user()->canPermission('events.edit')) {
                              $buttons .= action_button('edit', [
                                   'modal-size' => 'modal-lg',
                                   'btn-type'   => 'btn-icon',
                                   'title'      => 'Edit Session',
                                   'color'      => 'primary',
                                   'editUrl'    => route('events.sessions.edit', [$event, $row]),
                                   'updateUrl'  => route('events.sessions.update', [$event, $row]),
                                   'table'      => 'sessionsTable',
                              ]);
                         }

                         if (auth()->user()->canPermission('events.delete')) {
                              $buttons .= action_button('delete', [
                                   'btn-type' => 'btn-icon',
                                   'title'    => 'Delete Session',
                                   'color'    => 'danger',
                                   'url'      => route('events.sessions.destroy', [$event, $row]),
                                   'method'   => 'DELETE',
                                   'table'    => 'sessionsTable',
                              ]);
                         }
                         return '<div class="d-flex justify-content-center gap-1">'.$buttons.'</div>';
                    })

                    ->rawColumns(['session','description','track','slot','sort','status','created','actions',])
                    ->with('custom_meta', [
                         'button_html' => $addButtonHtml,
                    ])
                    ->make(true);
               }
     }

     /*
     |--------------------------------------------------------------------------
     | Create
     |--------------------------------------------------------------------------
     */
     public function create(Event $event) {
          $tracks = $event->tracks()
               ->active()
               ->ordered()
               ->get();

          $timeSlots = EventTimeSlot::query()
               ->whereHas('schedule', function ($query) use ($event) {
                    $query->where('event_id', $event->id);
               })
               ->where('type', EventTimeSlot::TYPE_SESSION)
               ->whereDoesntHave('session')
               ->orderBy('start_at')
               ->get();

          return view(
               'event_management.workspace.sessions.partials.form',
               [
                    'event'      => $event,
                    'session'    => null,
                    'tracks'     => $tracks,
                    'timeSlots'  => $timeSlots,
                    'isEdit'     => false,
               ]
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Store
     |--------------------------------------------------------------------------
     */
     public function store(Request $request, Event $event) {
          $validator = Validator::make($request->all(), [
               'title' => 'required|string|max:255',
               'slug' => [
                    'nullable',
                    'string',
                    'max:180',
                    Rule::unique('event_sessions')
                         ->where('event_id', $event->id),
               ],
               'event_track_id' => [
                    'required',
                    Rule::exists('event_tracks', 'id')
                         ->where('event_id', $event->id),
               ],
               'event_time_slot_id' => [
                    'required',
                    Rule::exists('event_time_slots', 'id'),
               ],
               'description' => 'nullable|string',
               'color' => 'nullable|string|max:30',
               'sort_order' => 'nullable|integer|min:0',
               'status' => [
                    'required',
                    Rule::in(EventSession::statuses()),
               ],
          ]);

          $validator->after(function ($validator) use ($request, $event) {
               $slot = EventTimeSlot::query()
                    ->whereKey($request->event_time_slot_id)
                    ->whereHas('schedule', function ($query) use ($event) {
                         $query->where('event_id', $event->id);
                    })
                    ->first();

               if (! $slot) {
                    $validator->errors()->add(
                         'event_time_slot_id',
                         'Invalid session slot selected.'
                    );
                    return;
               }

               if (! $slot->canHaveSession()) {
                    $validator->errors()->add(
                         'event_time_slot_id',
                         'Only Session type slots can be assigned.'
                    );
               }

               if ($slot->hasSession()) {
                    $validator->errors()->add(
                         'event_time_slot_id',
                         'This time slot already has a session assigned.'
                    );
               }
          });

          if ($validator->fails()) {
               return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors(),
               ], 422);
          }

          DB::beginTransaction();

          try {
               EventSession::create([
                    'event_id' => $event->id,
                    'event_track_id' => $request->event_track_id,
                    'event_time_slot_id' => $request->event_time_slot_id,
                    'title' => $request->title,
                    'slug' => $request->slug ?: null,
                    'description' => $request->description,
                    'color' => $request->color ?: 'primary',
                    'sort_order' => $request->sort_order ?? 0,
                    'status' => $request->status,
                    'created_by' => auth()->id(),
               ]);

               DB::commit();
               return response()->json([
                    'success' => true,
                    'message' => 'Session created successfully.',
               ]);
          } catch (\Throwable $e) {
               DB::rollBack();
               report($e);
               return response()->json([
                    'success' => false,
                    'message' => 'Failed to create session.',
               ], 500);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Edit
     |--------------------------------------------------------------------------
     */
     public function edit(Event $event, EventSession $session) {
          abort_if(
               $session->event_id !== $event->id,
               404
          );

          $tracks = $event->tracks()
               ->active()
               ->ordered()
               ->get();

          $timeSlots = EventTimeSlot::query()
               ->whereHas('schedule', function ($query) use ($event) {
                    $query->where('event_id', $event->id);
               })
               ->where('type', EventTimeSlot::TYPE_SESSION)
               ->where(function ($query) use ($session) {
                    $query->whereDoesntHave('session')
                         ->orWhere('id', $session->event_time_slot_id);
               })
               ->orderBy('start_at')
               ->get();

          return view(
               'event_management.workspace.sessions.partials.form',
               [
                    'event'      => $event,
                    'session'    => $session,
                    'tracks'     => $tracks,
                    'timeSlots'  => $timeSlots,
                    'isEdit'     => true,
               ]
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Update
     |--------------------------------------------------------------------------
     */
     public function update(Request $request,Event $event,EventSession $session) {
          abort_if(
               $session->event_id !== $event->id,
               404
          );

          $validator = Validator::make($request->all(), [
               'title' => 'required|string|max:255',
               'slug' => [
                    'nullable',
                    'string',
                    'max:180',
                    Rule::unique('event_sessions')
                         ->ignore($session->id)
                         ->where('event_id', $event->id),
               ],

               'event_track_id' => [
                    'required',
                    Rule::exists('event_tracks', 'id')
                         ->where('event_id', $event->id),
               ],

               'event_time_slot_id' => [
                    'required',
                    Rule::exists('event_time_slots', 'id'),
               ],

               'description' => 'nullable|string',
               'color' => 'nullable|string|max:30',
               'sort_order' => 'nullable|integer|min:0',
               'status' => [
                    'required',
                    Rule::in(EventSession::statuses()),
               ],
          ]);

          $validator->after(function ($validator) use ($request, $event, $session) {
               $slot = EventTimeSlot::query()
                    ->whereKey($request->event_time_slot_id)
                    ->whereHas('schedule', function ($query) use ($event) {
                         $query->where('event_id', $event->id);
                    })
                    ->first();

               if (! $slot) {
                    $validator->errors()->add(
                         'event_time_slot_id',
                         'Invalid session slot selected.'
                    );

                    return;
               }

               if (! $slot->canHaveSession()) {
                    $validator->errors()->add(
                         'event_time_slot_id',
                         'Only Session type slots can be assigned.'
                    );
               }

               if ($slot->session && $slot->session->id !== $session->id) {
                    $validator->errors()->add(
                         'event_time_slot_id',
                         'This session slot is already assigned.'
                    );
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
               $session->update([
                    'event_track_id' => $request->event_track_id,
                    'event_time_slot_id' => $request->event_time_slot_id,
                    'title' => $request->title,
                    'slug' => $request->slug ?: null,
                    'description' => $request->description,
                    'color' => $request->color ?: 'primary',
                    'sort_order' => $request->sort_order ?? 0,
                    'status' => $request->status,
                    'updated_by' => auth()->id(),
               ]);

               DB::commit();
               return response()->json([
                    'success' => true,
                    'message' => 'Session updated successfully.',
               ]);
          } catch (\Throwable $e) {
               DB::rollBack();
               report($e);
               return response()->json([
                    'success' => false,
                    'message' => 'Failed to update session.',
               ], 500);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Delete
     |--------------------------------------------------------------------------
     */
     public function destroy(Event $event,EventSession $session) {
          abort_if(
               $session->event_id !== $event->id,
               404
          );

          if (! $session->canBeDeleted()) {
               return response()->json([
                    'success' => false,
                    'message' => 'This session cannot be deleted because it is already in use.',
               ], 422);
          }

          DB::beginTransaction();

          try {
               $session->delete();
               DB::commit();
               return response()->json([
                    'success' => true,
                    'message' => 'Session deleted successfully.',
               ]);
          } catch (\Throwable $e) {
               DB::rollBack();
               report($e);
               return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete session.',
               ], 500);
          }
     }
}