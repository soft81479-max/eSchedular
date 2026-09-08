<?php

namespace App\Http\Controllers\Configurations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Str;

use App\Models\ParticipantType;

class ParticipantTypeController extends Controller {
     /*
     |--------------------------------------------------------------------------
     | Listing
     |--------------------------------------------------------------------------
     */

     public function index() {
          if (!auth()->user()->canPermission('participant_types.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view('configurations.participant_types.index');
     }

     public function datatable(Request $request) {
          if (!auth()->user()->canPermission('participant_types.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          if ($request->ajax()) {
               $addButtonHtml = '';
               if (auth()->user()->canPermission('participant_types.view')) {
                    $addButtonHtml = action_button('add', [
                         'modal-size' => 'modal-md',
                         'label' => 'ADD',
                         'title' => 'Add Participant Type',
                         'icon' => '',
                         'color' => 'warning',
                         'createUrl' => route('participant-types.create'), 
                         'storeUrl' => route('participant-types.store'),   
                         'table' => 'participantTypesTable'                
                    ]);
               }
               
               $query = ParticipantType::query()->orderBy('priority_level')->orderBy('sort_order')->orderBy('name');
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
                    | Can Initiate Meetings
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('can_initiate_meetings', function ($row) {
                         return '                         
                              <span class="badge fs-xs bg-info">
                                   <i class="ph-arrow-bend-double-up-right ph-sm"></i>
                              </span>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Can Receive Meetings
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('can_receive_meetings', function ($row) {
                         return '                         
                              <span class="badge fs-xs bg-teal">
                                   <i class="ph-arrow-bend-double-up-left ph-sm"></i>
                              </span>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Priority
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('priority_level', function ($row) {
                         return '                         
                              <span class="badge fs-xs bg-warning">
                                   ' . e($row->priority_level ?: 0) . '
                              </span>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Max Daily Meetings
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('max_daily_meetings', function ($row) {
                         return '
                              <div class="d-flex align-items-center">
                                   <div class="me-2">
                                        <span class="badge bg-purple">
                                             <i class="ph-arrows-left-right ph-sm"></i>
                                        </span>
                                   </div>
                                   <div>
                                        <div class="fw-semibold fs-sm">' . e($row->max_daily_meetings ?: 0) . '</div>
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
                         $canEdit   = auth()->user()->canPermission('participant_types.edit');
                         $canDelete = auth()->user()->canPermission('participant_types.delete');

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
                                   'title' => 'Edit Participant Type',
                                   'icon' => 'ph-pencil-line',
                                   'color' => 'primary',
                                   'editUrl' => route('participant-types.edit',$row->id),
                                   'updateUrl' => route('participant-types.update',$row->id),
                                   'table' => 'participantTypesTable'
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
                                   'title' => 'Delete Participant Type',
                                   'icon' => 'ph-trash',
                                   'color' => 'danger',
                                   'url' => route('participant-types.destroy',$row->id),
                                   'method' => 'DELETE',
                                   'table' => 'participantTypesTable'
                              ]);
                         }

                         return '
                              <div class="d-flex justify-content-center align-items-center gap-1">' . $buttons . '</div>
                         ';
                    })
                    ->rawColumns(['name','description','can_initiate_meetings','can_receive_meetings','priority_level','max_daily_meetings','status','sort','created','actions'])
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
          if (!auth()->user()->canPermission('participant_types.create')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }
          
          return view(
               'configurations.participant_types.partials.form'
          );
     }

     public function store(Request $request) {
          if (!auth()->user()->canPermission('participant_types.create')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validator = Validator::make(
               $request->all(),
               [
                    'name' => ['required','string','max:255','unique:participant_types,name'],
                    'slug' => ['nullable','string','max:255','unique:participant_types,slug'],
                    'description' => ['nullable','string'],
                    'icon' => ['nullable','string','max:255'],
                    'color' => ['nullable','string','max:50'],
                    
                    'can_initiate_meetings' => ['required','boolean'],
                    'can_receive_meetings' => ['required','boolean'],
                    'priority_level' => ['required','integer','min:1'],
                    'max_daily_meetings' => ['nullable','integer','min:0'],

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

          $participantType = ParticipantType::create([
               'name' => $request->name,
               'slug' => $slug,
               'description' => $request->description,
               'icon' => $request->icon,
               'color' => $request->color,

               'can_initiate_meetings' => $request->boolean('can_initiate_meetings',true),
               'can_receive_meetings' => $request->boolean('can_receive_meetings',true),
               'priority_level' => $request->priority_level ?? 1,
               'max_daily_meetings' => $request->max_daily_meetings ?? 0,

               'sort_order' => $request->sort_order ?? 0,
               'is_active' => $request->boolean('is_active',true),
               'created_by' => auth()->id(),
               'updated_by' => auth()->id(),
          ]);

          return response()->json([
               'success' => true,
               'message' => 'Participant type created successfully',
               'data' => $participantType
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Edit
     |--------------------------------------------------------------------------
     */
     public function edit(ParticipantType $participantType) {
          if (!auth()->user()->canPermission('participant_types.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view(
               'configurations.participant_types.partials.form',compact('participantType')
          );
     }

     public function update(Request $request,ParticipantType $participantType) {
          if (!auth()->user()->canPermission('participant_types.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validator = Validator::make(
               $request->all(),
               [
                    'name' => ['required','string','max:255','unique:participant_types,name,' . $participantType->id],
                    'slug' => ['nullable','string','max:255','unique:participant_types,slug,' . $participantType->id],
                    'description' => ['nullable','string'],
                    'icon' => ['nullable','string','max:255'],
                    'color' => ['nullable','string','max:50'],
                    
                    'can_initiate_meetings' => ['required','boolean'],
                    'can_receive_meetings' => ['required','boolean'],
                    'priority_level' => ['required','integer','min:1'],
                    'max_daily_meetings' => ['nullable','integer','min:0'],

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

          $participantType->update([
               'name' => $request->name,
               'slug' => $slug,
               'description' => $request->description,
               'icon' => $request->icon,
               'color' => $request->color,

               'can_initiate_meetings' => $request->boolean('can_initiate_meetings'),
               'can_receive_meetings' => $request->boolean('can_receive_meetings'),
               'priority_level' => $request->priority_level ?? 1,
               'max_daily_meetings' => $request->max_daily_meetings ?? 0,

               'sort_order' => $request->sort_order ?? 0,
               'is_active' => $request->boolean('is_active'),
               'updated_by' => auth()->id(),
          ]);

          return response()->json([
               'success' => true,
               'message' => 'Participant type Update successfully'
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Delete
     |--------------------------------------------------------------------------
     */
     public function destroy(ParticipantType $participantType){
          if (!auth()->user()->canPermission('participant_types.delete')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $participantType->delete();
          return response()->json([
               'success' => true,
               'message' => 'Meeting type deleted successfully'
          ]);
     }
}