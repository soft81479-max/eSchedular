<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class PermissionController extends Controller {
    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */
    public function index(): View {
          if (!auth()->user()->canPermission('permissions.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $stats = [
               'permissions' => Permission::count(),
               'modules'     => Module::count(),
               'active'      => Permission::where('is_active', true)->count(),
               'inactive'    => Permission::where('is_active', false)->count(),
          ];

          return view(
               'administration.permissions.index',
               compact('stats')
          );
    }

    /*
    |--------------------------------------------------------------------------
    | Datatable
    |--------------------------------------------------------------------------
    */
    public function datatable(Request $request): JsonResponse {
          if (!auth()->user()->canPermission('permissions.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          if ($request->ajax()) {
               $addButtonHtml = '';
               $selectStatusFilter  = '';
               $selectModuleFilter  = '';

               if (auth()->user()->canPermission('permissions.view')) {
                    $modules = Module::query()
                         ->select(['id', 'label'])
                         ->orderBy('label')
                         ->get();

                    $selectModuleFilter = '<select id="module_id" class="form-select form-select-sm">';
                    $selectModuleFilter .= '<option value="">All Modules</option>';

                    foreach ($modules as $module) {
                         $selectModuleFilter .= sprintf(
                              '<option value="%s">%s</option>',
                              $module->id,
                              e($module->label)
                         );
                    }
                    $selectModuleFilter .= '</select>';
                    
                    $addButtonHtml = action_button('add', [
                         'modal-size' => 'modal-md','label' => 'Add','title' => 'Add Permission','icon' => '','color' => 'warning',
                         'createUrl' => route('permissions.create'),
                         'storeUrl' => route('permissions.store'),
                         'table' => 'permissionsTable'
                    ]);

                    $selectStatusFilter = '
                         <select id="status" class="form-select form-select-sm">
                              <option value="">All Statuses</option>
                              <option value="1">Active</option>
                              <option value="0">Inactive</option>
                         </select>
                    ';
               }

               $query = Permission::query()
                    ->with('module')
                    ->withCount('roles')
                    ->join(
                         'modules',
                         'modules.id',
                         '=',
                         'permissions.module_id'
                    )
                    ->select('permissions.*')
                    ->orderBy('modules.label')
                    ->orderBy('permissions.label');

               /*
               |--------------------------------------------------------------------------
               | Filters
               |--------------------------------------------------------------------------
               */
               if ($request->filled('module_id')) {
                    $query->where(
                         'permissions.module_id',
                         $request->module_id
                    );
               }

               if ($request->filled('status')) {
                    $query->where(
                         'permissions.is_active',
                         (int) $request->status
                    );
               }

               return DataTables::eloquent($query)
                    /*
                    |--------------------------------------------------------------------------
                    | Module Name (Row Group)
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('module_name', function ($row) {
                         return $row->module?->label ?? 'Unknown';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Permission
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('permission_name', function ($row) {
                         return '
                              <div class="d-flex align-items-center lh-1">
                                   <div class="me-2">
                                        <span class="badge bg-primary p-1"><i class="ph-key ph-sm"></i></span>
                                   </div>
                                   <div>
                                        <div class="fw-semibold small mb-1">'.e($row->label).'</div>
                                        <div class="text-muted fs-xs">'.e($row->slug).'</div>
                                   </div>
                              </div>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Usage
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('usage', function ($row) {
                         return '
                              <span class="badge bg-yellow bg-opacity-10 border-start border-width-3 fs-xs text-body rounded-start-0 border-yellow">
                                   '.ucfirst($row->action).'
                              </span>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Status
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('permission_status', function ($row) {
                         return $row->is_active
                              ? '<span class="badge fs-xs bg-success">Active</span>'
                              : '<span class="badge fs-xs bg-secondary">Inactive</span>';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Search Support
                    |--------------------------------------------------------------------------
                    */
                    ->filterColumn('module_name', function ($query, $keyword) {
                         $query->whereHas('module', function ($query) use ($keyword) {
                              $query->where(
                                   'label',
                                   'like',
                                   "%{$keyword}%"
                              );
                         });
                    })


                    /*
                    |--------------------------------------------------------------------------
                    | Actions
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('actions', function ($row) {
                         $actions = '';
                         if (auth()->user()->canPermission('permissions.view')) {
                              $actions .= '
                                   <a href="'.route('permissions.show', $row).'" class="dropdown-item"><i class="ph-eye me-2"></i>
                                        View Permission
                                   </a>';
                         }

                         if (auth()->user()->canPermission('permissions.manage')) {
                              $actions .= '<a href="#" class="dropdown-item" data-action="edit" data-title="Edit Permission" data-bs-toggle="modal" data-bs-target="#modal-dialog" 
                                   data-modal-size="modal-md" data-table-reload="permissionsTable" data-edit-url="'.route('permissions.edit', $row).'" data-update-url="'.route('permissions.update', $row).'">
                                   <i class="ph-pencil-line me-2"></i>
                                   Edit Permission
                              </a>';
                         }

                         return '
                              <div class="dropdown">
                                   <a href="#" class="text-body" data-bs-toggle="dropdown"> <i class="ph-list"></i></a>
                                   <div class="dropdown-menu dropdown-menu-end">'.$actions.'</div>
                              </div>
                         ';
                    })
                    ->rawColumns(['permission_name','usage','permission_status','actions'])
                    ->with('custom_meta', [
                         'button_html' => $addButtonHtml,
                    ])
                    ->with('statusFilter', [
                         'status_filter' => $selectStatusFilter,
                    ])
                    ->with('moduleFilter', [
                         'module_filter' => $selectModuleFilter,
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
          if (!auth()->user()->canPermission('permissions.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $modules = Module::query()
               ->where('is_active', true)
               ->orderBy('label')
               ->get();

          return view(
               'administration.permissions.partials.create',
               compact('modules')
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Store
     |--------------------------------------------------------------------------
     */
     public function store(Request $request): JsonResponse {
          if (!auth()->user()->canPermission('permissions.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validated = $request->validate([
               'module_id' => ['required','exists:modules,id',],
               'action' => ['required','string','max:100','regex:/^[a-z_]+$/i',],
               'label' => ['required','string','max:255',],
               'is_active' => ['required','boolean',],
          ]);

          $module = Module::findOrFail($validated['module_id']);
          $action = strtolower(trim($validated['action']));

          $slug = $module->slug.'.'.$action;

          if (Permission::where('slug', $slug)->exists()) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission already exists.',
               ], 422);
          }

          Permission::create([
               'module_id' => $module->id,
               'action' => $action,
               'label' => trim($validated['label']),
               'slug' => $slug,
               'is_active' => (bool) $validated['is_active'],
          ]);

          return response()->json([
               'success' => true,
               'message' => 'Permission created successfully.',
          ]);
     }

    /*
     |--------------------------------------------------------------------------
     | Show
     |--------------------------------------------------------------------------
     */
     public function show(Permission $permission): View {
          if (!auth()->user()->canPermission('permissions.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $permission->load([
               'module',
               'roles' => function ($query) {
                    $query->orderBy('label');
               },
          ]);

          return view(
               'administration.permissions.show',
               compact('permission')
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Edit
     |--------------------------------------------------------------------------
     */
     public function edit(Permission $permission): View {
          if (!auth()->user()->canPermission('permissions.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $modules = Module::query()
               ->where('is_active', true)
               ->orderBy('label')
               ->get();

          return view(
               'administration.permissions.partials.edit',
               compact(
                    'permission',
                    'modules'
               )
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Update
     |--------------------------------------------------------------------------
     */
     public function update(Request $request,Permission $permission): JsonResponse {
          if (!auth()->user()->canPermission('permissions.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validated = $request->validate([
               'label' => ['required','string','max:255',],
               'is_active' => ['required','boolean',],
          ]);

          $corePermissions = [
               'users.view',
               'users.manage',
               'roles.view',
               'roles.manage',
               'permissions.view',
               'permissions.manage',
          ];

          if (in_array($permission->slug, $corePermissions, true) && !$validated['is_active']) {
               return response()->json([
                    'success' => false,
                    'message' => 'Core permissions cannot be deactivated.',
               ], 422);
          }

          $permission->update([
               'label'     => $validated['label'],
               'is_active' => (bool) $validated['is_active'],
          ]);

          return response()->json([
               'success' => true,
               'message' => 'Permission updated successfully.',
          ]);
     }
}