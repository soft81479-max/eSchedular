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
use App\Http\Requests\Organizations\OrganizerRequest;

use Exception;
use App\Models\Role;
use App\Models\User;
use App\Models\Organizer;
use App\Models\UserRole;
use App\Models\OrganizerUser;

use App\Models\Country;
use App\Models\State;
use App\Models\City;

class OrganizerController extends Controller {
     /*
     |--------------------------------------------------------------------------
     | Listing
     |--------------------------------------------------------------------------
     */
     public function index() {
          if (!auth()->user()->canPermission('organizers.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view('organizers.index');
     }

     public function datatable(Request $request) {
          if (!auth()->user()->canPermission('organizers.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          if ($request->ajax()) {
               $addButtonHtml = '';
               $selectStatusFilter  = '';
               $selectVerificationFilter = '';

               if (auth()->user()->canPermission('organizers.view')) {
                    $addButtonHtml = action_button('view', [
                         'href'  => route('organizers.create'),
                         'label' => 'ADD',
                         'title' => 'Add Organizer',
                         'icon'  => '',
                         'color' => 'warning',
                    ]);
                         
                    $selectStatusFilter = '
                         <select id="status" class="form-select form-select-sm">
                              <option value="">All Statuses</option>
                              <option value="pending">Pending</option>
                              <option value="active">Active</option>
                              <option value="inactive">Inactive</option>
                              <option value="rejected">Rejected</option>
                         </select>
                    ';

                    $selectVerificationFilter = '
                         <select id="verification" class="form-select form-select-sm">
                              <option value="">All Verification</option>
                              <option value="0">Pending</option>
                              <option value="1">Verified</option>
                         </select>
                    ';
               }

               $query = Organizer::query()
                    ->with(['primaryMembership.user','country','state','city',])
                    ->withCount(['users','events']);

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

               if ($request->filled('is_verified')) {
                    $query->where(
                         'is_verified',
                         $request->is_verified
                    );
               }
                    

               $permissions = [
                    'view'   => auth()->user()->canPermission('organizers.view'),
                    'edit'   => auth()->user()->canPermission('organizers.edit'),
                    'delete' => auth()->user()->canPermission('organizers.delete'),
               ];

               return DataTables::of($query)
                    ->setRowClass('table-border-double')
                    ->addColumn('card', function ($row) use ($permissions) {
                         $logo = $row->logo_url;
                         $name = e($row->name);
                         $email = e($row->email ?? '-');
                         $website = e($row->website ?? '-');
                         $location = e(trim(($row->city->name ?? '') .', ' .($row->country->name ?? ''),', '));
                         $owner = e($row->primary_user?->name ?? 'Not Assigned');
                         $usersCount = number_format($row->users_count);

                         $eventsCount = number_format(
                              $row->events_count
                         );

                         $statusBadge = sprintf('<span class="badge fs-xs bg-%s">%s</span>',
                              $row->status_color,
                              $row->status_label
                         );

                         $verifiedBadge = $row->is_verified
                              ? '<span class="badge fs-xs bg-success">Verified</span>'
                              : '<span class="badge fs-xs bg-warning text-dark">Pending</span>';

                         /*
                         |--------------------------------------------------------------------------
                         | Actions
                         |--------------------------------------------------------------------------
                         */
                         $actions = '';

                         if ($permissions['view']) {
                              $actions .= action_button('view', [
                                   'href'  => route('organizers.show', $row),
                                   'btn-type' => 'btn-icon',
                                   'label' => '',
                                   'title' => 'View Organizer',
                                   'color' => 'secondary',
                              ]);
                         }

                         if ($permissions['edit']) {
                              $actions .= action_button('view', [
                                   'href'  => route('organizers.edit', $row),
                                   'btn-type' => 'btn-icon',
                                   'label' => '',
                                   'title' => 'Edit Organizer',
                                   'icon'  => 'ph-pencil-line',
                                   'color' => 'primary',
                              ]);
                         }

                         if ($permissions['delete']) {
                              $actions .= action_button('delete', [
                                   'label'  => '',
                                   'title'  => 'Delete Organizer',
                                   'url'    => route('organizers.destroy', $row),
                                   'method' => 'DELETE',
                                   'table'  => 'organizersTable',
                                   'btn-type' => 'btn-icon',
                                   'color'  => 'danger',
                              ]);
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
                                                            <div class="text-muted small">' . $email . '</div>
                                                            <div class="text-muted small">' . $website . '</div>
                                                            <div class="text-muted small">' . $location . '</div>
                                                       </div>
                                                       <div class="mt-2 mt-md-0 text-start text-md-end">' . $verifiedBadge . ' ' . $statusBadge . '</div>
                                                  </div>

                                                  <div class="row border-top pt-2">
                                                       <div class="col-12 col-md-4">
                                                            <div class="small text-muted">Primary User</div>
                                                            <div class="d-flex align-items-center fw-semibold"><i class="ph-user-gear ph-sm me-1"></i>' . $owner . '</div>
                                                       </div>
                                                       <div class="col-6 col-md-2 pt-2 pt-md-0">
                                                            <div class="small text-muted">Users</div>
                                                            <div class="d-flex align-items-center fw-semibold"><i class="ph-users ph-sm me-1"></i>' . $usersCount . '</div>
                                                       </div>
                                                       <div class="col-6 col-md-2 pt-2 pt-md-0">
                                                            <div class="small text-muted">Events</div>
                                                            <div class="d-flex align-items-center fw-semibold"><i class="ph-calendar-check ph-sm me-1"></i>' . $eventsCount . '</div>
                                                       </div>
                                                       <div class="col-12 col-md-4 d-flex justify-content-start justify-content-md-end align-items-center pt-2 pt-md-0">
                                                            <div class="d-flex justify-content-start justify-content-md-end">
                                                                 <div class="hstack gap-1">' . $actions . '</div>
                                                            </div>
                                                       </div>
                                                  </div>
                                             </div>
                                        </div>
                                   </div>
                              </div>
                         ';
                    })
                    ->rawColumns(['card',])
                    ->with('custom_button', ['button_html' => $addButtonHtml])
                    ->with('statusFilter', [
                         'status_filter' => $selectStatusFilter,
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
          if (!auth()->user()->canPermission('organizers.create')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }
          
          $countries = Country::
               orderBy('name')
               ->get();

          // List of timezones for select
          $timezones = \DateTimeZone::listIdentifiers();

          return view(
               'organizers.create', compact('countries', 'timezones')
          );
     }

     public function store(OrganizerRequest $request) {
          if (!auth()->user()->canPermission('organizers.create')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          DB::beginTransaction();
          try {
               /*
               |--------------------------------------------------------------------------
               | Uploads
               |--------------------------------------------------------------------------
               */
               $avatar = $request->hasFile('avatar')
                    ? FileUploadHelper::upload(
                         $request->file('avatar'),
                         'users/avatars'
                    )
                    : null;

               $logo = $request->hasFile('logo')
                    ? FileUploadHelper::upload(
                         $request->file('logo'),
                         'organizers/logos'
                    )
                    : null;

               $bannerImage = $request->hasFile('banner_image')
                    ? FileUploadHelper::upload(
                         $request->file('banner_image'),
                         'organizers/banners'
                    )
                    : null;

               /*
               |--------------------------------------------------------------------------
               | Create User
               |--------------------------------------------------------------------------
               */
               $user = User::create([
                    'avatar'     => $avatar,
                    'name'       => $request->user_name,
                    'email'      => $request->user_email,
                    'phone'      => $request->user_phone,
                    'password'   => Hash::make($request->password),
                    'timezone'   => $request->timezone,
                    'status'     => $request->user_status,
               ]);

               /*
               |--------------------------------------------------------------------------
               | Assign Role
               |--------------------------------------------------------------------------
               */
               $user->assignRole('organizer');

               /*
               |--------------------------------------------------------------------------
               | Create Organizer
               |--------------------------------------------------------------------------
               */
               $organizer = Organizer::create([
                    'logo'                 => $logo,
                    'banner_image'         => $bannerImage,

                    'name'                 => $request->name,
                    'website'              => $request->website,
                    'email'                => $request->email,
                    'phone'                => $request->phone,
                    'description'          => $request->description,

                    'address_line_1'       => $request->address_line_1,
                    'address_line_2'       => $request->address_line_2,

                    'country_id'           => $request->country_id,
                    'state_id'             => $request->state_id,
                    'city_id'              => $request->city_id,
                    'pincode'              => $request->pincode,

                    'gst_number'           => $request->gst_number,
                    'pan_number'           => $request->pan_number,
                    'registration_number'  => $request->registration_number,

                    'status'               => Organizer::STATUS_ACTIVE,
                    'is_verified'          => false,

                    'created_by'           => auth()->id(),
                    'updated_by'           => auth()->id(),
               ]);

               /*
               |--------------------------------------------------------------------------
               | Link User To Organizer
               |--------------------------------------------------------------------------
               */
               OrganizerUser::create([
                    'organizer_id' => $organizer->id,
                    'user_id'      => $user->id,

                    'is_primary'   => true,
                    'status'       => OrganizerUser::STATUS_ACTIVE,

                    'joined_at'    => now(),

                    'created_by'   => auth()->id(),
                    'updated_by'   => auth()->id(),
               ]);

               DB::commit();

               return redirect()->route('organizers.show', $organizer)->with('success', 'Organizer created successfully');
          } catch (Exception $exception) {
               DB::rollBack();
               report($exception);
               return back()->with('error', 'Something went wrong');
          }
     }

     /*
     |--------------------------------------------------------------------------
     | View
     |--------------------------------------------------------------------------
     */
     public function show(Organizer $organizer) {
          if (!auth()->user()->canPermission('organizers.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $organizer->load([
               'country',
               'state',
               'city',

               'primaryMembership.user',

               'organizerUsers' => function ($query) {
                    $query->with('user')
                         ->orderByDesc('is_primary')
                         ->orderBy('id');
               },
          ]);

          $organizer->loadCount([
               'users',
          ]);

          return view(
               'organizers.show',
               compact('organizer')
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Edit
     |--------------------------------------------------------------------------
     */
     public function edit(Organizer $organizer) {
          if (!auth()->user()->canPermission('organizers.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $organizer->load([
               'country',
               'state',
               'city',
               'primaryMembership.user',
          ]);

          $countries = Country::orderBy('name')->get();
          $states = State::where('country_id', $organizer->country_id)->orderBy('name')->get();
          $cities = City::where('state_id', $organizer->state_id)->orderBy('name')->get();

          $timezones = \DateTimeZone::listIdentifiers();

          return view(
               'organizers.edit',
               compact(
                    'organizer',
                    'countries', 
                    'states', 
                    'cities',
                    'timezones'
               )
          );
     }

     public function update(OrganizerRequest $request, Organizer $organizer) {
          if (!auth()->user()->canPermission('organizers.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          DB::beginTransaction();
          try {
               /*
               |--------------------------------------------------------------------------
               | Primary User
               |--------------------------------------------------------------------------
               */
               $user = $organizer->primary_user;

               if ($user) {
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
                    $user->update([
                         'name'      => $request->user_name,
                         'email'     => $request->user_email,
                         'phone'     => $request->user_phone,
                         'timezone'  => $request->timezone,
                         'status'    => $request->user_status,

                         'password'  => $request->filled('password')
                              ? Hash::make($request->password)
                              : $user->password,
                    ]);
               }

               /*
               |--------------------------------------------------------------------------
               | Organizer Files
               |--------------------------------------------------------------------------
               */
               $logo = $request->hasFile('logo')
                    ? FileUploadHelper::upload(
                         $request->file('logo'),
                         'organizers/logos',
                         $organizer->logo
                    )
                    : $organizer->logo;

               $bannerImage = $request->hasFile('banner_image')
                    ? FileUploadHelper::upload(
                         $request->file('banner_image'),
                         'organizers/banners',
                         $organizer->banner_image
                    )
                    : $organizer->banner_image;

               /*
               |--------------------------------------------------------------------------
               | Organizer
               |--------------------------------------------------------------------------
               */
               $organizer->update([
                    'logo'                => $logo,
                    'banner_image'        => $bannerImage,

                    'name'                => $request->name,
                    'website'             => $request->website,
                    'email'               => $request->email,
                    'phone'               => $request->phone,
                    'description'         => $request->description,

                    'address_line_1'      => $request->address_line_1,
                    'address_line_2'      => $request->address_line_2,

                    'country_id'          => $request->country_id,
                    'state_id'            => $request->state_id,
                    'city_id'             => $request->city_id,
                    'pincode'             => $request->pincode,

                    'gst_number'          => $request->gst_number,
                    'pan_number'          => $request->pan_number,
                    'registration_number' => $request->registration_number,
                    'updated_by'          => auth()->id(),
               ]);

               DB::commit();
               return redirect()->route('organizers.show', $organizer)->with('success', 'Organizer updated successfully');
          } catch (Exception $exception) {
               DB::rollBack();
               report($exception);
               return back()->withInput()->with('error', 'Something went wrong.');
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Delete
     |--------------------------------------------------------------------------
     */
     public function destroy(Organizer $organizer){
          if (!auth()->user()->canPermission('organizers.delete')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }
          //
     }

     /*
     |--------------------------------------------------------------------------
     | Status Management
     |--------------------------------------------------------------------------
     */
     public function activate(Organizer $organizer) {
          if (!auth()->user()->canPermission('organizers.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $organizer->update([
               'status'     => Organizer::STATUS_ACTIVE,
               'updated_by' => auth()->id(),
          ]);

          return response()->json([
               'status'  => 'success',
               'message' => 'Organizer activated successfully.',
          ]);
     }

     public function deactivate(Organizer $organizer) {
          if (!auth()->user()->canPermission('organizers.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $organizer->update([
               'status'     => Organizer::STATUS_INACTIVE,
               'updated_by' => auth()->id(),
          ]);

          return response()->json([
               'status'  => 'success',
               'message' => 'Organizer deactivated successfully.',
          ]);
     }

     public function suspend(Organizer $organizer) {
          if (!auth()->user()->canPermission('organizers.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $organizer->update([
               'status'     => Organizer::STATUS_SUSPENDED,
               'updated_by' => auth()->id(),
          ]);

          return response()->json([
               'status'  => 'success',
               'message' => 'Organizer suspended successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Verification
     |--------------------------------------------------------------------------
     */
     public function verify(Organizer $organizer) {
          if (!auth()->user()->canPermission('organizers.approve')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $organizer->update([
               'is_verified' => true,
               'updated_by'  => auth()->id(),
          ]);

          return response()->json([
               'status'  => 'success',
               'message' => 'Organizer verified successfully.',
          ]);
     }

     public function unverify(Organizer $organizer) {
          if (!auth()->user()->canPermission('organizers.approve')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $organizer->update([
               'is_verified' => false,
               'updated_by'  => auth()->id(),
          ]);

          return response()->json([
               'status'  => 'success',
               'message' => 'Organizer verification removed successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Export
     |--------------------------------------------------------------------------
     */
     public function export(Request $request)
     {
          //
     }
}