<?php

namespace App\Http\Controllers\Configurations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Str;

use App\Models\NetworkingMode;

class NetworkingModeController extends Controller {
     /*
     |--------------------------------------------------------------------------
     | Listing
     |--------------------------------------------------------------------------
     */
     public function index() {
          if (!auth()->user()->canPermission('networking_modes.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view('configurations.networking_modes.index');
     }

     public function datatable(Request $request) {
          if (!auth()->user()->canPermission('networking_modes.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          if ($request->ajax()) {
               $addButtonHtml = '';
               if (auth()->user()->canPermission('networking_modes.create')) {
                    $addButtonHtml = action_button('add', [
                         'modal-size' => 'modal-md',
                         'label' => 'ADD',
                         'title' => 'Add Networking Mode',
                         'icon' => '',
                         'color' => 'warning',
                         'createUrl' => route('networking-modes.create'), 
                         'storeUrl' => route('networking-modes.store'),   
                         'table' => 'networkingModesTable'                
                    ]);
               }
               
               $query = NetworkingMode::query()->orderBy('sort_order')->orderBy('name');
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
                         $canEdit   = auth()->user()->canPermission('networking_modes.edit');
                         $canDelete = auth()->user()->canPermission('networking_modes.delete');

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
                                   'title' => 'Edit Networking Mode',
                                   'icon' => 'ph-pencil-line',
                                   'color' => 'primary',
                                   'editUrl' => route('networking-modes.edit',$row->id),
                                   'updateUrl' => route('networking-modes.update',$row->id),
                                   'table' => 'networkingModesTable'
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
                                   'title' => 'Delete Networking Mode',
                                   'icon' => 'ph-trash',
                                   'color' => 'danger',
                                   'url' => route('networking-modes.destroy',$row->id),
                                   'method' => 'DELETE',
                                   'table' => 'networkingModesTable'
                              ]);
                         }

                         return '
                              <div class="d-flex justify-content-center align-items-center gap-1">' . $buttons . '</div>
                         ';
                    })
                    ->rawColumns(['name','description','status','sort','created','actions'])
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
          if (!auth()->user()->canPermission('networking_modes.create')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view(
               'configurations.networking_modes.partials.form'
          );
     }

     public function store(Request $request) {
          if (!auth()->user()->canPermission('networking_modes.create')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validator = Validator::make(
               $request->all(),
               [
                    'name' => ['required','string','max:255','unique:networking_modes,name'],
                    'slug' => ['nullable','string','max:255','unique:networking_modes,slug',],
                    'description' => ['nullable','string'],
                    'icon' => ['nullable','string','max:255'],
                    'color' => ['nullable','string','max:50'],
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

          $networkingMode = NetworkingMode::create([
               'name' => $request->name,
               'description' => $request->description,
               'slug' => $slug,
               'icon' => $request->icon,
               'color' => $request->color,
               'sort_order' => $request->sort_order ?? 0,
               'is_active' => $request->boolean('is_active',true),
               'created_by' => auth()->id(),
               'updated_by' => auth()->id(),
          ]);

          return response()->json([
               'success' => true,
               'message' => 'Networking mode created successfully.',
               'data' => $networkingMode
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Edit
     |--------------------------------------------------------------------------
     */
     public function edit(NetworkingMode $networkingMode) {
          if (!auth()->user()->canPermission('networking_modes.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view(
               'configurations.networking_modes.partials.form',compact('networkingMode')
          );
     }

     public function update(Request $request,NetworkingMode $networkingMode) {
          if (!auth()->user()->canPermission('networking_modes.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validator = Validator::make(
               $request->all(),
               [
                    'name' => ['required','string','max:255','unique:networking_modes,name,' . $networkingMode->id],
                    'slug' => ['required','string','max:255','unique:networking_modes,slug,' . $networkingMode->id],
                    'description' => ['nullable','string'],
                    'icon' => ['nullable','string','max:255'],
                    'color' => ['nullable','string','max:50'],
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

          $networkingMode->update([
               'name' => $request->name,
               'description' => $request->description,
               'slug' => $slug,
               'icon' => $request->icon,
               'color' => $request->color,
               'sort_order' => $request->sort_order ?? 0,
               'is_active' => $request->boolean('is_active'),
               'updated_by' => auth()->id(),
          ]);

          return response()->json([
               'success' => true,
               'message' => 'Networking mode updated successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Delete
     |--------------------------------------------------------------------------
     */
     public function destroy(NetworkingMode $networkingMode){
          if (!auth()->user()->canPermission('networking_modes.delete')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $networkingMode->delete();
          return response()->json([
               'success' => true,
               'message' => 'Networking mode deleted successfully.',
          ]);
     }
}