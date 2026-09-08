<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Helpers\FileUploadHelper;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Str;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\UserOverride;

class UserController extends Controller {
    /*
    |--------------------------------------------------------------------------
    | Listing
    |--------------------------------------------------------------------------
    */
    public function index() {
        if (!auth()->user()->canPermission('users.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }

        return view('administration.users.index');
    }

    public function datatable(Request $request) {
        if (!auth()->user()->canPermission('users.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }

        if ($request->ajax()) {
            $addButtonHtml = '';
            $selectStatusFilter  = '';
            $selectRoleFilter  = '';

            if (auth()->user()->canPermission('users.view')) {
                $roles = Role::query()->select(['id', 'label'])->orderBy('label')->get();
                $selectRoleFilter = '<select id="role_id" class="form-select form-select-sm">';
                $selectRoleFilter .= '<option value="">All Roles</option>';
                
                foreach ($roles as $role) {
                    $selectRoleFilter .= sprintf('<option value="%s">%s</option>',$role->id,e($role->label));
                }
                $selectRoleFilter .= '</select>';

                $addButtonHtml = action_button('add', [
                    'modal-size' => 'modal-md','label' => 'Add','title' => 'Add User','icon' => '','color' => 'warning',
                    'createUrl' => route('users.create'),
                    'storeUrl' => route('users.store'),
                    'table' => 'usersTable'
                ]);

                $selectStatusFilter = '
                    <select id="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="locked">Locked</option>
                        <option value="suspended">Suspended</option>
                    </select>
                ';
            }

            $query = User::query()
                ->select(['id','ulid','avatar','name','email','status','last_login_at',])
                ->with(['roles.rolePermissions',])
                ->withCount('userOverrides')
                ->orderBy('name');

                if ($request->filled('status')) {
                    $query->where('status', $request->status);
                }

                if ($request->filled('role_id')) {
                    $query->whereHas('roles', function ($query) use ($request) {
                        $query->where('roles.id', $request->role_id);
                    });
                }

            return DataTables::of($query)
                ->addIndexColumn()
                /*
                |--------------------------------------------------------------------------
                | User
                |--------------------------------------------------------------------------
                */
                ->addColumn('user_name', function ($row) {
                    return '
                        <div class="d-flex align-items-center">
                            <div class="me-2">
                                <img src="'.$row->avatar_url.'" class="rounded-circle" width="36" height="36" style="object-fit:cover;">
                            </div>
                            <div>
                                <div class="fw-semibold">'.e($row->name).'</div>
                                <div class="text-muted fs-sm">'.e($row->email).'</div>
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
                    $role = $row->roles->first();
                    $roleName = $role?->label ?? 'No Role';
                    $menuCount = $role ? $role->rolePermissions->count() : 0;
                    $overrideCount = $row->user_overrides_count ?? 0;

                    return '
                        <div class="fw-semibold">'.e(ucfirst($roleName)).'</div>
                        <div class="text-muted fs-xs">'.$menuCount.' Menus • '.$overrideCount.' Overrides</div>
                    ';
                })

                /*
                |--------------------------------------------------------------------------
                | Status
                |--------------------------------------------------------------------------
                */
                ->addColumn('user_status', function ($row) {
                    return '
                        <span class="badge bg-'.$row->status_color.'">
                            '.ucfirst($row->status).'
                        </span>
                    ';
                })

                /*
                |--------------------------------------------------------------------------
                | Activity
                |--------------------------------------------------------------------------
                */
                ->addColumn('activity', function ($row) {
                    if (!$row->last_login_at) {
                        return '<span class="text-muted">Never Logged In</span>';
                    }

                    return '
                        <div class="fw-semibold fs-sm">'.$row->last_login_at->format('d M Y').'</div>
                        <div class="text-muted fs-xs">'.$row->last_login_at->format('h:i A').'</div>
                    ';
                })

                /*
                |--------------------------------------------------------------------------
                | Actions
                |--------------------------------------------------------------------------
                */
                ->addColumn('actions', function ($row) {
                    $actions = '';

                    /*
                    |--------------------------------------------------------------------------
                    | View
                    |--------------------------------------------------------------------------
                    */
                    if (auth()->user()->canPermission('users.view')) {
                        $actions .= '<a href="'.route('users.show', $row).'" class="dropdown-item"><i class="ph-eye me-2"></i>View User</a>';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Edit
                    |--------------------------------------------------------------------------
                    */
                    if (auth()->user()->canPermission('users.manage')) {
                        $actions .= '<a href="#" class="dropdown-item" data-action="edit" data-title="Edit User" data-bs-toggle="modal" data-bs-target="#modal-dialog" 
                            data-modal-size="modal-md" data-table-reload="usersTable" data-edit-url="'.route('users.edit', $row).'" data-update-url="'.route('users.update', $row).'">
                            <i class="ph-pencil-line me-2"></i>
                            Edit User
                        </a>';
                    }

                    if (auth()->user()->canPermission('users.manage')) {
                        $actions .= '<div class="dropdown-divider"></div>';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Permissions
                    |--------------------------------------------------------------------------
                    */
                    if (auth()->user()->hasRole('super_admin') && auth()->user()->canPermission('users.manage')) {
                        $actions .= '
                        <a href="'.route('users.permissions', $row).'" class="dropdown-item"><i class="ph-shield-check me-2"></i>Manage Access</a>';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Reset Password
                    |--------------------------------------------------------------------------
                    */
                    if (auth()->user()->canPermission('users.manage')) {
                        $actions .= '<a href="#" class="dropdown-item" data-action="edit" data-title="Reset Password" data-bs-toggle="modal" data-bs-target="#modal-dialog" 
                            data-modal-size="modal-sm" data-table-reload="usersTable" data-edit-url="'.route('users.reset-password.form', $row).'"
                            data-update-url="'.route('users.reset-password', $row).'">
                                <i class="ph-key me-2"></i>
                                Reset Password
                        </a>';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Unlock Account
                    |--------------------------------------------------------------------------
                    */
                    if ($row->status === 'locked' && auth()->user()->canPermission('users.manage')) {
                        $actions .= '<a href="#" class="dropdown-item" data-action="unlock" data-table-reload="usersTable" data-url="'.route('users.unlock', $row).'">
                            <i class="ph-lock-open me-2 text-success"></i>
                            Unlock Account
                        </a>';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Force Logout
                    |--------------------------------------------------------------------------
                    */
                    if (auth()->user()->canPermission('users.manage')) {
                        $actions .= '<a href="#" class="dropdown-item" data-action="forceLogout" data-table-reload="usersTable" data-url="'.route('users.force-logout', $row).'">
                            <i class="ph-sign-out me-2"></i>
                            Force Logout
                        </a>';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Delete
                    |--------------------------------------------------------------------------
                    */
                    if (auth()->user()->canPermission('users.manage') && ! $row->hasRole('super_admin')) {
                        $actions .= '<div class="dropdown-divider"></div>';
                        $actions .= '<a href="#" class="dropdown-item" data-action="delete" data-method="DELETE" data-table-reload="usersTable" data-url="'.route('users.destroy', $row).'">
                            <i class="ph-trash me-2 text-danger"></i>
                            Delete User
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
                ->rawColumns(['user_name','access','user_status','activity','actions'])
                ->with('custom_meta', [
                    'button_html' => $addButtonHtml,
                ])
                ->with('statusFilter', [
                    'status_filter' => $selectStatusFilter,
                ])
                ->with('roleFilter', [
                    'role_filter' => $selectRoleFilter,
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
        if (!auth()->user()->canPermission('users.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }
        
        $roles = Role::query()
            ->where('is_active', true)
            ->orderBy('label')
            ->get(['id','label',]);

        $timezones = \DateTimeZone::listIdentifiers();

        return view('administration.users.partials.create', compact(
            'roles',
            'timezones'
        ));
    }

    public function store(Request $request) {
        if (!auth()->user()->canPermission('users.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }

        $validated = $request->validate([
            'avatar' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048',],
            'name' => ['required','string','max:150',],
            'email' => ['required','email:rfc,dns','max:255','unique:users,email',],
            'phone' => ['required','string','max:30','unique:users,phone',],
            'role_id' => ['required','exists:roles,id',],
            'status' => ['required',
                Rule::in(['active','inactive','locked','suspended',]),
            ],
            'timezone' => ['required',
                Rule::in(\DateTimeZone::listIdentifiers()),
            ],
            'password' => ['required','confirmed','min:8',],
        ]);

        DB::transaction(function () use ($request, $validated) {
            $avatar = null;
            if ($request->hasFile('avatar')) {
                $avatar = FileUploadHelper::upload(
                    $request->file('avatar'),
                    'users/avatars',
                    null,
                    'public',
                    ['image/jpeg', 'image/png', 'image/webp'],
                    2048,
                    200,
                    200,
                    'fit',
                    'avatar'
                );
            }

            $user = User::create([
                'avatar'   => $avatar,
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'phone'    => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'timezone' => $validated['timezone'],
                'status'   => $validated['status'],
            ]);

            UserRole::create([
                'user_id'   => $user->id,
                'role_id'   => $validated['role_id'],
                'is_system' => false,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */
    public function show(User $user) {
        if (!auth()->user()->canPermission('users.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }

        $user->load([
            'roles.roleMenus.menu',
            'roles.rolePermissions.permission',
            'userOverrides.permission',
        ]);

        $role = $user->roles->first();

        return view('administration.users.show', compact(
            'user',
            'role'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Edit
    |--------------------------------------------------------------------------
    */
    public function edit(User $user) {
        if (!auth()->user()->canPermission('users.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }

        $timezones = \DateTimeZone::listIdentifiers();

        return view('administration.users.partials.edit', compact('user','timezones'));
    }

    public function update(Request $request, User $user) {
        if (!auth()->user()->canPermission('users.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }

        $validated = $request->validate([
            'avatar'   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required','email:rfc,dns','max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone'    => ['required','string','max:30',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'status'   => ['required',
                Rule::in(['active','inactive','locked','suspended',]),
            ],
            'timezone' => ['required',
                Rule::in(\DateTimeZone::listIdentifiers()),
            ],
        ]);

        $adminRole = Role::where('name','super_admin')->first();
        $isLastAdmin = User::query()
            ->whereHas('roles', function ($q) use ($adminRole) {
                $q->where('roles.id', $adminRole->id);
            })
            ->count() <= 1;

        if ($isLastAdmin && $user->hasRole('super_admin')) {
            return response()->json([
                'success' => true,
                'message' => 'The last administrator cannot be disabled.',
            ]);
        }

        DB::transaction(function () use ($request, $user, $validated) {
            /*
            |--------------------------------------------------------------------------
            | Avatar
            |--------------------------------------------------------------------------
            */
            if ($request->hasFile('avatar')) {
                $validated['avatar'] = FileUploadHelper::upload(
                    $request->file('avatar'),
                    'users/avatars',
                    $user->avatar,
                    'public',
                    ['image/jpeg', 'image/png', 'image/webp'],
                    2048,
                    200,
                    200,
                    'fit',
                    'avatar'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Update User
            |--------------------------------------------------------------------------
            */
            $updateData = [
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'phone'    => $validated['phone'],
                'status'   => $validated['status'],
                'timezone' => $validated['timezone'],
            ];

            if (isset($validated['avatar'])) {
                $updateData['avatar'] = $validated['avatar'];
            }

            $user->update($updateData);
        });

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */
    public function toggleStatus(User $user) {
        if (!auth()->user()->canPermission('users.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }
        //
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */
    public function destroy(User $user) {
        if (!auth()->user()->canPermission('users.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }

        abort_if($user->hasRole('super_admin'),
            422,
            'Super Admin cannot be deleted.'
        );

        abort_if($user->id === auth()->id(),
            422,
            'You cannot delete your own account.'
        );

        //$user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }

    public function permissions(User $user) {
        if (!auth()->user()->canPermission('users.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }

        $user->load([
            'roles.permissions.module',
            'userOverrides.permission',
        ]);

        $role = $user->roles->first();
        $rolePermissions = $role ? $role->permissions : collect();

        $allPermissions = Permission::query()
            ->with('module')
            ->where('is_active', true)
            ->orderBy('module_id')
            ->orderBy('label')
            ->get();

        $permissionsByModule = $allPermissions->groupBy(fn ($permission) => $permission->module?->label ?? 'Other');
        $overrides = $user->userOverrides->keyBy('permission_id');

        return view(
            'administration.users.permissions',
            compact('user','role','rolePermissions','permissionsByModule','overrides')
        );
    }

    public function updatePermissions(Request $request, User $user) {
        if (!auth()->user()->canPermission('users.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }

        $selectedPermissions = collect($request->input('permissions', []))->map(fn ($id) => (int) $id);
        $role = $user->roles()->with('permissions')->first();

        $rolePermissionIds = $role ? $role->permissions->pluck('id') : collect();

        $user->userOverrides()->delete();
        $allPermissions = Permission::pluck('id');

        foreach ($allPermissions as $permissionId) {
            $inherited = $rolePermissionIds->contains($permissionId);
            $checked = $selectedPermissions->contains($permissionId);
            /*
            |--------------------------------------------------------------------------
            | Role = YES, User = OFF
            | => DENY
            |--------------------------------------------------------------------------
            */
            if ($inherited && ! $checked) {
                UserOverride::create([
                    'user_id'       => $user->id,
                    'permission_id' => $permissionId,
                    'effect'        => 'deny',
                ]);
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Role = NO, User = ON
            | => ALLOW
            |--------------------------------------------------------------------------
            */
            if (! $inherited && $checked) {
                UserOverride::create([
                    'user_id'       => $user->id,
                    'permission_id' => $permissionId,
                    'effect'        => 'allow',
                ]);
            }
        }

        return redirect()->back()->with('success','User permissions updated successfully.');
    }

    public function resetPasswordForm(User $user) {
        if (!auth()->user()->canPermission('users.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }
        
        return view(
            'administration.users.partials.reset-password',compact('user')
        );
    }

    public function resetPassword(Request $request,User $user) {
        if (!auth()->user()->canPermission('users.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }

        $validated = $request->validate([
            'password' => ['required','confirmed','min:8',],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
        ]);
    }

    public function unlock(User $user) {
        if (!auth()->user()->canPermission('users.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }

        $user->update([
            'failed_attempts' => 0,
            'locked_until'    => null,
            'status'          => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account unlocked successfully.',
        ]);
    }

    public function forceLogout(User $user) {
        if (!auth()->user()->canPermission('users.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied'
            ], 403);
        }

        abort_if(auth()->id() === $user->id,
            422,
            'You cannot force logout yourself.'
        );

        /*
        |--------------------------------------------------------------------------
        | Database Sessions
        |--------------------------------------------------------------------------
        */
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->delete();

        $user->update([
            'is_online'      => false,
            'last_seen_at'   => now(),
            'last_logout_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User logged out successfully.',
        ]);
    }
}