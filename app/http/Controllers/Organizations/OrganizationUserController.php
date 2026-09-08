<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Str;
use Illuminate\View\View;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Helpers\FileUploadHelper;
use App\Http\Requests\Organizations\OrganizationRequest;

use Exception;
use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Http\Requests\Organizations\OrganizationUserRequest;
use App\Services\Shared\OrganizationAccessService;
use Illuminate\Http\JsonResponse;

class OrganizationUserController extends Controller {
     public function __construct(
          protected OrganizationAccessService $organizationAccessService,
     ) {
     }

     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     */
     public function index(Organization $organization) {
          if (!auth()->user()->canPermission('organizations_users.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view('organizations.users.index',compact('organization'));
     }

     /*
     |--------------------------------------------------------------------------
     | Datatable
     |--------------------------------------------------------------------------
     */
     public function datatable(Request $request, Organization $organization): JsonResponse {
          if (!auth()->user()->canPermission('organizations_users.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          if ($request->ajax()) {
               $addButtonHtml = '';
               $selectStatusFilter  = '';

               if (auth()->user()->canPermission('organizations_users.view')) {
                    $selectStatusFilter = '
                         <select id="status" class="form-select form-select-sm">
                              <option value="">All Statuses</option>
                              <option value="active">Active</option>
                              <option value="inactive">Inactive</option>
                         </select>
                    ';

                    $addButtonHtml = action_button('view', [
                         'href' => route('organizations.users.create', ['organization' => $organization,]),
                         'label' => 'ADD',
                         'title' => 'Add User',
                         'icon'  => '',
                         'color' => 'warning',
                    ]);
               }

               $query = OrganizationUser::query()
                    ->where('organization_id', $organization->id)
                    ->with(['user','creator:id,name',])
                    ->withCount([
                         'participants',
                    ])
                    ->latest();

               /*
               |--------------------------------------------------------------------------
               | Filters
               |--------------------------------------------------------------------------
               */
               if ($request->filled('status')) {
                    $query->where(
                         'status',
                         $request->status
                    );
               }

               $permissions = [
                    'view'   => auth()->user()->canPermission('organizations_users.view'),
                    'edit'   => auth()->user()->canPermission('organizations_users.edit'),
                    'delete' => auth()->user()->canPermission('organizations_users.delete'),
               ];

               return DataTables::eloquent($query)
                    ->addIndexColumn()
                    ->addColumn('user_name', function ($row) {
                    $avatar = '<img src="'.$row->user->avatar_url.'" class="rounded-circle" width="38" height="38">';
                    return '
                         <div class="d-flex align-items-center">
                              '.$avatar.'
                              <div class="ms-2">
                                   <div class="fw-semibold">'.$row->user->name.'</div>
                                   <div class="text-muted fs-sm">'.$row->user->email.'</div>
                              </div>
                         </div>';
                    })
                    ->addColumn('administrator', function ($row) {
                         return $row->is_admin
                              ? '<span class="badge bg-primary">Yes</span>'
                              : '<span class="badge bg-light text-body border">No</span>';
                    })
                    ->addColumn('events', function ($row) {
                         return '
                              <div class="text-center">
                                   <span class="fw-semibold">'.$row->event_participants_count.'</span>
                              </div>';
                    })
                    ->addColumn('user_status', function ($row) {
                         return $row->status === OrganizationUser::STATUS_ACTIVE
                              ? '<span class="badge bg-success">Active</span>'
                              : '<span class="badge bg-secondary">Inactive</span>';
                    })
                    ->addColumn('activity', function ($row) {
                         return '
                              <div class="small">
                                   '.($row->user->last_login_at
                                        ? $row->user->last_login_at->diffForHumans()
                                        : '<span class="text-muted">Never</span>').'
                              </div>';
                    })
                    ->addColumn('actions', function ($row) use ($organization) {
                         $actionBtns = '';

                         /*
                         |--------------------------------------------------------------------------
                         | View
                         |--------------------------------------------------------------------------
                         */
                         if (auth()->user()->canPermission('organizations_users.view')) {
                              $actionBtns .= '<div class="dropdown-item p-0 d-flex justify-content-start align-items-center">'.
                                   action_button('view', [
                                        'label'  => 'View User',
                                        'title'  => 'View User',
                                        'href'  => route('organizations.users.show', [$organization, $row]),
                                        'class'  => 'd-flex justify-content-start align-items-center w-100 text-start border-0 bg-transparent py-1 px-3 text-dark d-block m-0',
                                        'color'  => '',
                                        'icon'  => 'ph-eye',
                                   ]).
                              '</div>';
                         }

                         /*
                         |--------------------------------------------------------------------------
                         | Edit
                         |--------------------------------------------------------------------------
                         */
                         if (auth()->user()->canPermission('organizations_users.edit')) {
                              $actionBtns .= '<div class="dropdown-item p-0 d-flex justify-content-start align-items-center">'.
                                   action_button('view', [
                                        'label'  => 'Edit User',
                                        'title'  => 'Edit User',
                                        'href'  => route('organizations.users.edit', [$organization, $row]),
                                        'class'  => 'd-flex justify-content-start align-items-center w-100 text-start border-0 bg-transparent py-1 px-3 text-dark d-block m-0',
                                        'color'  => '',
                                        'icon'  => 'ph-pencil-simple',
                                   ]).
                              '</div>';
                         }

                         /*
                         |--------------------------------------------------------------------------
                         | Make Administrator
                         |--------------------------------------------------------------------------
                         */
                         if (auth()->user()->canPermission('organizations_users.manage') && ! $row->is_admin) {
                              $actionBtns .= '<div class="dropdown-item p-0 d-flex justify-content-start align-items-center">'.
                                   action_button('makeAdmin', [
                                        'label'  => 'Make Administrator',
                                        'title'  => 'Make Administrator',
                                        'url' => route('organizations.users.make-admin',[$organization, $row]),
                                        'method' => 'POST',
                                        'table'  => 'organizationUsersTable',
                                        'class'  => 'd-flex justify-content-start align-items-center w-100 text-start border-0 bg-transparent py-1 px-3 text-dark d-block m-0',
                                        'color'  => '',
                                        'icon'  => 'ph-crown',
                                   ]).
                              '</div>';
                         }

                         /*
                         |--------------------------------------------------------------------------
                         | Activate / Deactivate
                         |--------------------------------------------------------------------------
                         */
                         if (auth()->user()->canPermission('organizations_users.edit')) {
                              if ($row->status === OrganizationUser::STATUS_ACTIVE) {
                                   $actionBtns .= '<div class="dropdown-item p-0 d-flex justify-content-start align-items-center">'.
                                        action_button('deactivate', [
                                             'label'  => 'Deactivate',
                                             'title'  => 'Deactivate',
                                             'url' => route('organizations.users.deactivate',[$organization, $row]),
                                             'method' => 'POST',
                                             'table'  => 'organizationUsersTable',
                                             'class'  => 'd-flex justify-content-start align-items-center w-100 text-start border-0 bg-transparent py-1 px-3 text-dark d-block m-0',
                                             'color'  => '',
                                             'icon'  => 'ph-lock',
                                        ]).
                                   '</div>';
                              } else {
                                   action_button('deactivate', [
                                             'label'  => 'Activate',
                                             'title'  => 'Activate',
                                             'url' => route('organizations.users.activate',[$organization, $row]),
                                             'method' => 'POST',
                                             'table'  => 'organizationUsersTable',
                                             'class'  => 'd-flex justify-content-start align-items-center w-100 text-start border-0 bg-transparent py-1 px-3 text-dark d-block m-0',
                                             'color'  => '',
                                             'icon'  => 'ph-lock-open',
                                        ]).
                                   '</div>';
                              }
                         }

                         /*
                         |--------------------------------------------------------------------------
                         | Delete
                         |--------------------------------------------------------------------------
                         */
                         if (auth()->user()->canPermission('organizations_users.delete')) {
                              if (!empty($actionBtns)) {
                                   $actionBtns .= '<div class="dropdown-divider"></div>';
                              }
                              $actionBtns .= '<div class="dropdown-item p-0 d-flex justify-content-start align-items-center">'.
                                   action_button('delete', [
                                        'label'  => 'Delete User',
                                        'title'  => 'Delete User',
                                        'url' => route('organizations.users.destroy', [$organization, $row]),
                                        'method' => 'DELETE',
                                        'table'  => 'organizationUsersTable',
                                        'class'  => 'd-flex justify-content-start align-items-center w-100 text-start border-0 bg-transparent py-1 px-3 text-danger d-block m-0',
                                        'color'  => '',
                                        'icon'  => 'ph-trash',
                                   ]).
                              '</div>';
                         }
                         
                         return '<div class="dropdown">
                              <button type="button" class="btn btn-sm  px-2 btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                   <i class="ph-dots-three-vertical ph-sm me-1"></i>
                              </button>
                              <div class="dropdown-menu dropdown-menu-end py-1">'.$actionBtns.'</div>
                         </div>';
                    })
                    ->rawColumns(['user_name','administrator','events','user_status','activity','actions',])
                    ->with('custom_meta', ['button_html' => $addButtonHtml])
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
     public function create(Organization $organization) {
          if (!auth()->user()->canPermission('organizations_users.create')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          abort_unless(
               $this->organizationAccessService->hasAccess($organization),
               403
          );

          $timezones = \DateTimeZone::listIdentifiers();

          return view(
               'organizations.users.create',
               compact(
                    'organization',
                    'timezones'
               )
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Store
     |--------------------------------------------------------------------------
     */
     public function store(OrganizationUserRequest $request,Organization $organization) {
          if (!auth()->user()->canPermission('organizations_users.create')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          abort_unless(
               $this->organizationAccessService->hasAccess($organization),
               403
          );

          DB::beginTransaction();

          try {

               /*
               |--------------------------------------------------------------------------
               | Upload Avatar
               |--------------------------------------------------------------------------
               */
               $avatar = null;

               if ($request->hasFile('avatar')) {
                    $avatar = FileUploadHelper::upload(
                         $request->file('avatar'),
                         'users/avatars'
                    );
               }

               /*
               |--------------------------------------------------------------------------
               | Create User
               |--------------------------------------------------------------------------
               */
               $user = User::create([
                    'avatar'   => $avatar,
                    'name'     => $request->name,
                    'email'    => $request->email,
                    'phone'    => $request->phone,
                    'password' => Hash::make($request->password),
                    'timezone' => $request->timezone,
                    'status'   => $request->status,
               ]);

               /*
               |--------------------------------------------------------------------------
               | Assign Role
               |--------------------------------------------------------------------------
               */
               if ($request->boolean('is_admin')) {
                    $user->assignRole('organization');
               } else {
                    $user->assignRole('representative');
               }

               /*
               |--------------------------------------------------------------------------
               | Organization User
               |--------------------------------------------------------------------------
               */
               OrganizationUser::create([
                    'organization_id' => $organization->id,
                    'user_id'         => $user->id,

                    'is_admin'        => $request->boolean('is_admin'),
                    'designation'     => $request->designation,
                    'bio'             => $request->bio,
                    'status'          => $request->status,

                    'created_by'      => auth()->id(),
                    'updated_by'      => auth()->id(),
               ]);

               DB::commit();

               return redirect()
                    ->route('organizations.show', $organization)
                    ->with('success', 'Organization user created successfully.');

          } catch (\Exception $exception) {
               DB::rollBack();
               report($exception);
               return back()
                    ->withInput()
                    ->withErrors([
                         'error' => $exception->getMessage(),
                    ]);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Show
     |--------------------------------------------------------------------------
     */
     public function show(Organization $organization,OrganizationUser $organizationUser): View {

          abort_unless(
               auth()->user()->canPermission('organizations_users.view'),
               403
          );

          abort_unless(
               $this->organizationAccessService->hasAccess($organization),
               403
          );

          abort_unless(
               $organizationUser->organization_id === $organization->id,
               404
          );

          $organization->load([
               'organizationType',
               'owner',
               'country',
               'state',
               'city',
          ]);

          $organizationUser->load([
               'user',
               'creator',
               'updater',
          ]);

          return view(
               'organizations.users.show',
               compact(
                    'organization',
                    'organizationUser'
               )
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Edit
     |--------------------------------------------------------------------------
     */
     public function edit(Organization $organization,OrganizationUser $organizationUser) {
          if (!auth()->user()->canPermission('organizations_users.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          abort_unless(
               $this->organizationAccessService->hasAccess($organization),
               403
          );

          abort_unless(
               $organizationUser->organization_id === $organization->id,
               404
          );

          $organizationUser->load([
               'user',
               'organization',
          ]);

          $timezones = \DateTimeZone::listIdentifiers();

          return view(
               'organizations.users.edit',
               compact(
                    'organization',
                    'organizationUser',
                    'timezones'
               )
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Update
     |--------------------------------------------------------------------------
     */
     public function update(OrganizationUserRequest $request,Organization $organization,OrganizationUser $organizationUser) {
          if (!auth()->user()->canPermission('organizations_users.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          abort_unless(
               $this->organizationAccessService->hasAccess($organization),
               403
          );

          abort_unless(
               $organizationUser->organization_id === $organization->id,
               404
          );

          DB::beginTransaction();

          try {

               /*
               |--------------------------------------------------------------------------
               | User
               |--------------------------------------------------------------------------
               */
               $user = $organizationUser->user;

               /*
               |--------------------------------------------------------------------------
               | Avatar
               |--------------------------------------------------------------------------
               */
               if ($request->hasFile('avatar')) {

                    $user->avatar = FileUploadHelper::upload(
                         $request->file('avatar'),
                         'users/avatars',
                         $user->avatar
                    );
               }

               /*
               |--------------------------------------------------------------------------
               | User Information
               |--------------------------------------------------------------------------
               */
               $user->name = $request->name;
               $user->email = $request->email;
               $user->phone = $request->phone;
               $user->timezone = $request->timezone;
               $user->status = $request->status;

               if ($request->filled('password')) {
                    $user->password = Hash::make($request->password);
               }

               $user->save();

               /*
               |--------------------------------------------------------------------------
               | Role
               |--------------------------------------------------------------------------
               */
               if ($request->boolean('is_admin')) {
                    $user->syncRoles([
                         'organization',
                    ]);
               } else {
                    $user->syncRoles([
                         'representative',
                    ]);
               }

               /*
               |--------------------------------------------------------------------------
               | Organization User
               |--------------------------------------------------------------------------
               */
               $organizationUser->update([
                    'is_admin' => $request->boolean('is_admin'),
                    'designation' => $request->designation,
                    'bio' => $request->bio,
                    'status' => $request->status,

                    'updated_by' => auth()->id(),
               ]);

               DB::commit();
               return redirect()
                    ->route('organizations.show', $organization)
                    ->with('success', 'Organization user updated successfully.');

          } catch (\Exception $exception) {
               DB::rollBack();
               report($exception);
               return back()
                    ->withInput()
                    ->withErrors([
                         'error' => $exception->getMessage(),
                    ]);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Activate
     |--------------------------------------------------------------------------
     */
     public function activate(Organization $organization,OrganizationUser $organizationUser): JsonResponse {
          if (! auth()->user()->canPermission('organizations.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied.',
               ], 403);
          }

          abort_unless(
               $organizationUser->organization_id === $organization->id,
               404
          );

          $organizationUser->update([
               'status' => OrganizationUser::STATUS_ACTIVE,
               'updated_by' => auth()->id(),
          ]);

          return response()->json([
               'success' => true,
               'message' => 'Representative activated successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Deactivate
     |--------------------------------------------------------------------------
     */
     public function deactivate(Organization $organization,OrganizationUser $organizationUser): JsonResponse {
          if (! auth()->user()->canPermission('organizations.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied.',
               ], 403);
          }

          abort_unless(
               $organizationUser->organization_id === $organization->id,
               404
          );

          if ($organizationUser->is_admin) {
               return response()->json([
                    'success' => false,
                    'message' => 'Organization administrator cannot be deactivated.',
               ], 422);
          }

          $organizationUser->update([
               'status' => OrganizationUser::STATUS_INACTIVE,
               'updated_by' => auth()->id(),
          ]);

          return response()->json([
               'success' => true,
               'message' => 'Representative deactivated successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Make Administrator
     |--------------------------------------------------------------------------
     */
     public function makeAdmin(Organization $organization,OrganizationUser $organizationUser): JsonResponse {
          if (! auth()->user()->canPermission('organizations.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied.',
               ], 403);
          }

          abort_unless(
               $organizationUser->organization_id === $organization->id,
               404
          );

          DB::transaction(function () use ($organization, $organizationUser) {
               $organization->organizationUsers()->update([
                    'is_admin' => false,
                    'updated_by' => auth()->id(),
               ]);

               $organizationUser->update([
                    'is_admin' => true,
                    'updated_by' => auth()->id(),
               ]);
          });

          return response()->json([
               'success' => true,
               'message' => 'Organization administrator updated successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Destroy
     |--------------------------------------------------------------------------
     */
     public function destroy(Organization $organization,OrganizationUser $organizationUser): JsonResponse {
          if (! auth()->user()->canPermission('organizations.delete')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied.',
               ], 403);
          }

          abort_unless(
               $organizationUser->organization_id === $organization->id,
               404
          );

          if ($organizationUser->is_admin) {
               return response()->json([
                    'success' => false,
                    'message' => 'Organization administrator cannot be deleted.',
               ], 422);
          }

          if ($organizationUser->eventParticipants()->exists()) {
               return response()->json([
                    'success' => false,
                    'message' => 'Representative is participating in one or more events.',
               ], 422);
          }

          if ($organizationUser->eventSpeakers()->exists()) {
               return response()->json([
                    'success' => false,
                    'message' => 'Representative is assigned as a speaker for one or more event sessions.',
               ], 422);
          }

          if ($organizationUser->meetings()->exists()) {
               return response()->json([
                    'success' => false,
                    'message' => 'Representative is associated with one or more meetings.',
               ], 422);
          }

          $organizationUser->delete();

          return response()->json([
               'success' => true,
               'message' => 'Representative removed successfully.',
          ]);
     }
}