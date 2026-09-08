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

class TrackController extends WorkspaceController {
     /*
     |--------------------------------------------------------------------------
     | Track Workspace
     |--------------------------------------------------------------------------
     */
     public function index(Event $event): View {
          if (!auth()->user()->canPermission('events.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view(
               'event_management.workspace.tracks.index',
               $this->workspace($event)
          );
     }

     /*
     |--------------------------------------------------------------------------
     | DataTable
     |--------------------------------------------------------------------------
     */
     public function datatable(Event $event, Request $request) {
          if (!auth()->user()->canPermission('events.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          if ($request->ajax()) {
               $addButtonHtml = '';
               if (auth()->user()->canPermission('events.create')) {
                    $addButtonHtml = action_button('add', [
                         'modal-size' => 'modal-md',
                         'label' => 'ADD',
                         'title' => 'Add Track',
                         'icon' => '',
                         'color' => 'warning',
                         'createUrl' => route('events.tracks.create', $event), 
                         'storeUrl' => route('events.tracks.store', $event),   
                         'table' => 'tracksTable'                
                    ]);
               }

               $query = EventTrack::query()
                    ->where('event_id', $event->id)
                    ->withCount('sessions')
                    ->orderBy('sort_order')
                    ->orderBy('name');

               return DataTables::eloquent($query)
                    ->addIndexColumn()
                    /*
                    |--------------------------------------------------------------------------
                    | Name
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('name', function ($row) {
                         $icon = $row->icon ?: 'ph-buildings';
                         $color = $row->color ?: 'secondary';
                         return '
                              <div class="d-flex align-items-center">
                                   <div class="me-2">
                                        <span class="badge bg-' . e($color) . ' p-2">
                                             <i class="' . e($icon) . ' ph-sm"></i>
                                        </span>
                                   </div>
                                   <div>
                                        <div class="fw-semibold fs-md">' . e($row->name) . '</div>
                                        <div class="text-muted fs-xs">' . e($row->slug) . '</div>
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
                              <div class="text-wrap" style="max-width:250px;">
                              ' . e($row->description ?: '-') . '
                              </div>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Status
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('status', function ($row) {
                         return '
                              <span class="badge fs-xs ' . $row->status_badge_class . '">
                              ' . ($row->is_active ? 'Active' : 'Inactive') . '
                              </span>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Sort Order
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('sort', function ($row) {
                         return '
                              <span class="badge bg-light border text-dark">
                              ' . $row->sort_order . '
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
                              <div class="fw-semibold fs-sm">' . $row->created_at?->format('d M Y') . '</div>
                              <div class="text-muted fs-xs">' . $row->created_at?->format('h:i A') . '</div>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Actions
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('actions', function ($row) use ($event) {
                         $canEdit   = auth()->user()->canPermission('events.edit');
                         $canDelete = auth()->user()->canPermission('events.delete');

                         $buttons = '';
                         /*
                         |--------------------------------------------------------------------------
                         | Edit
                         |--------------------------------------------------------------------------
                         */
                         if ($canEdit) {
                              $buttons .= action_button('edit', [
                                   'modal-size' => 'modal-md',
                                   'btn-type' => 'btn-icon',
                                   'title' => 'Edit Track',
                                   'icon' => 'ph-pencil-line',
                                   'color' => 'primary',
                                   'editUrl' => route('events.tracks.edit',[$event, $row]),
                                   'updateUrl' => route('events.tracks.update',[$event, $row]),
                                   'table' => 'tracksTable'
                              ]);
                         } else {
                              $buttons .= '
                                   <button type="button" class="btn btn-sm btn-icon btn-light border py-1 cursor-pointer"
                                        title="No permission to edit" disabled>
                                        <i class="ph-pencil-line fs-sm"></i>
                                   </button>
                              ';
                         }

                         /*
                         |--------------------------------------------------------------------------
                         | Delete
                         |--------------------------------------------------------------------------
                         */
                         if ($canDelete) {
                              $buttons .= action_button('delete', [
                                   'btn-type' => 'btn-icon',
                                   'title' => 'Delete Track',
                                   'icon' => 'ph-trash',
                                   'color' => 'danger',
                                   'url' => route('events.tracks.destroy',[$event, $row]),
                                   'method' => 'DELETE',
                                   'table' => 'tracksTable'
                              ]);
                         }

                         return '
                              <div class="d-flex justify-content-center align-items-center gap-1">' . $buttons . '</div>
                         ';
                    })
                    ->rawColumns(['name','description','sort','status','created','actions'])
                    ->with('custom_meta', [
                         'button_html' => $addButtonHtml
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
          return view(
               'event_management.workspace.tracks.partials.form',
               [
                    'event'   => $event,
                    'track'   => null,
                    'isEdit'  => false,
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
               'name' => 'required|string|max:255',
               'slug' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('event_tracks')
                         ->where('event_id', $event->id),
               ],
               'description' => 'nullable|string',
               'icon' => 'nullable|string|max:100',
               'color' => 'nullable|string|max:30',
               'sort_order' => 'nullable|integer|min:0',
               'is_active' => 'nullable|boolean',
          ]);

          if ($validator->fails()) {
               return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors(),
               ], 422);
          }

          DB::beginTransaction();

          try {
               EventTrack::create([
                    'event_id' => $event->id,
                    'name' => $request->name,
                    'slug' => $request->slug ?: null,
                    'description' => $request->description,
                    'icon' => $request->icon,
                    'color' => $request->color ?: 'primary',
                    'sort_order' => $request->sort_order ?? 0,
                    'is_active' => $request->boolean('is_active'),
                    'created_by' => auth()->id(),
               ]);

               DB::commit();
               return response()->json([
                    'success' => true,
                    'message' => 'Track created successfully.',
               ]);
          } catch (\Throwable $e) {
               DB::rollBack();
               report($e);
               return response()->json([
                    'success' => false,
                    'message' => 'Failed to create track.',
               ], 500);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Edit
     |--------------------------------------------------------------------------
     */
     public function edit(Event $event, EventTrack $track) {
          abort_if(
               $track->event_id !== $event->id,
               404
          );

          return view(
               'event_management.workspace.tracks.partials.form',
               [
                    'event'   => $event,
                    'track'   => $track,
                    'isEdit'  => true,
               ]
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Update
     |--------------------------------------------------------------------------
     */
     public function update(Request $request,Event $event,EventTrack $track) {
          abort_if(
               $track->event_id !== $event->id,
               404
          );

          $validator = Validator::make($request->all(), [
               'name' => 'required|string|max:255',
               'slug' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('event_tracks')
                         ->ignore($track->id)
                         ->where('event_id', $event->id),
               ],

               'description' => 'nullable|string',
               'icon' => 'nullable|string|max:100',
               'color' => 'nullable|string|max:30',
               'sort_order' => 'nullable|integer|min:0',
               'is_active' => 'nullable|boolean',
          ]);

          if ($validator->fails()) {
               return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors(),
               ], 422);
          }

          DB::beginTransaction();

          try {
               $track->update([
                    'name' => $request->name,
                    'slug' => $request->slug ?: null,
                    'description' => $request->description,
                    'icon' => $request->icon,
                    'color' => $request->color ?: 'primary',
                    'sort_order' => $request->sort_order ?? 0,
                    'is_active' => $request->boolean('is_active'),
                    'updated_by' => auth()->id(),
               ]);

               DB::commit();
               return response()->json([
                    'success' => true,
                    'message' => 'Track updated successfully.',
               ]);
          } catch (\Throwable $e) {
               DB::rollBack();
               report($e);
               return response()->json([
                    'success' => false,
                    'message' => 'Failed to update track.',
               ], 500);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Delete
     |--------------------------------------------------------------------------
     */
     public function destroy(Event $event,EventTrack $track) {
          abort_if(
               $track->event_id !== $event->id,
               404
          );

          if (! $track->canBeDeleted()) {
               return response()->json([
                    'success' => false,
                    'message' => 'This track cannot be deleted because it is already assigned to one or more sessions.',
               ], 422);
          }

          DB::beginTransaction();

          try {
               $track->delete();
               DB::commit();
               return response()->json([
                    'success' => true,
                    'message' => 'Track deleted successfully.',
               ]);
          } catch (\Throwable $e) {
               DB::rollBack();
               report($e);
               return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete track.',
               ], 500);
          }
     }
}