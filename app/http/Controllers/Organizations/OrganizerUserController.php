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
use App\Http\Requests\Organizations\OrganizerUserRequest;

use Exception;
use App\Models\User;
use App\Models\Organizer;
use App\Models\OrganizerUser;

class OrganizerUserController extends Controller {
     public function create(Organizer $organizer) {
          if (!auth()->user()->canPermission('organizers.manage_users')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          // List of timezones for select
          $timezones = \DateTimeZone::listIdentifiers();

          return view(
               'organizers.users.create',
               compact('organizer', 'timezones')
          );
     }

     public function store(OrganizerUserRequest $request,Organizer $organizer) {
          if (!auth()->user()->canPermission('organizers.manage_users')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          DB::beginTransaction();
          try {
               $avatar = $request->hasFile('avatar')
                    ? FileUploadHelper::upload(
                         $request->file('avatar'),
                         'users/avatars'
                    )
                    : null;

               $user = User::create([
                    'avatar'   => $avatar,
                    'name'     => $request->name,
                    'email'    => $request->email,
                    'phone'    => $request->phone,
                    'password' => Hash::make($request->password),
                    'timezone' => $request->timezone,
                    'status'   => $request->status,
               ]);

               $user->assignRole('organizer');

               OrganizerUser::create([
                    'organizer_id' => $organizer->id,
                    'user_id'      => $user->id,

                    'is_primary'   => false,
                    'status'       => OrganizerUser::STATUS_ACTIVE,

                    'joined_at'    => now(),

                    'created_by'   => auth()->id(),
                    'updated_by'   => auth()->id(),
               ]);

               DB::commit();
               return response()->json([
                    'status'  => 'success',
                    'message' => 'Organizer user created successfully.',
               ]);
          } catch (Exception $exception) {
               DB::rollBack();
               report($exception);
               return response()->json([
                    'status'  => 'error',
                    'message' => 'Something went wrong.',
               ], 500);
          }
     }

     public function edit(Organizer $organizer,OrganizerUser $organizerUser) {
          if (!auth()->user()->canPermission('organizers.manage_users')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          // List of timezones for select
          $timezones = \DateTimeZone::listIdentifiers();

          //abort_if($organizerUser->organizer_id !== $organizer->id,
          //     404
          //);

          $organizerUser->load(
               'user'
          );

          return view(
               'organizers.users.edit',
               compact(
                    'organizer',
                    'organizerUser', 
                    'timezones'
               )
          );
     }

     public function update(OrganizerUserRequest $request,Organizer $organizer,OrganizerUser $organizerUser) {
          if (!auth()->user()->canPermission('organizers.manage_users')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          //abort_if(
          //     $organizerUser->organizer_id !== $organizer->id,
          //     404
          //);

          DB::beginTransaction();

          try {
               $user = $organizerUser->user;
               if ($request->hasFile('avatar')) {
                    $user->avatar = FileUploadHelper::upload(
                         $request->file('avatar'),
                         'users/avatars'
                    );
               }

               $user->name = $request->name;
               $user->email = $request->email;
               $user->phone = $request->phone;
               $user->timezone = $request->timezone;
               $user->status = $request->status;
               if ($request->filled('password')) {
                    $user->password = Hash::make(
                         $request->password
                    );
               }

               $user->save();
               $organizerUser->update([
                    'updated_by' => auth()->id(),
               ]);

               DB::commit();
               return response()->json([
                    'status'  => 'success',
                    'message' => 'Organizer user updated successfully.',
               ]);
          } catch (Exception $exception) {
               DB::rollBack();
               report($exception);
               return response()->json([
                    'status'  => 'error',
                    'message' => 'Something went wrong.',
               ], 500);
          }
     }

     public function activate(Organizer $organizer,OrganizerUser $organizerUser) {
          if (!auth()->user()->canPermission('organizers.manage_users')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          //abort_if(
          //     $organizerUser->organizer_id !== $organizer->id,
          //     404
          //);

          $user = $organizerUser->user;
          //if ($user->status === 'active') {
          //     return response()->json([
          //          'status'  => 'warning',
          //          'message' => 'User is already active.',
          //     ]);
          //}

          $user->update([
               'status' => 'active',
          ]);

          return response()->json([
               'status'  => 'success',
               'message' => 'User activated successfully.',
          ]);
     }

     public function deactivate(Organizer $organizer,OrganizerUser $organizerUser) {
          if (!auth()->user()->canPermission('organizers.manage_users')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          //abort_if(
          //     $organizerUser->organizer_id !== $organizer->id,
          //     404
          //);

          if ($organizerUser->is_primary) {
               return response()->json([
                    'status'  => 'warning',
                    'message' => 'Primary user cannot be deactivated.',
               ]);
          }

          $user = $organizerUser->user;

          //if ($user->status === 'inactive') {
          //     return response()->json([
          //          'status'  => 'warning',
          //          'message' => 'User is already inactive.',
          //     ]);
          //}

          $user->update([
               'status' => 'inactive',
          ]);

          return response()->json([
               'status'  => 'success',
               'message' => 'User deactivated successfully.',
          ]);
     }

     public function makePrimary(Organizer $organizer,OrganizerUser $organizerUser) {
          if (!auth()->user()->canPermission('organizers.manage_users')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          //abort_if(
          //     $organizerUser->organizer_id !== $organizer->id,
          //     404
          //);

          //if ($organizerUser->is_primary) {
          //     return response()->json([
          //          'status'  => 'warning',
          //          'message' => 'User is already the primary user.',
          //     ]);
          //}
          if ($organizerUser->user->status !== 'active') {
               return response()->json([
                    'status'  => 'warning',
                    'message' => 'Only active users can be made primary.',
               ]);
          }

          DB::transaction(function () use ($organizer,$organizerUser) {
               OrganizerUser::where(
                    'organizer_id',
                    $organizer->id
               )->update([
                    'is_primary' => false,
               ]);

               $organizerUser->update([
                    'is_primary' => true,
                    'updated_by' => auth()->id(),
               ]);
          });

          return response()->json([
               'status'  => 'success',
               'message' => 'Primary user updated successfully.',
          ]);
     }

     public function destroy(Organizer $organizer,OrganizerUser $organizerUser) {
          if (!auth()->user()->canPermission('organizers.manage_users')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          //abort_if(
          //     $organizerUser->organizer_id !== $organizer->id,
          //     404
          //);

          if ($organizerUser->is_primary) {
               return response()->json([
                    'status'  => 'warning',
                    'message' => 'Primary user cannot be removed.',
               ]);
          }

          if ($organizer->users()->count() <= 1) {
               return response()->json([
                    'status'  => 'warning',
                    'message' => 'Organizer must have at least one user.',
               ]);
          }

          DB::beginTransaction();

          try {
               $user = $organizerUser->user;
               $organizerUser->delete();

               $user->syncRoles([]);

               $user->delete();
               DB::commit();
               return response()->json([
                    'status'  => 'success',
                    'message' => 'User removed successfully.',
               ]);
          } catch (Exception $exception) {
               DB::rollBack();
               report($exception);
               return response()->json([
                    'status'  => 'error',
                    'message' => 'Something went wrong.',
               ], 500);
          }
     }
}