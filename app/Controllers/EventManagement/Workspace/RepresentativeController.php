<?php

namespace App\Http\Controllers\EventManagement\Workspace;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use App\Models\Event;
use App\Models\EventOrganization;
use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\EventParticipant;
use App\Models\ParticipantType;
use App\Models\OrganizationUser;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\ParticipantAvailability;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Validation\Rule;
use App\Notifications\EventAccessNotification;


class RepresentativeController extends WorkspaceController {
     public function index(Event $event,EventOrganization $eventOrganization): View {
          return view(
               'event_management.workspace.organizations.representatives.index',
               array_merge(
                    $this->workspace($event),
                    [
                         'eventOrganization' => $eventOrganization,
                    ]
               )
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Datatable
     |--------------------------------------------------------------------------
     */
     public function datatable(Request $request,Event $event,EventOrganization $eventOrganization) {
          abort_unless(
               auth()->user()->canPermission('representatives.view'),
               403
          );

          if (!$request->ajax()) {
               abort(404);
          }

          /*
          |--------------------------------------------------------------------------
          | Add Button
          |--------------------------------------------------------------------------
          */
          $addButtonHtml = '';

          if (auth()->user()->canPermission('representatives.create')) {
               $addButtonHtml = action_button('add', [
                    'modal-size' => 'modal-md','label' => 'Add','title' => 'Add Representative to Event','icon' => '','color' => 'warning',
                    'createUrl' => route('events.organizations.representatives.create',[$event, $eventOrganization]),
                    'storeUrl' => route('events.organizations.representatives.store',[$event, $eventOrganization]),
                    'table' => 'representativesTable'
               ]);
          }

          $query = EventParticipant::query()
               ->with([
                    'organizationUser.user',
               ])
               ->where('event_organization_id', $eventOrganization->id)
               ->whereHas('eventOrganization', function ($query) use ($event) {
                    $query->where('event_id', $event->id);
               })
               ->whereHas('organizationUser', function ($query) use ($eventOrganization) {
                    $query->where(
                         'organization_id',
                         $eventOrganization->organization_id
                    );
               });

          return DataTables::eloquent($query)
               /*
               |--------------------------------------------------------------------------
               | Representative
               |--------------------------------------------------------------------------
               */
               ->addColumn('profile', function ($row) {
                    return '
                         <div class="d-flex align-items-center">
                              <img src="'.$row->avatar_url.'" class="rounded-circle me-2" width="38" height="38" style="object-fit:cover;">
                              <div class="overflow-hidden">
                                   <div class="fw-semibold text-truncate">'.$row->display_name.'</div>
                                   <div class="small text-muted">'.($row->organizationUser?->designation ?? '-').'</div>
                              </div>
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Access
               |--------------------------------------------------------------------------
               */
               ->addColumn('contact', function ($row) {
                    return '
                         <div class="small">
                              <div>'.($row->organizationUser?->email ?? '-').'</div>
                              <div class="text-muted">'.($row->organizationUser?->phone ?? '-').'</div>
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Status
               |--------------------------------------------------------------------------
               */
               ->addColumn('networking_view', function ($row) {
                    $mmClass = $row->matchmaking_enabled ? 'text-success' : 'text-warning';
                    $matchmakingHtml = '<span class="d-inline-flex align-items-center '.$mmClass.'"><i class="ph-lightning fs-sm me-1"></i>Matchmaking</span>';

                    $priClass = $row->is_primary ? 'text-primary' : 'text-muted';
                    $priText = $row->is_primary ? 'Primary' : 'Secondary';
                    $primaryHtml = '<span class="d-inline-flex align-items-center '.$priClass.'"><i class="ph-users fs-sm me-1"></i>'.$priText.'</span>';
                    
                    $statusClass = $row->status === 'confirmed' ? 'text-success' : 'text-muted';
                    $statusIcon = $row->status === 'confirmed' ? 'ph-shield-check' : 'ph-shield-warning';
                    $statusHtml = '<span class="d-inline-flex align-items-center '.$statusClass.'"><i class="'.$statusIcon.' fs-sm me-1"></i>'.ucfirst($row->status).'</span>';

                    $bottomRowHtml = '<div class="d-flex align-items-center gap-1 text-muted">' . $primaryHtml . ' | ' . $statusHtml . '</div>';

                    return '<div class="d-flex flex-column gap-0 small">' . $matchmakingHtml . $bottomRowHtml . '</div>';
               })

               /*
               |--------------------------------------------------------------------------
               | Activity
               |--------------------------------------------------------------------------
               */
               ->addColumn('activity', function ($row) {
                    return '
                         <div class="small">
                              <div>'.optional($row->created_at)->format('d M Y').'</div>
                              <div class="text-muted">'.optional($row->updated_at)->diffForHumans().'</div>
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Actions
               |--------------------------------------------------------------------------
               */
               ->addColumn('actions', function ($row) use ($event, $eventOrganization) {
                    $actions = '';

                    $actions .= '<div class="dropdown-item p-0 d-flex justify-content-start align-items-center">'.
                         action_button('view', [
                              'label'  => 'Representative Profile',
                              'title'  => '',
                              'href' => route('events.organizations.representatives.profile',[$event, $eventOrganization, $row]),
                              'class'  => 'd-flex justify-content-start align-items-center w-100 text-start border-0 bg-transparent py-1 px-3 text-dark d-block m-0',
                              'color'  => '',
                              'icon'  => 'ph-user',
                         ]).
                    '</div>';

                    $actions .= '<div class="dropdown-item p-0 d-flex justify-content-start align-items-center">'.
                         action_button('view', [
                              'label'  => 'Availability',
                              'title'  => '',
                              'href' => route('events.organizations.representatives.availability.index',[$event, $eventOrganization, $row]),
                              'class'  => 'd-flex justify-content-start align-items-center w-100 text-start border-0 bg-transparent py-1 px-3 text-dark d-block m-0',
                              'color'  => '',
                              'icon'  => 'ph-eye',
                         ]).
                    '</div>';

                    $actions .= '<div class="dropdown-item p-0 d-flex justify-content-start align-items-center">'.
                         action_button('edit', [
                              'modal-size' => 'modal-md',
                              'label'  => 'Edit Representative',
                              'title'  => 'Edit Representative',
                              'editUrl' => route('events.organizations.representatives.edit',[$event, $eventOrganization, $row]),
                              'updateUrl' => route('events.organizations.representatives.update',[$event, $eventOrganization, $row]),
                              'class'  => 'd-flex justify-content-start align-items-center w-100 text-start border-0 bg-transparent py-1 px-3 text-dark d-block m-0',
                              'table' => 'representativesTable',
                              'color'  => '',
                              'icon'  => 'ph-pencil-simple',
                         ]).
                    '</div>';                    

                    $actions .= '<div class="dropdown-item p-0 d-flex justify-content-start align-items-center">'.
                         action_button('sendAccess', [
                              'label'  => 'Login Access Email',
                              'title'  => '',
                              'url' => route('events.organizations.representatives.send-access',[$event, $eventOrganization, $row]),
                              'method' => 'POST',
                              'class'  => 'd-flex justify-content-start align-items-center w-100 text-start border-0 bg-transparent py-1 px-3 text-dark d-block m-0',
                              'table' => 'representativesTable',
                              'color'  => '',
                              'icon'  => 'ph-paper-plane-tilt',
                         ]).
                    '</div>';

                    $actions .= '<div class="dropdown-item p-0 d-flex justify-content-start align-items-center">'.
                         action_button('resetAccess', [
                              'label'  => 'Password Reset Email',
                              'title'  => '',
                              'url' => route('events.organizations.representatives.reset-access',[$event, $eventOrganization, $row]),
                              'method' => 'POST',
                              'class'  => 'd-flex justify-content-start align-items-center w-100 text-start border-0 bg-transparent py-1 px-3 text-dark d-block m-0',
                              'table' => 'representativesTable',
                              'color'  => '',
                              'icon'  => 'ph-key',
                         ]).
                    '</div>';

                    if (!empty($actions)) {
                         $actions .= '<div class="dropdown-divider"></div>';
                    }
                    $actions .= '<div class="dropdown-item p-0 d-flex justify-content-start align-items-center">'.
                         action_button('delete', [
                              'label'  => 'Delete Event',
                              'title'  => 'Delete Event',
                              'url' => route('events.organizations.representatives.destroy',[$event, $eventOrganization, $row]),
                              'method' => 'DELETE',
                              'table'  => 'representativesTable',
                              'class'  => 'd-flex justify-content-start align-items-center w-100 text-start border-0 bg-transparent py-1 px-3 text-danger d-block m-0',
                              'color'  => '',
                              'icon'  => 'ph-trash',
                         ]).
                    '</div>';
                    
                    return '<div class="dropdown">
                         <button type="button" class="btn btn-sm  px-2 btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                              <i class="ph-dots-three-vertical ph-sm me-1"></i>
                         </button>
                         <div class="dropdown-menu dropdown-menu-end py-1">'.$actions.'</div>
                    </div>';
               })
               ->rawColumns(['profile','contact','networking_view','activity','actions',])
               ->with('custom_button', [
                    'button_html' => $addButtonHtml,
               ])
               ->make(true);
     }

     public function create(Event $event,EventOrganization $eventOrganization): View {
          abort_unless(
               auth()->user()->canPermission('representatives.create'),
               403
          );

          $organizationUsers = OrganizationUser::query()
               ->with('user')
               ->where('organization_id', $eventOrganization->organization_id)
               ->where('is_admin', false)
               ->active()
               ->whereDoesntHave('participants', function ($query) use ($eventOrganization) {
                    $query->where(
                         'event_organization_id',
                         $eventOrganization->id
                    );
               })
               ->orderBy('designation')
               ->orderBy('user_id')
               ->get();

          return view(
               'event_management.workspace.organizations.representatives.partials.form',
               compact(
                    'event',
                    'eventOrganization',
                    'organizationUsers'
               )
          );
     }

     public function store(Request $request,Event $event,EventOrganization $eventOrganization) {
          abort_unless(
               auth()->user()->canPermission('representatives.create'),
               403
          );

          $request->validate([
               'organization_user_id' => [
                    'required',
                    Rule::exists(
                         'organization_users',
                         'id'
                    ),
               ],
               'is_primary' => [
                    'nullable',
                    'boolean',
               ],
               'matchmaking_enabled' => [
                    'nullable',
                    'boolean',
               ],
          ]);

          $organizationUser = OrganizationUser::findOrFail(
               $request->organization_user_id
          );

          $exists = EventParticipant::where('event_organization_id',$eventOrganization->id)
               ->where('organization_user_id',$organizationUser->id)
               ->exists();

          abort_if($exists,422,
               'Representative has already been added to this event.'
          );

          EventParticipant::create([
               'event_organization_id' => $eventOrganization->id,
               'organization_user_id' => $organizationUser->id,
               'is_primary' => $request->boolean('is_primary'),
               'badge_name' => $organizationUser->display_name,
               'badge_title' => $organizationUser->designation,
               'matchmaking_enabled' => $request->boolean('matchmaking_enabled'),
               'status' => EventParticipant::STATUS_REGISTERED,
               'created_by' => auth()->id(),
               'updated_by' => auth()->id(),
          ]);

          return response()->json([
               'status' => 'success',
               'message' => 'Representative added successfully.',
          ]);
     }

     public function edit(Event $event,EventOrganization $eventOrganization,EventParticipant $eventParticipant): View {
          abort_unless(
               auth()->user()->canPermission('representatives.edit'),
               403
          );

          abort_unless(
               $eventParticipant->event_organization_id === $eventOrganization->id,
               404
          );

          $eventParticipant->load([
               'organizationUser.user',
          ]);

          return view(
               'event_management.workspace.organizations.representatives.partials.form',
               array_merge(
                    $this->workspace($event),
                    [
                         'eventOrganization' => $eventOrganization,
                         'eventParticipant'  => $eventParticipant,
                         'organizationUsers' => collect([
                              $eventParticipant->organizationUser,
                         ]),
                    ]
               )
          );
     }

     public function update(Request $request,Event $event,EventOrganization $eventOrganization,EventParticipant $eventParticipant) {
          abort_unless(
               auth()->user()->canPermission('representatives.edit'),
               403
          );

          abort_unless(
               $eventParticipant->event_organization_id === $eventOrganization->id,
               404
          );

          $request->validate([
               'is_primary' => [
                    'nullable',
                    'boolean',
               ],
               'status' => [
                    'required',
                    Rule::in(
                         EventParticipant::statuses()
                    ),
               ],
               'matchmaking_enabled' => [
                    'nullable',
                    'boolean',
               ],
          ]);

          /*
          |--------------------------------------------------------------------------
          | Only One Primary Representative
          |--------------------------------------------------------------------------
          */
          if ($request->boolean('is_primary')) {
               EventParticipant::where(
                    'event_organization_id',
                    $eventOrganization->id
               )
               ->whereKeyNot($eventParticipant->id)
               ->update([
                    'is_primary' => false,
               ]);
          }

          $eventParticipant->update([
               'is_primary' => $request->boolean('is_primary'),
               'status' => $request->status,
               'matchmaking_enabled' => $request->boolean(
                    'matchmaking_enabled'
               ),
               'updated_by' => auth()->id(),
          ]);

          return response()->json([
               'status'  => 'success',
               'message' => 'Representative updated successfully.',
          ]);
     }

     public function destroy(Event $event,EventOrganization $eventOrganization,EventParticipant $eventParticipant) {
          abort_unless(
               auth()->user()->canPermission('representatives.delete'),
               403
          );

          abort_unless(
               $eventParticipant->event_organization_id === $eventOrganization->id,
               404
          );

          $eventParticipant->delete();

          return response()->json([
               'status'  => 'success',
               'message' => 'Representative removed successfully.',
          ]);
     }

     public function sendAccess(Event $event,EventOrganization $eventOrganization,EventParticipant $eventParticipant): JsonResponse {
          abort_unless(
               auth()->user()->canPermission('representatives.edit'),
               403
          );

          abort_unless(
               $eventParticipant->event_organization_id === $eventOrganization->id,
               404
          );

          $eventParticipant->load([
               'organizationUser.user',
               'eventOrganization.organization',
               'eventOrganization.event',
          ]);

          $user = $eventParticipant->organizationUser?->user;

          abort_if(
               blank($user?->email),
               422,
               'Representative email address is not available.'
          );

          /*
          |--------------------------------------------------------------------------
          | Send Event Access
          |--------------------------------------------------------------------------
          */
          $user->notify(
               new EventAccessNotification(
                    $eventParticipant,
                    route('login')
               )
          );

          return response()->json([
               'status'  => 'success',
               'message' => 'Access email sent successfully.',
          ]);
     }

     public function resetAccess(Event $event,EventOrganization $eventOrganization,EventParticipant $eventParticipant): JsonResponse {
          abort_unless(
               auth()->user()->canPermission('representatives.edit'),
               403
          );

          abort_unless(
               $eventParticipant->event_organization_id === $eventOrganization->id,
               404
          );

          $eventParticipant->load('organizationUser.user');

          $user = $eventParticipant->organizationUser?->user;

          abort_if(
               blank($user?->email),
               422,
               'Representative email address is not available.'
          );

          /*
          |--------------------------------------------------------------------------
          | Send Password Reset Email
          |--------------------------------------------------------------------------
          */

          $status = Password::sendResetLink([
               'email' => $user->email,
          ]);

          abort_if(
               $status !== Password::RESET_LINK_SENT,
               422,
               __($status)
          );

          return response()->json([
               'status'  => 'success',
               'message' => 'Password reset email sent successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Profile
     |--------------------------------------------------------------------------
     */
     public function profile(Event $event,EventOrganization $eventOrganization,EventParticipant $eventParticipant): View {
          /*
          |--------------------------------------------------------------------------
          | Safety Boundary
          |--------------------------------------------------------------------------
          |
          | Event Organization must belong to the current event.
          |
          */
          abort_unless(
               (int) $eventOrganization->event_id === (int) $event->id,
               404
          );

          /*
          |--------------------------------------------------------------------------
          | Participant Boundary
          |--------------------------------------------------------------------------
          |
          | Event Participant must belong to the supplied Event Organization.
          |
          */
          abort_unless(
               (int) $eventParticipant->event_organization_id
                    ===
               (int) $eventOrganization->id,
               404
          );

          /*
          |--------------------------------------------------------------------------
          | Event Participant
          |--------------------------------------------------------------------------
          */
          $eventParticipant->load([
               'organizationUser.user',
               'eventOrganization.organization.organizationType',
               'eventOrganization.participantType',
          ]);

          /*
          |--------------------------------------------------------------------------
          | Organization User
          |--------------------------------------------------------------------------
          */
          $organizationUser = $eventParticipant->organizationUser;

          /*
          |--------------------------------------------------------------------------
          | User
          |--------------------------------------------------------------------------
          */
          $user = $organizationUser?->user;

          /*
          |--------------------------------------------------------------------------
          | Organization
          |--------------------------------------------------------------------------
          */
          $organization = $eventParticipant->eventOrganization?->organization;

          /*
          |--------------------------------------------------------------------------
          | Availability
          |--------------------------------------------------------------------------
          */
          $availabilityQuery = ParticipantAvailability::query()
               ->where(
                    'event_participant_id',
                    $eventParticipant->id
               );

          /*
          |--------------------------------------------------------------------------
          | Preferred
          |--------------------------------------------------------------------------
          */
          $preferredCount =
               (clone $availabilityQuery)
                    ->where(
                         'status',
                         ParticipantAvailability::STATUS_PREFERRED
                    )
                    ->count();

          /*
          |--------------------------------------------------------------------------
          | Available
          |--------------------------------------------------------------------------
          */
          $availableCount =
               (clone $availabilityQuery)
                    ->where(
                         'status',
                         ParticipantAvailability::STATUS_AVAILABLE
                    )
                    ->count();

          /*
          |--------------------------------------------------------------------------
          | Unavailable
          |--------------------------------------------------------------------------
          */
          $unavailableCount =
               (clone $availabilityQuery)
                    ->where(
                         'status',
                         ParticipantAvailability::STATUS_UNAVAILABLE
                    )
                    ->count();

          /*
          |--------------------------------------------------------------------------
          | Total Availability Records
          |--------------------------------------------------------------------------
          */
          $totalAvailability = $preferredCount + $availableCount + $unavailableCount;

          /*
          |--------------------------------------------------------------------------
          | Availability Percentage
          |--------------------------------------------------------------------------
          |
          | Preferred and Available are both meeting-eligible.
          |
          */
          $availabilityPercentage =
               $totalAvailability > 0
                    ? round(
                         (
                              (
                                   $preferredCount
                                   +
                                   $availableCount
                              )
                              /
                              $totalAvailability
                         )
                         * 100
                    )

                    : 0;

          /*
          |--------------------------------------------------------------------------
          | Meeting Requests
          |--------------------------------------------------------------------------
          */
          $meetingRequests = MeetingRequest::query()
               ->where(function ($query) use ($eventParticipant) {
                    $query
                         ->where(
                              'sender_participant_id',
                              $eventParticipant->id
                         )
                         ->orWhere(
                              'receiver_participant_id',
                              $eventParticipant->id
                         );
               });

          /*
          |--------------------------------------------------------------------------
          | Meetings
          |--------------------------------------------------------------------------
          */
          $meetings = Meeting::query()
               ->where(function ($query) use ($eventParticipant) {
                    $query
                         ->where(
                              'sender_participant_id',
                              $eventParticipant->id
                         )
                         ->orWhere(
                              'receiver_participant_id',
                              $eventParticipant->id
                         );
               });

          /*
          |--------------------------------------------------------------------------
          | Meeting Statistics
          |--------------------------------------------------------------------------
          */
          $meetingRequestsCount = (clone $meetingRequests)->count();
          $scheduledMeetings = (clone $meetings)->count();
          $completedMeetings =
               (clone $meetings)
                    ->where(
                         'status',
                         Meeting::STATUS_COMPLETED
                    )
                    ->count();

          /*
          |--------------------------------------------------------------------------
          | Success Rate
          |--------------------------------------------------------------------------
          */
          $successRate = $scheduledMeetings > 0
               ? round(
                    (
                         $completedMeetings
                         /
                         $scheduledMeetings
                    )
                    * 100
               )

               : 0;

          /*
          |--------------------------------------------------------------------------
          | Profile Completion
          |--------------------------------------------------------------------------
          */
          $completion = 0;

          /*
          |--------------------------------------------------------------------------
          | Avatar
          |--------------------------------------------------------------------------
          */
          if (! empty($user?->avatar)) {
               $completion += 20;
          }

          /*
          |--------------------------------------------------------------------------
          | Phone
          |--------------------------------------------------------------------------
          */
          if (! empty($user?->phone)) {
               $completion += 20;
          }

          /*
          |--------------------------------------------------------------------------
          | Designation
          |--------------------------------------------------------------------------
          */
          if (! empty($organizationUser?->designation)) {
               $completion += 20;
          }

          /*
          |--------------------------------------------------------------------------
          | Bio
          |--------------------------------------------------------------------------
          |
          | Assuming bio belongs to OrganizationUser in V3.
          |
          */
          if (! empty($organizationUser?->bio)) {
               $completion += 20;
          }

          /*
          |--------------------------------------------------------------------------
          | Event Role
          |--------------------------------------------------------------------------
          */
          if (! empty($eventParticipant?->role_in_event)) {
               $completion += 20;
          }

          /*
          |--------------------------------------------------------------------------
          | Statistics
          |--------------------------------------------------------------------------
          */
          $stats = [
               /*
               |--------------------------------------------------------------------------
               | Availability
               |--------------------------------------------------------------------------
               */
               'availability' => [
                    'percentage' => $availabilityPercentage,
                    'preferred' => $preferredCount,
                    'available' => $availableCount,
                    'unavailable' => $unavailableCount,
               ],

               /*
               |--------------------------------------------------------------------------
               | Meetings
               |--------------------------------------------------------------------------
               */
               'meetings' => [
                    'requests' => $meetingRequestsCount,
                    'scheduled' => $scheduledMeetings,
                    'completed' => $completedMeetings,
               ],

               /*
               |--------------------------------------------------------------------------
               | Success Rate
               |--------------------------------------------------------------------------
               */
               'success_rate' => $successRate,

               /*
               |--------------------------------------------------------------------------
               | Profile Completion
               |--------------------------------------------------------------------------
               */
               'completion' => $completion,
          ];

          /*
          |--------------------------------------------------------------------------
          | View
          |--------------------------------------------------------------------------
          */
          return view(
               'event_management.workspace.organizations.representatives.profile',
               array_merge(
                    $this->workspace($event),
                    [
                         'eventOrganization' => $eventOrganization,
                         'eventParticipant' => $eventParticipant,
                         'organizationUser' => $organizationUser,
                         'user' => $user,
                         'organization' => $organization,
                         'stats' => $stats,
                    ]
               )
          );
     }
}