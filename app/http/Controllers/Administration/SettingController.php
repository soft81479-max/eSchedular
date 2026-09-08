<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class SettingController extends Controller {
     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     */
     public function index(): View {
          if (!auth()->user()->canPermission('settings.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view('administration.settings.index');
     }

     /*
     |--------------------------------------------------------------------------
     | Datatable
     |--------------------------------------------------------------------------
     */
     public function datatable(Request $request): JsonResponse {
          if (!auth()->user()->canPermission('settings.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          if ($request->ajax()) {
               $addButtonHtml = '';
               $selectStatusFilter  = '';
               $selectGroupFilter = '';

               $canCreate = canPermission('settings.view');

               if ($canCreate) {
                    $addButtonHtml = action_button('add', [
                         'modal-size' => 'modal-md','label' => 'Add','title' => 'Add Setting','icon' => '','color' => 'warning',
                         'createUrl' => route('settings.create'),
                         'storeUrl' => route('settings.store'),
                         'table' => 'settingsTable'
                    ]);

                    $selectStatusFilter = '
                         <select id="locked" class="form-select form-select-sm">
                              <option value="">All Statuses</option>
                              <option value="1">Active</option>
                              <option value="0">Inactive</option>
                         </select>
                    ';

                    $selectGroupFilter = '
                         <select id="group" class="form-select form-select-sm">
                              <option value="">All Groups</option>
                              <option value="General">General</option>
                              <option value="Branding">Branding</option>
                              <option value="Security">Security</option>
                              <option value="Mail">Mail</option>
                              <option value="System">System</option>
                         </select>
                    ';
               }

               $query = Setting::query()
                    ->orderBy('setting_group')
                    ->orderBy('sort_order');

               if ($request->filled('group')) {
                    $query->where(
                         'setting_group',
                         $request->group
                    );
               }

               if ($request->filled('locked')) {
                    $query->where(
                         'is_locked',
                         (int) $request->locked
                    );
               }

               return DataTables::eloquent($query)
                    ->addIndexColumn()
                    /*
                    |--------------------------------------------------------------------------
                    | Setting
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('setting_name', function ($row) {
                         return '
                              <div class="fw-semibold fs-md">'.$row->setting_key.'</div>
                              <div class="text-muted fs-xs">'.$row->description.'</div>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Group
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('setting_group_name', function ($row) {
                         return $row->setting_group;
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Type
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('setting_type_name', function ($row) {
                         return '
                              <span class="badge fs-xs bg-primary w-100">'.ucfirst($row->setting_type).'</span>
                         ';
                    })
                    /*
                    |--------------------------------------------------------------------------
                    | Value
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('setting_value_display', function ($row) {
                         if ($row->setting_type === 'boolean') {
                              return $row->setting_value
                                   ? '<span class="badge fs-xs w-100 bg-success text-white">Enabled</span>'
                                   : '<span class="badge fs-xs w-100 bg-light border text-body">Disabled</span>';
                         }

                         return e(
                              \Illuminate\Support\Str::limit($row->setting_value,50)
                         );
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Status
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('setting_status', function ($row) {
                         return $row->is_locked
                              ? '<span class="badge fs-xs w-100 bg-warning">Locked</span>'
                              : '<span class="badge fs-xs w-100 bg-success">Editable</span>';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Actions
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('actions', function ($row) {
                         $actions = '';

                         if (canPermission('settings.view')) {
                              $actions .= '
                                   <a href="#" class="dropdown-item" data-action="viewModal" data-title="View Setting" data-bs-toggle="modal"
                                        data-bs-target="#modal-dialog" data-modal-size="modal-md" data-table-reload="settingsTable"
                                        data-url="'.route('settings.show', $row).'"> <i class="ph-eye me-2"></i>View Setting
                                   </a>
                              ';
                         }

                         if (canPermission('settings.manage') && ! $row->is_locked) {
                              $actions .= '
                                   <a href="#" class="dropdown-item" data-action="edit" data-title="Edit Setting" data-bs-toggle="modal"
                                        data-bs-target="#modal-dialog" data-modal-size="modal-md" data-table-reload="settingsTable" 
                                        data-edit-url="'.route('settings.edit', $row).'" data-update-url="'.route('settings.update', $row).'">
                                        <i class="ph-pencil-line me-2"></i>Edit Setting
                                   </a>
                              ';
                         }

                         return '
                              <div class="dropdown">
                                   <a href="#" class="text-body" data-bs-toggle="dropdown"><i class="ph-list"></i></a>
                                   <div class="dropdown-menu dropdown-menu-end">'.$actions.'</div>
                              </div>
                         ';
                    })
                    ->rawColumns(['setting_name','setting_group_name','setting_type_name','setting_value_display','setting_status','actions',])
                    ->with('custom_meta', [
                         'button_html' => $addButtonHtml,
                    ])
                    ->with('statusFilter', [
                         'status_filter' => $selectStatusFilter,
                    ])
                    ->with('groupFilter', [
                         'group_filter' => $selectGroupFilter,
                    ])
                    ->make(true);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Create
     |--------------------------------------------------------------------------
     */
     public function create(): View {
          if (!auth()->user()->canPermission('settings.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $timezones = \DateTimeZone::listIdentifiers();
          return view('administration.settings.partials.form',compact(
               'timezones'
          ));
     }

     /*
     |--------------------------------------------------------------------------
     | Store
     |--------------------------------------------------------------------------
     */
     public function store(Request $request): JsonResponse {
          if (!auth()->user()->canPermission('settings.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validated = $request->validate([
               'setting_group' => ['required', 'string', 'max:100'],
               'setting_key'   => ['required', 'string', 'max:150', 'unique:settings,setting_key'],
               'setting_value' => ['nullable'],
               'setting_type'  => ['required', 'string'],
               'description'   => ['nullable', 'string', 'max:255'],
               'is_public'     => ['nullable', 'boolean'],
               'is_locked'     => ['nullable', 'boolean'],
               'sort_order'    => ['nullable', 'integer'],
          ]);

          Setting::create([
               ...$validated,
               'is_public' => $request->boolean('is_public'),
               'is_locked' => $request->boolean('is_locked'),
          ]);

          return response()->json([
               'success' => true,
               'message' => 'Setting created successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Show
     |--------------------------------------------------------------------------
     */
     public function show(Setting $setting): View {
          if (!auth()->user()->canPermission('settings.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view('administration.settings.partials.show', [
               'setting' => $setting,
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Edit
     |--------------------------------------------------------------------------
     */
     public function edit(Setting $setting): View {
          if (!auth()->user()->canPermission('settings.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $timezones = \DateTimeZone::listIdentifiers();

          return view('administration.settings.partials.form', [
               'setting' => $setting,
               'timezones' => $timezones
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Update
     |--------------------------------------------------------------------------
     */
     public function update(Request $request, Setting $setting): JsonResponse {
          if (!auth()->user()->canPermission('settings.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validated = $request->validate([
               'setting_group' => ['required', 'string', 'max:100'],
               'setting_key'   => [
                    'required',
                    'string',
                    'max:150',
                    'unique:settings,setting_key,' . $setting->id,
               ],
               'setting_value' => ['nullable'],
               'setting_type'  => ['required', 'string'],
               'description'   => ['nullable', 'string', 'max:255'],
               'sort_order'    => ['nullable', 'integer'],
          ]);

          $setting->update([
               ...$validated,
               'is_public' => $request->boolean('is_public'),
               'is_locked' => $request->boolean('is_locked'),
          ]);

          return response()->json([
               'success' => true,
               'message' => 'Setting updated successfully.',
          ]);
     }
}