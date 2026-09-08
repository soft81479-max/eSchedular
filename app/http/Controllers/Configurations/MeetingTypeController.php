<?php

namespace App\Http\Controllers\Configurations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Str;

use App\Models\MeetingType;

class MeetingTypeController extends Controller {
     /*
     |--------------------------------------------------------------------------
     | Listing
     |--------------------------------------------------------------------------
     */
     public function index() {
          if (!auth()->user()->canPermission('meeting_types.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view('configurations.meeting_types.index');
     }

     /*
     |--------------------------------------------------------------------------
     | Datatable
     |--------------------------------------------------------------------------
     */
     public function datatable(Request $request) {
          if (!auth()->user()->canPermission('meeting_types.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          if ($request->ajax()) {
               $addButtonHtml = '';
               if (auth()->user()->canPermission('meeting_types.view')) {
                    $addButtonHtml = action_button('add', [
                         'modal-size' => 'modal-md',
                         'label' => 'ADD',
                         'title' => 'Add Meeting Type',
                         'icon' => '',
                         'color' => 'warning',
                         'createUrl' => route('meeting-types.create'), 
                         'storeUrl' => route('meeting-types.store'),   
                         'table' => 'meetingTypesTable'                
                    ]);
               }
               
               $query = MeetingType::query()->orderBy('sort_order')->orderBy('name');
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
                    | Duration
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('duration', function ($row) {
                         return '
                              <div class="d-flex align-items-center lh-1">
                                   <div class="me-2">
                                        <span class="badge bg-info">
                                             <i class="ph-clock ph-sm"></i>
                                        </span>
                                   </div>
                                   <div>
                                        <div class="fs-sm">' . e($row->default_duration) . '</div>
                                        <div class="fs-xs">minutes</div>
                                   </div>
                              </div>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Capacity
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('capacity', function ($row) {
                         return '
                              <div class="d-flex align-items-center lh-1">
                                   <div class="me-2">
                                        <span class="badge bg-purple p-1">
                                             <i class="ph-users ph-sm"></i>
                                        </span>
                                   </div>
                                   <div>
                                        <div class="fw-semibold fs-sm">Min: ' . e($row->min_participants) . '</div>
                                        <div class="fw-semibold fs-sm">Max: ' . e($row->max_participants) . '</div>
                                   </div>
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
                    ->addColumn('actions', function ($row) {
                         $canEdit   = auth()->user()->canPermission('meeting_types.edit');
                         $canDelete = auth()->user()->canPermission('meeting_types.delete');

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
                                   'title' => 'Edit Meeting Type',
                                   'icon' => 'ph-pencil-line',
                                   'color' => 'primary',
                                   'editUrl' => route('meeting-types.edit',$row->id),
                                   'updateUrl' => route('meeting-types.update',$row->id),
                                   'table' => 'meetingTypesTable'
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
                                   'title' => 'Delete Meeting Type',
                                   'icon' => 'ph-trash',
                                   'color' => 'danger',
                                   'url' => route('meeting-types.destroy',$row->id),
                                   'method' => 'DELETE',
                                   'table' => 'meetingTypesTable'
                              ]);
                         }

                         return '
                              <div class="d-flex justify-content-center align-items-center gap-1">' . $buttons . '</div>
                         ';
                    })
                    ->rawColumns(['name','description','duration','capacity','status','sort','created','actions'])
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
     public function create(){
          if (!auth()->user()->canPermission('meeting_types.create')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view(
               'configurations.meeting_types.partials.form'
          );
     }

     public function store(Request $request) {
          if (!auth()->user()->canPermission('meeting_types.create')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validator = Validator::make(
               $request->all(),
               [
                    'name' => ['required','string','max:255','unique:meeting_types,name'],
                    'slug' => ['nullable','string','max:255','unique:meeting_types,slug'],
                    'description' => ['nullable','string'],
                    'icon' => ['nullable','string','max:255'],
                    'color' => ['nullable','string','max:50'],
                    
                    'default_duration' => ['required','integer','min:15'],
                    'min_participants' => ['required','integer','min:1'],
                    'max_participants' => ['required','integer','min:1'],

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

          $meetingType = MeetingType::create([
               'name' => $request->name,
               'slug' => $slug,
               'description' => $request->description,
               'icon' => $request->icon,
               'color' => $request->color,
               'default_duration' => $request->default_duration ?? 15,
               'min_participants' => $request->min_participants ?? 1,
               'max_participants' => $request->max_participants ?? 1,
               'sort_order' => $request->sort_order ?? 0,
               'is_active' => $request->boolean('is_active',true),
               'created_by' => auth()->id(),
               'updated_by' => auth()->id(),
          ]);

          return response()->json([
               'success' => true,
               'message' => 'Meeting type created successfully',
               'data' => $meetingType
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Edit
     |--------------------------------------------------------------------------
     */
     public function edit(MeetingType $meetingType) {
          if (!auth()->user()->canPermission('meeting_types.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view(
               'configurations.meeting_types.partials.form',compact('meetingType')
          );
     }

     public function update(Request $request,MeetingType $meetingType) {
          if (!auth()->user()->canPermission('meeting_types.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validator = Validator::make(
               $request->all(),
               [
                    'name' => ['required','string','max:255','unique:meeting_types,name,' . $meetingType->id],
                    'slug' => ['nullable','string','max:255','unique:meeting_types,slug,' . $meetingType->id],
                    'description' => ['nullable','string'],
                    'icon' => ['nullable','string','max:255'],
                    'color' => ['nullable','string','max:50'],
                    
                    'default_duration' => ['required','integer','min:15'],
                    'min_participants' => ['required','integer','min:1'],
                    'max_participants' => ['required','integer','min:1'],

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

          $meetingType->update([
               'name' => $request->name,
               'slug' => $slug,
               'description' => trim($request->description),
               'icon' => $request->icon,
               'color' => $request->color,
               'default_duration' => $request->default_duration ?? 15,
               'min_participants' => $request->min_participants ?? 1,
               'max_participants' => $request->max_participants ?? 1,
               'sort_order' => $request->sort_order ?? 0,
               'is_active' => $request->boolean('is_active'),
               'updated_by' => auth()->id(),
          ]);

          return response()->json([
               'success' => true,
               'message' => 'Meeting type updated successfully',
               'data' => $meetingType
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Delete
     |--------------------------------------------------------------------------
     */
     public function destroy(MeetingType $meetingType){
          if (!auth()->user()->canPermission('meeting_types.delete')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $meetingType->delete();
          return response()->json([
               'success' => true,
               'message' => 'Meeting type deleted successfully'
          ]);
     }
}