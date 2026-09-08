<?php

namespace App\Http\Controllers\Organizations;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Helpers\FileUploadHelper;
use App\Http\Requests\Organizations\OrganizationRequest;

use Exception;

use Illuminate\View\View;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Role;
use App\Models\Organizer;
use App\Models\Country;
use App\Models\State;
use App\Models\City;
use App\Models\OrganizationType;
use App\Services\Shared\OrganizationAccessService;

class OrganizationController extends Controller {
     public function __construct(
          protected OrganizationAccessService $organizationAccessService,
     ) {
     }

     /*
     |--------------------------------------------------------------------------
     | Listing
     |--------------------------------------------------------------------------
     */
     public function index() {
          if (!auth()->user()->canPermission('organizations.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }
          
          return view('organizations.index');
     }

     public function datatable(Request $request) {
          if (!auth()->user()->canPermission('organizations.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          if ($request->ajax()) {
               $addButtonHtml = '';
               $selectStatusFilter  = '';
               $selectVerificationFilter = '';
               $selectTypeFilter  = '';

               if (auth()->user()->canPermission('organizations.create')) {
                    $organizationtypes = OrganizationType::query()->select(['id', 'name'])->orderBy('name')->get();
                    $selectTypeFilter = '<select id="organization_type" class="form-select form-select-sm">';
                    $selectTypeFilter .= '<option value="">All Organizations</option>';
                    
                    foreach ($organizationtypes as $organizationtype) {
                         $selectTypeFilter .= sprintf('<option value="%s">%s</option>',$organizationtype->id,e($organizationtype->name));
                    }
                    $selectTypeFilter .= '</select>';

                    $selectStatusFilter = '
                         <select id="status" class="form-select form-select-sm">
                              <option value="">All Statuses</option>
                              <option value="pending">Pending</option>
                              <option value="active">Active</option>
                              <option value="inactive">Inactive</option>
                              <option value="locked">Rejected</option>
                         </select>
                    ';

                    $selectVerificationFilter = '
                         <select id="verification" class="form-select form-select-sm">
                              <option value="">All Verification</option>
                              <option value="0">Pending</option>
                              <option value="1">Verified</option>
                         </select>
                    ';

                    $addButtonHtml = action_button('view', [
                         'href'  => route('organizations.create'),
                         'label' => 'ADD',
                         'title' => 'Add Organization',
                         'icon'  => '',
                         'color' => 'warning',
                    ]);
               }

               $query = $this->organizationAccessService
                    ->visibleOrganizations()
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

               if ($request->filled('organization_type_id')) {
                    $query->where(
                         'organization_type_id',
                         $request->organization_type_id
                    );
               }

               if ($request->filled('is_verified')) {
                    $query->where(
                         'is_verified',
                         $request->is_verified
                    );
               }

               $permissions = [
                    'view'   => auth()->user()->canPermission('organizations.view'),
                    'edit'   => auth()->user()->canPermission('organizations.edit'),
                    'delete' => auth()->user()->canPermission('organizations.delete'),
                    
                    'users_view' => auth()->user()->canPermission('organizations_users.view'),
               ];

               return DataTables::eloquent($query)
                    ->addIndexColumn()
                    ->setRowClass('table-border-double')
                    /*
                    |--------------------------------------------------------------------------
                    | Organization
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('card', function ($row) use ($permissions) {
                         $logo = $row->logo_url;
                         $name = e($row->name);
                         $type = e($row->organizationType?->name ?? '-');
                         $email = e($row->email ?? '-');
                         $website = e($row->website ?? '-');
                         $location = e(trim(($row->city->name ?? '') .', ' .($row->country->name ?? ''),', '));

                         $owner = e($row->owner?->name ?? 'Not Assigned');
                         $usersCount = number_format($row->organization_users_count);
                         $eventsCount = number_format($row->event_organizations_count);
                         $statusBadge = sprintf(
                              '<span class="badge fs-xs bg-%s">%s</span>',
                              $row->status_color,
                              $row->status_label
                         );

                         $verificationBadge = '';
                         if ($row->status === Organization::STATUS_ACTIVE) {
                              $verificationBadge = $row->is_verified
                                   ? '<span class="badge fs-xs bg-success ms-1">Verified</span>'
                                   : '<span class="badge fs-xs bg-warning border text-white ms-1">Pending</span>';
                         }

                         /*
                         |--------------------------------------------------------------------------
                         | Actions
                         |--------------------------------------------------------------------------
                         */
                         $actions = '';

                         if ($permissions['view']) {
                              $actions .= action_button('view', [
                                   'href'  => route('organizations.show', $row),
                                   'btn-type' => 'btn-icon',
                                   'label' => '',
                                   'title' => 'View Organization',
                                   'color' => 'secondary',
                              ]);
                         }

                         if ($permissions['edit']) {
                              $actions .= action_button('view', [
                                   'href'  => route('organizations.edit', $row),
                                   'btn-type' => 'btn-icon',
                                   'label' => '',
                                   'title' => 'Edit Organization',
                                   'icon'  => 'ph-pencil-line',
                                   'color' => 'primary',
                              ]);
                         }

                         //if ($permissions['users_view']) {
                         //     $actions .= action_button('view', [
                         //          'href'     => route('organizations.users.index', $row),
                         //          'btn-type' => 'btn-icon',
                         //          'label'    => '',
                         //          'title'    => 'Organization Users',
                         //          'icon'     => 'ph-users',
                         //          'color'    => 'info',
                         //     ]);
                         //}

                         if ($permissions['delete']) {
                              $actions .= action_button('delete', [
                                   'label'  => '',
                                   'title'  => 'Delete Organization',
                                   'url'    => route('organizations.destroy', $row),
                                   'method' => 'DELETE',
                                   'table'  => 'organizationsTable',
                                   'btn-type' => 'btn-icon',
                                   'color'  => 'danger',
                              ]);
                         }

                         /*
                         |--------------------------------------------------------------------------
                         | Verification
                         |--------------------------------------------------------------------------
                         */
                         if ($permissions['edit'] && $row->status === Organization::STATUS_ACTIVE) {
                              if (! $row->is_verified) {
                                   $actions .= action_button('verify', [
                                        'label'    => '',
                                        'title'    => 'Verify Organization',
                                        'url'      => route('organizations.verify', $row),
                                        'method'   => 'POST',
                                        'table'    => 'organizationsTable',
                                        'btn-type' => 'btn-icon',
                                        'icon'     => 'ph-check-circle',
                                        'color'    => 'success',
                                   ]);
                              } else {
                                   $actions .= action_button('unverify', [
                                        'label'    => '',
                                        'title'    => 'Unverify Organization',
                                        'url'      => route('organizations.unverify', $row),
                                        'method'   => 'POST',
                                        'table'    => 'organizationsTable',
                                        'btn-type' => 'btn-icon',
                                        'icon'     => 'ph-x-circle',
                                        'color'    => 'warning',
                                   ]);
                              }
                         }

                         return '
                              <div class="card mb-0 shadow-none border-0 rounded-0 bg-transparent">
                                   <div class="card-body p-0">
                                        <div class="d-flex flex-column flex-md-row align-items-start">
                                             <img src="' . $logo . '" class="img-fluid rounded me-md-2 mb-2 mb-md-0 flex-shrink-0" style="width:70px;height:70px;object-fit:cover;">
                                             <div class="flex-fill">
                                                  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start mb-2">
                                                       <div>
                                                            <h6 class="mb-0 fw-semibold">' . $name . '</h6>
                                                            <div class="text-black small">' . $type . '</div>
                                                            <div class="text-muted small">' . $email . '</div>
                                                            <div class="text-muted small">' . $location . '</div>
                                                       </div>
                                                       <div class="mt-2 mt-md-0 text-start text-md-end">' . $statusBadge . $verificationBadge. '</div>
                                                  </div>
                                                  
                                                  <div class="row border-top pt-2">
                                                       <div class="col-12 col-md-4">
                                                            <div class="small text-muted">Primary User</div>
                                                            <div class="d-flex align-items-center fw-semibold">
                                                                 <i class="ph-user-circle ph-sm me-1"></i>
                                                                 ' . $owner . '
                                                            </div>
                                                       </div>
                                                       <div class="col-6 col-md-2 pt-2 pt-md-0">
                                                            <div class="small text-muted">Users</div>
                                                            <div class="d-flex align-items-center fw-semibold">
                                                                 <i class="ph-users ph-sm me-1"></i>
                                                                 ' . $usersCount . '
                                                            </div>
                                                       </div>
                                                       <div class="col-6 col-md-2 pt-2 pt-md-0">
                                                            <div class="small text-muted">Events</div>
                                                            <div class="d-flex align-items-center fw-semibold">
                                                                 <i class="ph-calendar-check ph-sm me-1"></i>
                                                                 ' . $eventsCount . '
                                                            </div>
                                                       </div>
                                                       
                                                       <div class="col-12 col-md-4 d-flex justify-content-start justify-content-md-end align-items-center pt-2 pt-md-0">
                                                            <div class="hstack gap-1">
                                                                 ' . $actions . '
                                                            </div>
                                                       </div>
                                                  </div>
                                             </div>
                                        </div>
                                   </div>
                              </div>
                         ';
                    })
                    ->rawColumns([
                         'card',
                    ])
                    ->with('custom_button', ['button_html' => $addButtonHtml])
                    ->with('statusFilter', [
                         'status_filter' => $selectStatusFilter,
                    ])
                    ->with('typeFilter', [
                         'type_filter' => $selectTypeFilter,
                    ])
                    ->with('verificationFilter', [
                         'verification_filter' => $selectVerificationFilter,
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
          if (!auth()->user()->canPermission('organizations.ctreate')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $showOrganizerSelector = auth()->user()->hasRole([
               'super_admin',
               'admin',
          ]);

          $countries = Country::query()
               ->orderBy('name')
               ->get();

          $organizationTypes = OrganizationType::query()
               ->orderBy('name')
               ->get();

          $organizers = collect();

          if ($showOrganizerSelector) {
               $organizers = Organizer::query()
                    ->select('id', 'name')
                    ->where('status', Organizer::STATUS_ACTIVE)
                    ->where('is_verified', true)
                    ->orderBy('name')
                    ->get();
          }

          $timezones = \DateTimeZone::listIdentifiers();

          return view(
               'organizations.create',
               compact(
                    'showOrganizerSelector',
                    'organizers',
                    'organizationTypes',
                    'countries',
                    'timezones'
               )
          );
     }

     public function store(OrganizationRequest $request) {
          if (!auth()->user()->canPermission('organizations.create')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          DB::beginTransaction();

          try {
              /*
               |--------------------------------------------------------------------------
               | Organizer
               |--------------------------------------------------------------------------
               */
               $organizerId = auth()->user()->hasRole([
                    'super_admin',
                    'admin',
               ])
                    ? $request->organizer_id
                    : auth()->user()->organizers()->value('organizers.id');

               /*
               |--------------------------------------------------------------------------
               | Uploads
               |--------------------------------------------------------------------------
               */
               $logo = null;
               $avatar = null;

               if ($request->hasFile('logo')) {
                    $logo = FileUploadHelper::upload(
                         $request->file('logo'),
                         'organizations/logos'
                    );
               }

               if ($request->hasFile('avatar')) {
                    $avatar = FileUploadHelper::upload(
                         $request->file('avatar'),
                         'users/avatars'
                    );
               }

               /*
               |--------------------------------------------------------------------------
               | User
               |--------------------------------------------------------------------------
               */
               $user = User::create([
                    'avatar'    => $avatar,
                    'name'      => $request->admin_name,
                    'email'     => $request->admin_email,
                    'phone'     => $request->admin_phone,
                    'password'  => Hash::make($request->password),
                    'timezone'  => $request->timezone,
                    'status'    => $request->admin_status,
               ]);

               /*
               |--------------------------------------------------------------------------
               | Organization
               |--------------------------------------------------------------------------
               */
               $organization = Organization::create([
                    'organizer_id'        => $organizerId,

                    'name'                => $request->name,
                    'organization_type_id'=> $request->organization_type_id,

                    'owner_user_id'       => $user->id,

                    'logo'                => $logo,

                    'website'             => $request->website,
                    'email'               => $request->email,
                    'phone'               => $request->phone,

                    'address'             => $request->address,

                    'country_id'          => $request->country_id,
                    'state_id'            => $request->state_id,
                    'city_id'             => $request->city_id,

                    'description'         => $request->description,

                    'status'              => $request->status,

                    'created_by'          => auth()->id(),
                    'updated_by'          => auth()->id(),
               ]);

               /*
               |--------------------------------------------------------------------------
               | Organization User
               |--------------------------------------------------------------------------
               */
               OrganizationUser::create([
                    'organization_id' => $organization->id,
                    'user_id'         => $user->id,

                    'is_admin'        => true,

                    'status'          => OrganizationUser::STATUS_ACTIVE,

                    'created_by'      => auth()->id(),
                    'updated_by'      => auth()->id(),
               ]);

               $user->assignRole('organization');
               DB::commit();

               return redirect()->route('organizations.index')->with('success','Organization created successfully.');
          } catch (Exception $e) {
               DB::rollBack();
               report($e);
               return back()->withInput()->withErrors(['error' => $e->getMessage(),]);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | View
     |--------------------------------------------------------------------------
     */
     public function show(Organization $organization) {
          abort_unless(
               auth()->user()->canPermission('organizations.view'),
               403
          );

          return view(
               'organizations.show',
               [
                    'organization' => $this->organizationAccessService->show($organization),
               ]
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Edit
     |--------------------------------------------------------------------------
     */
     public function edit(Organization $organization) {
          if (!auth()->user()->canPermission('organizations.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          abort_if(
               ! $this->organizationAccessService->hasAccess($organization),
               403
          );

          $organization->load([
               'owner',
               'organizationType',
               'country',
               'state',
               'city',
               'organizer',
          ]);

          $showOrganizerSelector = auth()->user()->hasRole([
               'super_admin',
               'admin',
          ]);

          $countries = Country::orderBy('name')->get();
          $states = State::where('country_id', $organization->country_id)->orderBy('name')->get();
          $cities = City::where('state_id', $organization->state_id)->orderBy('name')->get();

          $organizationTypes = OrganizationType::query()
               ->orderBy('name')
               ->get();

          $organizers = collect();

          if ($showOrganizerSelector) {
               $organizers = Organizer::query()
                    ->select('id', 'name')
                    ->where('status', Organizer::STATUS_ACTIVE)
                    ->where('is_verified', true)
                    ->orderBy('name')
                    ->get();
          }

          $timezones = \DateTimeZone::listIdentifiers();

          return view(
               'organizations.edit',
               compact(
                    'organization',
                    'showOrganizerSelector',
                    'organizers',
                    'organizationTypes',
                    'countries',
                    'states', 
                    'cities',
                    'timezones'
               )
          );
     }

     public function update(OrganizationRequest $request, Organization $organization) {
          if (!auth()->user()->canPermission('organizations.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          abort_if(
               ! $this->organizationAccessService->hasAccess($organization),
               403
          );

          DB::beginTransaction();

          try {
               /*
               |--------------------------------------------------------------------------
               | Organizer
               |--------------------------------------------------------------------------
               */
               $organizerId = auth()->user()->hasRole([
                    'super_admin',
                    'admin',
               ])
                    ? $request->organizer_id
                    : auth()->user()->organizers()->value('organizers.id');
               /*
               |--------------------------------------------------------------------------
               | Administrator
               |--------------------------------------------------------------------------
               */
               $user = $organization->owner;

               /*
               |--------------------------------------------------------------------------
               | Uploads
               |--------------------------------------------------------------------------
               */
               if ($request->hasFile('logo')) {
                    $organization->logo = FileUploadHelper::upload(
                         $request->file('logo'),
                         'organizations/logos',
                         $organization->logo
                    );
               }

               if ($request->hasFile('avatar')) {
                    $user->avatar = FileUploadHelper::upload(
                         $request->file('avatar'),
                         'users/avatars',
                         $user->avatar
                    );
               }

               /*
               |--------------------------------------------------------------------------
               | Update Administrator
               |--------------------------------------------------------------------------
               */
               $user->name     = $request->admin_name;
               $user->email    = $request->admin_email;
               $user->phone    = $request->admin_phone;
               $user->timezone = $request->timezone;
               $user->status   = $request->admin_status;

               if ($request->filled('password')) {
                    $user->password = Hash::make($request->password);
               }

               $user->save();

               /*
               |--------------------------------------------------------------------------
               | Update Organization
               |--------------------------------------------------------------------------
               */
               $data = [
                    'organizer_id'         => $organizerId,
                    'name'                 => $request->name,
                    'organization_type_id' => $request->organization_type_id,
                    'website'              => $request->website,
                    'email'                => $request->email,
                    'phone'                => $request->phone,
                    'address'              => $request->address,
                    'country_id'           => $request->country_id,
                    'state_id'             => $request->state_id,
                    'city_id'              => $request->city_id,
                    'description'          => $request->description,
                    'status'               => $request->status,
                    'updated_by'           => auth()->id(),
               ];

               if ($request->status !== Organization::STATUS_ACTIVE) {
                    $data['is_verified'] = false;
                    $data['approved_at'] = null;
                    $data['approved_by'] = null;
               }
               $organization->update($data);

               DB::commit();
               return redirect()->route('organizations.index')->with('success', 'Organization updated successfully');
          } catch (\Exception $e) {
               DB::rollBack();
               report($e);
               return back()->withInput()->withErrors(['error' => $e->getMessage(),]);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Verification
     |--------------------------------------------------------------------------
     */
     public function verify(Organization $organization) {
          if (! auth()->user()->canPermission('organizations.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied.',
               ], 403);
          }

          abort_unless(
               $organization->status === Organization::STATUS_ACTIVE,
               422,
               'Only active organizations can be verified.'
          );

          $organization->update([
               'is_verified' => true,
               'approved_at' => now(),
               'approved_by' => auth()->id(),
               'updated_by'  => auth()->id(),
          ]);

          return response()->json([
               'status'  => 'success',
               'message' => 'Organization verified successfully.',
          ]);
     }

     public function unverify(Organization $organization) {
          if (! auth()->user()->canPermission('organizations.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied.',
               ], 403);
          }

          abort_unless(
               $organization->status === Organization::STATUS_ACTIVE,
               422,
               'Only active organizations can be unverified.'
          );

          $organization->update([
               'is_verified' => false,
               'approved_at' => null,
               'approved_by' => null,
               'updated_by'  => auth()->id(),
          ]);

          return response()->json([
               'status'  => 'success',
               'message' => 'Organization verification removed successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Delete
     |--------------------------------------------------------------------------
     */
     public function destroy(Organization $organization)
     {
          if (!auth()->user()->canPermission('organizations.delete')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }
          //
     }
}