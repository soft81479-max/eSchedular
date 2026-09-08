<?php

namespace App\Http\Controllers\EventManagement\Workspace;

use App\Models\Event;
use App\Models\EventOrganization;
use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\EventParticipant;
use App\Models\ParticipantType;

use App\Models\Meeting;
use App\Models\MeetingRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Validation\Rule;

class OrganizationController extends WorkspaceController {
     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     */
     public function index(Event $event): View {
          abort_unless(auth()->user()->canPermission('organizations.view'),403);

          return view(
               'event_management.workspace.organizations.index',
               $this->workspace($event)
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Datatable
     |--------------------------------------------------------------------------
     */
     public function datatable(Request $request,Event $event) {
          abort_unless(auth()->user()->canPermission('organizations.view'),
               403
          );

          if ($request->ajax()) {
               /*
               |--------------------------------------------------------------------------
               | Add Button
               |--------------------------------------------------------------------------
               */
               $addButtonHtml = '';

               if (auth()->user()->canPermission('organizations.create')) {
                    $addButtonHtml = action_button('add', [
                         'modal-size' => 'modal-md','label' => 'Add','title' => 'Add Organization to Event','icon' => '','color' => 'warning',
                         'createUrl' => route('events.organizations.create', $event),
                         'storeUrl' => route('events.organizations.store', $event),
                         'table' => 'organizationsTable'
                    ]);
               }

               /*
               |--------------------------------------------------------------------------
               | Query
               |--------------------------------------------------------------------------
               */
               $query = EventOrganization::query()
                    ->with(['organization','organization.organizationType','participantType',
                         'organization.organizationUsers' => function ($query) {
                              $query->where('is_admin', false);
                         },
                    ])
                    ->withCount('participants','booth',)
                    ->where('event_id', $event->id);

               return DataTables::eloquent($query)
                    ->addIndexColumn()
                    ->setRowClass('table-border-double')
                    ->addColumn('participant_type_label', function ($row) {
                         return optional($row->participantType)->name ?? 'Other';
                    })
                    ->addColumn('organization_view', function ($row) {
                         $organization = $row->organization;
                         $logo = $organization->logo_url;
                         $organizationType = optional($organization->organizationType)->name;
                         $participantType = optional($row->participantType)->name;
                         return '
                              <div class="d-flex align-items-center">
                                   <div><img src="'.$logo.'" class="rounded w-48px h-48px img-fluid me-2" style="object-fit:cover;"></div>
                                   <div class="flex-fill">
                                        <div class="fw-semibold">'.e($organization?->name).'</div>
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                             '.($organizationType ? '<span class="badge bg-light text-dark border fs-xs">'.e($organizationType).'</span>' : '').'
                                             '.($participantType ? '<span class="badge bg-primary fs-xs">'.e($participantType).'</span>' : '').'
                                        </div>
                                   </div>
                              </div>
                         ';
                    })
                    ->addColumn('networking_view', function ($row) {
                         $items = [];
                         if ($row->matchmaking_enabled) {
                              $items[] = '<span class="d-inline-flex align-items-center text-success"><i class="ph-lightning fs-sm me-1"></i>Matchmaking</span>';
                         }
                         $items[] = '<span class="d-inline-flex align-items-center text-muted"><i class="ph-eye fs-sm me-1"></i>'.ucfirst($row->visibility_status ?? 'public').'</span>';
                         $items[] = '<span class="d-inline-flex align-items-center text-muted"><i class="ph-shield-check fs-sm me-1"></i>'.ucfirst(str_replace('_',' ',$row->networking_access_level ?? 'full')).'</span>';

                         return '<div class="d-flex flex-column gap-0 small">'.implode('', $items).'</div>';
                    })
                    ->addColumn('booth_view', function ($row) {
                         if (! $row->booth) {
                              return '<span class="badge fs-xs bg-light text-muted border w-100">Not Assigned</span>';
                         }

                         return '
                              <div class="d-flex flex-column gap-0 small">
                                   <span class="d-inline-flex align-items-center text-success"><i class="ph-storefront fs-sm me-1"></i>'.e($row->booth->code).'</span>
                                   <span class="d-inline-flex align-items-center text-muted">'.e($row->booth->location ?: 'N/A').'</span>
                              </div>
                         ';
                    })
                    ->addColumn('representatives_view', function ($row) {
                         return '<span class="badge bg-success fs-xs">' . $row->participants_count . '</span>';
                    })
                    ->addColumn('status_view', function ($row) {
                         return '<span class="badge ' . $row->status_badge_class . ' fs-xs">' . $row->status_label . '</span>';
                    })
                    ->addColumn('actions', function ($row) use ($event) {
                         $buttons = '';
                    
                         $buttons .=action_button('view', [
                              'href' => route('events.organizations.profile',[$event, $row]),
                              'btn-type' => 'btn-icon',
                              'color' => 'info',
                              'icon' => 'ph-user-circle',
                              'title' => 'Profile'
                         ]);

                         // REPRESENTATIVES
                         $buttons .= action_button('view', [
                              'btn-type' => 'btn-icon',
                              'href' => route('events.organizations.representatives.index',[$event, $row]),
                              'title' => 'Representatives',
                              'icon' => 'ph-users',
                              'color' => 'light'
                         ]);

                         // EDIT
                         if (auth()->user()->canPermission('organizations.edit')) {
                              $buttons .= action_button('edit', [
                                   'modal-size' => 'modal-md',
                                   'btn-type' => 'btn-icon',
                                   'title' => 'Edit Organization',
                                   'icon' => 'ph-pencil',
                                   'color' => 'primary',
                                   'editUrl' => route('events.organizations.edit',[$event, $row]),
                                   'updateUrl' => route('events.organizations.update',[$event, $row])
                              ]);
                         }

                         // DELETE
                         if (auth()->user()->canPermission('organizations.delete')) {
                              $buttons .= action_button('delete', [
                                   'btn-type' => 'btn-icon',
                                   'title' => 'Delete Organization',
                                   'icon' => 'ph-trash',
                                   'color' => 'danger',
                                   'url' => route('events.organizations.destroy',[$event, $row]),
                                   'method' => 'DELETE'
                              ]);
                         }
                         return '<div class="hstack gap-1">' . $buttons . '</div>';
                    })
                    ->rawColumns(['participant_type_label','organization_view','networking_view','booth_view','representatives_view','status_view','actions'])
                    ->with('custom_button', [
                         'button_html' => $addButtonHtml,
                    ])
                    ->make(true);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Create
     |--------------------------------------------------------------------------
     */
     public function create(Event $event): View {
          abort_unless(
               auth()->user()->canPermission('organizations.create'),
               403
          );

          /*
          |--------------------------------------------------------------------------
          | Available Organizations
          |--------------------------------------------------------------------------
          */
          $organizations = Organization::query()
               ->with(['organizationType',])
               ->where('organizer_id', $event->organizer_id)
               ->where('status', Organization::STATUS_ACTIVE)
               ->where('is_verified', true)
               ->whereNotIn('id', function ($query) use ($event) {
                    $query->select('organization_id')
                         ->from('event_organizations')
                         ->where('event_id', $event->id);
               })
               ->orderBy('name')
               ->get();

          /*
          |--------------------------------------------------------------------------
          | Marketplace Roles
          |--------------------------------------------------------------------------
          */
          $participantTypes = $event->participantTypes()
               ->active()
               ->orderBy('sort_order')
               ->get();

          return view(
               'event_management.workspace.organizations.partials.form',
               array_merge(
                    $this->workspace($event),
                    [
                         'eventOrganization' => new EventOrganization(),
                         'organizations'     => $organizations,
                         'participantTypes'  => $participantTypes,
                    ]
               )
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Store
     |--------------------------------------------------------------------------
     */
     public function store(Request $request,Event $event) {
          abort_unless(
               auth()->user()->canPermission('organizations.create'),
               403
          );

          $request->validate([
               'organization_id'           => ['required', 'exists:organizations,id'],
               'event_participant_type_id' => ['required', 'exists:event_participant_types,id'],
               'status'                    => ['required', Rule::in(EventOrganization::statuses())],
               'visibility_status'         => ['required', Rule::in(EventOrganization::visibilityStatuses())],
               'networking_access_level'   => ['required', Rule::in(EventOrganization::accessLevels())],
               'matchmaking_enabled'       => ['nullable', 'boolean'],
          ]);

          /*
          |--------------------------------------------------------------------------
          | Prevent Duplicate
          |--------------------------------------------------------------------------
          */
          abort_if(
               EventOrganization::where('event_id', $event->id)
                    ->where('organization_id', $request->organization_id)
                    ->exists(),
               422,
               'Organization has already been added to this event.'
          );

          /*
          |--------------------------------------------------------------------------
          | Store
          |--------------------------------------------------------------------------
          */
          EventOrganization::create([
               'event_id'                 => $event->id,
               'organization_id'          => $request->organization_id,
               'event_participant_type_id'=> $request->event_participant_type_id,
               'status'                   => $request->status,
               'visibility_status'        => $request->visibility_status,
               'networking_access_level'  => $request->networking_access_level,
               'matchmaking_enabled'      => $request->boolean('matchmaking_enabled'),
               'created_by'               => auth()->id(),
               'updated_by'               => auth()->id(),
          ]);

          return response()->json([
               'status'  => 'success',
               'message' => 'Organization added to event successfully.',
          ]);
     }

     public function edit(Event $event, EventOrganization $eventOrganization): View {
          abort_unless(
               auth()->user()->canPermission('organizations.edit'),
               403
          );

          /*
          |--------------------------------------------------------------------------
          | Marketplace Roles
          |--------------------------------------------------------------------------
          */
          $participantTypes = $event->participantTypes()
               ->active()
               ->orderBy('sort_order')
               ->get();

          /*
          |--------------------------------------------------------------------------
          | Load Relationships
          |--------------------------------------------------------------------------
          */
          $eventOrganization->load([
               'organization.organizationType',
               'participantType',
          ]);

          return view(
               'event_management.workspace.organizations.partials.form',
               array_merge(
                    $this->workspace($event),
                    [
                         'eventOrganization' => $eventOrganization,
                         'organizations'     => collect([
                              $eventOrganization->organization,
                         ]),
                         'participantTypes'  => $participantTypes,
                    ]
               )
          );
     }

     public function update(Request $request,Event $event,EventOrganization $eventOrganization) {
          abort_unless(
               auth()->user()->canPermission('organizations.edit'),
               403
          );

          $request->validate([
               'event_participant_type_id' => [
                    'required',
                    Rule::exists(
                         'event_participant_types',
                         'id'
                    )->where(
                         'event_id',
                         $event->id
                    ),
               ],

               'status' => [
                    'required',
                    Rule::in(
                         array_keys(
                              EventOrganization::statuses()
                         )
                    ),
               ],

               'visibility_status' => [
                    'required',
                    Rule::in(
                         EventOrganization::visibilityStatuses()
                    ),
               ],

               'networking_access_level' => [
                    'required',
                    Rule::in(
                         EventOrganization::accessLevels()
                    ),
               ],

               'matchmaking_enabled' => [
                    'nullable',
                    'boolean',
               ],

          ]);

          $eventOrganization->update([
               'event_participant_type_id' => $request->event_participant_type_id,
               'status' => $request->status,
               'visibility_status' => $request->visibility_status,
               'networking_access_level' => $request->networking_access_level,
               'matchmaking_enabled' => $request->boolean('matchmaking_enabled'),
               'updated_by' => auth()->id(),
          ]);

          return response()->json([
               'status'  => 'success',
               'message' => 'Organization updated successfully.',
          ]);
     }

     public function destroy(Event $event,EventOrganization $eventOrganization) {
          abort_unless(
               auth()->user()->canPermission('organizations.delete'),
               403
          );

          /*
          |--------------------------------------------------------------------------
          | Cannot Remove When Representatives Exist
          |--------------------------------------------------------------------------
          */
          abort_if(
               $eventOrganization->participants()->exists(),
               422,
               'Remove all representatives from this organization before removing it from the event.'
          );

          /*
          |--------------------------------------------------------------------------
          | Cannot Remove When Booth Assigned
          |--------------------------------------------------------------------------
          */
          abort_if(
               $eventOrganization->booth()->exists(),
               422,
               'Remove the booth assignment before removing this organization.'
          );

          /*
          |--------------------------------------------------------------------------
          | Delete
          |--------------------------------------------------------------------------
          */
          $eventOrganization->delete();

          return response()->json([
               'status'  => 'success',
               'message' => 'Organization removed from the event successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Profile
     |--------------------------------------------------------------------------
     */
     public function profile(Event $event,EventOrganization $eventOrganization): View {
          /*
          |--------------------------------------------------------------------------
          | Safety Boundary
          |--------------------------------------------------------------------------
          */
          abort_unless(
               (int) $eventOrganization->event_id === (int) $event->id,
               404
          );

          /*
          |--------------------------------------------------------------------------
          | Event Organization
          |--------------------------------------------------------------------------
          */
          $eventOrganization->load([
               'organization.organizationType',
               'participantType',
               'participants.organizationUser.user',
          ]);

          /*
          |--------------------------------------------------------------------------
          | Organization
          |--------------------------------------------------------------------------
          */
          $organization = $eventOrganization->organization;

          /*
          |--------------------------------------------------------------------------
          | Event Participants
          |--------------------------------------------------------------------------
          |
          | Event-specific representatives only.
          |--------------------------------------------------------------------------
          */
          $participants =
               $eventOrganization
                    ->participants
                    ->sortBy(
                         fn ($participant) =>
                              $participant->organizationUser?->user?->name
                    )
                    ->values();

          /*
          |--------------------------------------------------------------------------
          | Participant IDs
          |--------------------------------------------------------------------------
          */
          $participantIds = $participants
               ->pluck('id')
               ->filter()
               ->values();

          /*
          |--------------------------------------------------------------------------
          | Representatives Count
          |--------------------------------------------------------------------------
          */
          $representativesCount =
               $participantIds->count();

          /*
          |--------------------------------------------------------------------------
          | Meeting Requests
          |--------------------------------------------------------------------------
          */
          $meetingRequests = MeetingRequest::query()
               ->where(function ($query) use ($participantIds) {
                    $query
                         ->whereIn(
                              'sender_participant_id',
                              $participantIds
                         )
                         ->orWhereIn(
                              'receiver_participant_id',
                              $participantIds
                         );
               });

          /*
          |--------------------------------------------------------------------------
          | Meetings
          |--------------------------------------------------------------------------
          */
          $meetings = Meeting::query()
               ->where(function ($query) use ($participantIds) {
                    $query
                         ->whereIn(
                              'sender_participant_id',
                              $participantIds
                         )
                         ->orWhereIn(
                              'receiver_participant_id',
                              $participantIds
                         );
               });

          /*
          |--------------------------------------------------------------------------
          | Statistics
          |--------------------------------------------------------------------------
          */
          $requestsCount = (clone $meetingRequests)->count();
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
          | Statistics
          |--------------------------------------------------------------------------
          */
          $stats = [
               'representatives' => $representativesCount,
               'requests' => $requestsCount,
               'scheduled' => $scheduledMeetings,
               'completed' => $completedMeetings,
               'success_rate' => $successRate,
          ];

          /*
          |--------------------------------------------------------------------------
          | View
          |--------------------------------------------------------------------------
          */
          return view(
               'event_management.workspace.organizations.profile',
               array_merge(
                    $this->workspace($event),
                    [
                         'eventOrganization' => $eventOrganization,
                         'organization' => $organization,
                         'participants' => $participants,
                         'stats' => $stats,
                    ]
               )
          );
     }
}