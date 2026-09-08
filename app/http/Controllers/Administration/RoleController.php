<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;
use App\Models\Role;
use App\Models\Menu;
use App\Models\Module;

class RoleController extends Controller {
     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     */
     public function index() {
          if (!auth()->user()->canPermission('roles.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view('administration.roles.index');
     }

     /*
     |--------------------------------------------------------------------------
     | Datatable
     |--------------------------------------------------------------------------
     */
     public function datatable(Request $request) {
          if (!auth()->user()->canPermission('roles.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          if ($request->ajax()) {
               $addButtonHtml = '';
               $selectStatusFilter  = '';

               if (auth()->user()->canPermission('roles.view')) {
                    $addButtonHtml = action_button('add', [
                         'modal-size' => 'modal-md','label' => 'Add','title' => 'Add Role','icon' => '','color' => 'warning',
                         'createUrl' => route('roles.create'),
                         'storeUrl' => route('roles.store'),
                         'table' => 'rolesTable'
                    ]);

                    $selectStatusFilter = '
                         <select id="status" class="form-select form-select-sm">
                              <option value="">All Statuses</option>
                              <option value="1">Active</option>
                              <option value="0">Inactive</option>
                         </select>
                    ';
               }               

               $query = Role::query()->withCount(['users','permissions','menus',])->orderBy('label');

               if ($request->filled('status')) {
                    $query->where('is_active',$request->status);
               }

               return DataTables::eloquent($query)
                    ->addIndexColumn()
                    /*
                    |--------------------------------------------------------------------------
                    | Role
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('role_name', function ($row) {
                         $icon = $row->name === 'super_admin' ? '<i class="ph-shield-star ph-sm"></i>' : '<i class="ph-shield-check ph-sm"></i>';
                         $bgColor = $row->name === 'super_admin' ? 'warning' : 'purple';
                         return '
                              <div class="d-flex align-items-center">
                                   <div class="me-2">
                                        <span class="badge bg-'.$bgColor.' p-2">'.$icon.'</span>
                                   </div>
                                   <div>
                                        <div class="fw-semibold fs-md">'.$row->label.'</div>
                                        <div class="text-muted fs-xs">'.$row->name.'</div>
                                   </div>
                              </div>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Access
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('access', function ($row) {
                         return '
                              <div>
                                   <div>
                                        <span class="badge bg-danger bg-opacity-10 border-end border-start border-width-3 fw-semibold fs-sm text-body rounded border-danger w-100">'.$row->permissions_count.' <small class="fw-normal fs-xs me-1">Permissions</small>|<span class="ms-1">'.$row->menus_count.'</span> <small class="fw-normal fs-xs">Menus</small></span>
                                   </div>
                                   <div class="text-muted fs-xs ms-1">'.$row->users_count.' ' . ($row->users_count > 9 ? 'Users' : 'User') .'</div>
                              </div>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Status
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('role_status', function ($row) {
                         return $row->is_active
                              ? '<span class="badge fs-xs bg-success">Active</span>'
                              : '<span class="badge fs-xs bg-secondary">Inactive</span>';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Created
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('created', function ($row) {
                         return '
                              <div class="fw-semibold fs-sm">'.($row->created_at?->format('d M Y') ?? '-').'</div>
                              <div class="text-muted fs-xs">
                                   '.($row->created_at?->format('h:i A') ?? '').'
                              </div>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Actions
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('actions', function ($row) {
                         $actions = '';

                         if (auth()->user()->canPermission('roles.view')) {
                              $actions .= '<a href="'.route('roles.show', $row).'" class="dropdown-item">
                                   <i class="ph-eye me-2"></i>
                                   View Role
                              </a>';
                         }

                         if (auth()->user()->canPermission('roles.manage')) {
                              $actions .= '<a href="#" class="dropdown-item" data-action="edit" data-title="Edit Role" data-bs-toggle="modal" data-bs-target="#modal-dialog" 
                                   data-modal-size="modal-md" data-table-reload="rolesTable" data-edit-url="'.route('roles.edit', $row).'" data-update-url="'.route('roles.update', $row).'">
                                   <i class="ph-pencil-line me-2"></i>
                                   Edit Role
                              </a>';

                              $actions .= '<div class="dropdown-divider"></div>';

                              $actions .= '<a href="'.route('roles.permissions', $row).'" class="dropdown-item">
                                   <i class="ph-shield-check me-2"></i>
                                   Manage Permissions
                              </a>';

                              $actions .= '<a href="'.route('roles.menus', $row).'" class="dropdown-item">
                                   <i class="ph-list-checks me-2"></i>
                                   Manage Menus
                              </a>';
                         }

                         return '
                              <div class="dropdown">
                                   <a href="#" class="text-body" data-bs-toggle="dropdown"><i class="ph-list"></i></a>
                                   <div class="dropdown-menu dropdown-menu-end">
                                        '.$actions.'
                                   </div>
                              </div>
                         ';
                    })                    
                    ->rawColumns(['role_name','access','role_status','created','actions'])
                    ->with('custom_meta', [
                         'button_html' => $addButtonHtml,
                    ])
                    ->with('statusFilter', [
                         'status_filter' => $selectStatusFilter,
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
          if (!auth()->user()->canPermission('roles.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

         return view('administration.roles.partials.create');
     }

     /*
     |--------------------------------------------------------------------------
     | Store
     |--------------------------------------------------------------------------
     */
     public function store(Request $request) {
          if (!auth()->user()->canPermission('roles.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validated = $request->validate([
               'name' => ['required','alpha_dash','max:100','unique:roles,name',],
               'label' => ['required','string','max:255',],
          ]);

          Role::create([
               'name'      => strtolower($validated['name']),
               'label'     => $validated['label'],
               'is_active' => $request->boolean('is_active'),
          ]);

          return response()->json([
               'success' => true,
               'message' => 'Role created successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Show
     |--------------------------------------------------------------------------
     */
     public function show(Role $role) {
          if (!auth()->user()->canPermission('roles.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $role->loadCount(['users','permissions',]);

          return view('administration.roles.show',compact('role'));
     }

     /*
     |--------------------------------------------------------------------------
     | Edit
     |--------------------------------------------------------------------------
     */
     public function edit(Role $role) {
          if (!auth()->user()->canPermission('roles.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view('administration.roles.partials.edit',compact('role'));
     }

     /*
     |--------------------------------------------------------------------------
     | Update
     |--------------------------------------------------------------------------
     */
     public function update(Request $request,Role $role) {
          if (!auth()->user()->canPermission('roles.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validated = $request->validate([
               'name' => ['required','alpha_dash','max:100',
                    Rule::unique('roles', 'name')->ignore($role->id),
               ],
               'label' => ['required','string','max:255',],
               'is_active' => ['required','boolean',],
          ]);

          /*
          |--------------------------------------------------------------------------
          | Protect System Role
          |--------------------------------------------------------------------------
          */
          if ($role->name === 'super_admin') {
               $role->update([
                    'label'     => $validated['label'],
                    'is_active' => (bool) $validated['is_active'],
               ]);
          } else {
               $role->update([
                    'name'      => strtolower($validated['name']),
                    'label'     => $validated['label'],
                    'is_active' => (bool) $validated['is_active'],
               ]);
          }

          return response()->json([
               'success' => true,
               'message' => 'Role updated successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Permissions
     |--------------------------------------------------------------------------
     */
     public function permissions(Role $role) {
          if (!auth()->user()->canPermission('roles.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $role->load([
               'permissions',
          ])->loadCount([
               'users',
               'permissions',
          ]);

          $modules = Module::query()
               ->with([
                    'permissions' => fn ($query) => $query
                         ->where('is_active', true)
                         ->orderBy('label')
               ])
               ->where('is_active', true)
               ->orderBy('label')
               ->get();

          $rolePermissionIds = $role->permissions
               ->pluck('id')
               ->toArray();

          return view(
               'administration.roles.permissions',
               [
                    'role'              => $role,
                    'modules'           => $modules,
                    'rolePermissionIds' => $rolePermissionIds,
               ]
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Update Permissions
     |--------------------------------------------------------------------------
     */
     public function updatePermissions(Request $request,Role $role) {
          if (!auth()->user()->canPermission('roles.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $permissionIds = collect($request->permissions ?? [])
               ->map(fn ($id) => (int) $id)
               ->unique()
               ->values()
               ->toArray();

          $role->permissions()->sync(
               $permissionIds
          );

          return redirect()
               ->route('roles.permissions',$role)
               ->with(
                    'success',
                    'Role permissions updated successfully.'
               );
     }

     /*
     |--------------------------------------------------------------------------
     | Menus
     |--------------------------------------------------------------------------
     */
     public function menus(Role $role): View {
          if (!auth()->user()->canPermission('roles.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $role->loadCount([
               'activeUsers',
               'permissions',
          ]);

          $menus = Menu::query()
               ->with([
                    'children' => function ($query) {
                         $query->active()
                              ->orderBy('sort_order');
                    }
               ])
               ->whereNull('parent_id')
               ->active()
               ->orderBy('sort_order')
               ->get();

          $assignedMenus = $role->menus()
               ->pluck('menus.id')
               ->toArray();

          $assignedMenusCount = $role->menus()
               ->clickable()
               ->count();

          return view('administration.roles.menus', [
               'role'               => $role,
               'menus'              => $menus,
               'assignedMenus'      => $assignedMenus,
               'assignedMenusCount' => $assignedMenusCount,
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Update Role Menus
     |--------------------------------------------------------------------------
     */
     public function updateMenus(Request $request, Role $role): RedirectResponse {
          if (!auth()->user()->canPermission('roles.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          if (strtolower($role->name) === 'super_admin') {
               return back()->with(
                    'error',
                    'Administrator menus cannot be modified.'
               );
          }

          $validated = $request->validate([
               'menu_ids'   => ['nullable', 'array'],
               'menu_ids.*' => [
                    'integer',
                    Rule::exists('menus', 'id')
                         ->where('is_clickable', true),
               ],
          ]);

          $role->menus()->sync(
               $validated['menu_ids'] ?? []
          );

          return redirect()
               ->route('roles.menus', $role)
               ->with(
                    'success',
                    'Role menus updated successfully.'
               );
     }
}