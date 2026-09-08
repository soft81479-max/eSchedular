<?php

namespace App\Http\Controllers\Configurations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Str;

use App\Models\EventType;

class EventTypeController extends Controller {
     /*
     |--------------------------------------------------------------------------
     | Listing
     |--------------------------------------------------------------------------
     */
     public function index() {
          if (!auth()->user()->canPermission('event_types.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view('configurations.event_types.index');
     }

     public function datatable(Request $request) {
          if (!auth()->user()->canPermission('event_types.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          if ($request->ajax()) {
               $addButtonHtml = '';
               if (auth()->user()->canPermission('event_types.create')) {
                    $addButtonHtml = action_button('add', [
                         'modal-size' => 'modal-md',
                         'label' => 'ADD',
                         'title' => 'Add Meeting Type',
                         'icon' => '',
                         'color' => 'warning',
                         'createUrl' => route('event-types.create'), 
                         'storeUrl' => route('event-types.store'),   
                         'table' => 'eventTypesTable'                
                    ]);
               }
               
               $query = EventType::query()->orderBy('sort_order')->orderBy('name');
               return DataTables::of($query)
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
                    | Slot Duration
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('slot_duration', function ($row) {
                         return '
                              <span class="badge bg-purple p-1">' . e($row->default_slot_duration) . '</span>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Capacity
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('slot_capacity', function ($row) {
                         return '
                              <span class="badge bg-purple p-1">' . e($row->default_slot_capacity) . '</span>
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
                    ->addColumn('actions', function ($row) {
                         $canEdit   = auth()->user()->canPermission('event_types.edit');
                         $canDelete = auth()->user()->canPermission('event_types.delete');

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
                                   'title' => 'Edit Event Type',
                                   'icon' => 'ph-pencil-line',
                                   'color' => 'primary',
                                   'editUrl' => route('event-types.edit',$row->id),
                                   'updateUrl' => route('event-types.update',$row->id),
                                   'table' => 'eventTypesTable'
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
                                   'title' => 'Delete Event Type',
                                   'icon' => 'ph-trash',
                                   'color' => 'danger',
                                   'url' => route('event-types.destroy',$row->id),
                                   'method' => 'DELETE',
                                   'table' => 'eventTypesTable'
                              ]);
                         }

                         return '
                              <div class="d-flex justify-content-center align-items-center gap-1">' . $buttons . '</div>
                         ';
                    })
                    ->rawColumns(['name','description','slot_duration','slot_capacity','status','sort','created','actions'])
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
     public function create() {
          if (!auth()->user()->canPermission('event_types.create')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view(
               'configurations.event_types.partials.form'
          );
     }

     public function store(Request $request) {
          if (!auth()->user()->canPermission('event_types.create')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validator = Validator::make(
               $request->all(),
               [
                    'name' => ['required','string','max:255','unique:event_types,name'],
                    'slug' => ['nullable','string','max:255','unique:event_types,slug'],
                    'description' => ['nullable','string'],
                    'icon' => ['nullable','string','max:255'],
                    'color' => ['nullable','string','max:50'],
                    
                    'default_slot_duration' => ['required','integer','min:15'],
                    'default_slot_capacity' => ['required','integer','min:1'],

                    'sort_order' => ['nullable','integer','min:0'],
                    'is_active' => ['nullable','boolean'],
               ]
          );

          if ($validator->fails()) {
               return response()->json([
                    'success' => false,
                    'message' => collect($validator->errors()->toArray())->flatten()->first(),
                    'errors' => $validator->errors(),
               ], 422);
          }

          $rawSlug = $request->filled('slug') ? $request->slug : $request->name;
          $slug = Str::slug($rawSlug, '_');

          $eventType = EventType::create([
               'name' => $request->name,
               'slug' => $slug,
               'description' => $request->description,
               'icon' => $request->icon,
               'color' => $request->color,
               'default_slot_duration' => $request->default_slot_duration ?? 15,
               'default_slot_capacity' => $request->default_slot_capacity ?? 1,
               'sort_order' => $request->sort_order ?? 0,
               'is_active' => $request->boolean('is_active',true),
               'created_by' => auth()->id(),
               'updated_by' => auth()->id(),
          ]);

          return response()->json([
               'success' => true,
               'message' => 'Event type created successfully',
               'data' => $eventType
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Edit
     |--------------------------------------------------------------------------
     */
     public function edit(EventType $eventType) {
          if (!auth()->user()->canPermission('event_types.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view(
               'configurations.event_types.partials.form',compact('eventType')
          );
     }

     public function update(Request $request,EventType $eventType) {
          if (!auth()->user()->canPermission('event_types.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }
          
          $validator = Validator::make(
               $request->all(),
               [
                    'name' => ['required','string','max:255','unique:event_types,name,' . $eventType->id],
                    'slug' => ['nullable','string','max:255','unique:event_types,slug,' . $eventType->id],
                    'description' => ['nullable','string'],
                    'icon' => ['nullable','string','max:255'],
                    'color' => ['nullable','string','max:50'],
                    
                    'default_slot_duration' => ['required','integer','min:15'],
                    'default_slot_capacity' => ['required','integer','min:1'],

                    'sort_order' => ['nullable','integer','min:0'],
                    'is_active' => ['nullable','boolean'],
               ]
          );

          if ($validator->fails()) {
               return response()->json([
                    'success' => false,
                    'message' => collect($validator->errors()->toArray())->flatten()->first(),
                    'errors' => $validator->errors(),
               ], 422);
          }

          $rawSlug = $request->filled('slug') ? $request->slug : $request->name;
          $slug = Str::slug($rawSlug, '_');

          $eventType->update([
               'name' => $request->name,
               'slug' => $slug,
               'description' => trim($request->description),
               'icon' => $request->icon,
               'color' => $request->color,
               'default_slot_duration' => $request->default_slot_duration ?? 15,
               'default_slot_capacity' => $request->default_slot_capacity ?? 1,
               'sort_order' => $request->sort_order ?? 0,
               'is_active' => $request->boolean('is_active'),
               'updated_by' => auth()->id(),
          ]);

          return response()->json([
               'success' => true,
               'message' => 'Event type updated successfully'
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Delete
     |--------------------------------------------------------------------------
     */
     public function destroy(EventType $eventType){
          if (!auth()->user()->canPermission('event_types.delete')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $eventType->delete();
          return response()->json([
               'success' => true,
               'message' => 'Event type deleted successfully'
          ]);
     }
}