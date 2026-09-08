<?php

namespace App\Http\Controllers\MyEvents;

use App\Models\Event;
use App\Models\Meeting;
use App\Models\EventParticipant;
use App\Models\MeetingRequest;
use App\Services\MyEvents\MeetingService;
use App\Services\Networking\MeetingRequestService;
use App\Services\Networking\MeetingLifecycleService;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class MeetingController extends WorkspaceController {
     /*
     |--------------------------------------------------------------------------
     | Constructor
     |--------------------------------------------------------------------------
     */
     public function __construct(protected MeetingRequestService $meetingRequestService,protected MeetingLifecycleService $meetingLifecycleService) {
          /*
          |--------------------------------------------------------------------------
          | Workspace Service
          |--------------------------------------------------------------------------
          |
          | WorkspaceController owns the workspaceService property.
          | Keep the existing initialization approach.
          |
          */
          $this->middleware(function ($request, $next) {
               $refProperty = new \ReflectionProperty(WorkspaceController::class,'workspaceService');
               $refProperty->setValue($this,app(\App\Services\MyEvents\MyEventWorkspaceService::class));
               return $next($request);
          });
     }

     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     | One page:
     | Requests
     | Meetings
     |--------------------------------------------------------------------------
     */
     public function index(Event $event,MeetingService $meetingService): View {
          /*
          |--------------------------------------------------------------------------
          | Workspace Service
          |--------------------------------------------------------------------------
          */
          $refProperty = new \ReflectionProperty(WorkspaceController::class,'workspaceService');
          $refProperty->setValue($this,app(\App\Services\MyEvents\MyEventWorkspaceService::class));

          /*
          |--------------------------------------------------------------------------
          | Workspace
          |--------------------------------------------------------------------------
          */
          $workspace = $this->workspace($event);
          $participant = $workspace['participant'];

          /*
          |--------------------------------------------------------------------------
          | Matchmaking
          |--------------------------------------------------------------------------
          |
          | The workspace toolbar determines whether this event has
          | a dedicated matchmaking module.
          |
          | Requests must still work when matchmaking is disabled.
          |
          */
          $hasMatchmaking = collect($workspace['toolbar'] ?? [])->contains(
               fn ($module) =>($module['key'] ?? null) === 'matchmaking'
          );

          /*
          |--------------------------------------------------------------------------
          | Statistics
          |--------------------------------------------------------------------------
          */
          $workspace['meetingStatistics'] =
               $meetingService->meetingStatistics(
                    $workspace['event'],
                    $workspace['participant']
               );

          /*
          |--------------------------------------------------------------------------
          | View Data
          |--------------------------------------------------------------------------
          */
          $workspace['hasMatchmaking'] = $hasMatchmaking;

          /*
          |--------------------------------------------------------------------------
          | View
          |--------------------------------------------------------------------------
          */
          return view(
               'my_events.meetings.index',
               $workspace
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Requests Datatable
     |--------------------------------------------------------------------------
     | This table represents the REQUEST workspace.
     | It can contain:
     | 1. Available opportunity
     | 2. Outgoing pending request
     | 3. Incoming pending request
     |
     | Accepted requests are deliberately excluded because they become
     | actual meetings and belong in the Meetings tab.
     |--------------------------------------------------------------------------
     */
     public function opportunitiesDatatable(Request $request,Event $event): JsonResponse {
          /*
          |--------------------------------------------------------------------------
          | Permission
          |--------------------------------------------------------------------------
          */
          abort_unless(
               auth()->user()->canPermission('my-events.view'),
               403
          );

          /*
          |--------------------------------------------------------------------------
          | Workspace
          |--------------------------------------------------------------------------
          */
          $workspace = $this->workspace($event);
          $participant = $workspace['participant'];

          /*
          |--------------------------------------------------------------------------
          | Matchmaking
          |--------------------------------------------------------------------------
          */
          $hasMatchmaking = collect($workspace['toolbar'] ?? [] )->contains(
               fn ($module) =>($module['key'] ?? null) === 'matchmaking'
          );

          /*
          |--------------------------------------------------------------------------
          | Filters
          |--------------------------------------------------------------------------
          */
          $filters = [
               'status' => $request->get('status'),
               'event_time_slot_id' =>
                    $request->get('event_time_slot_id'),
          ];

          /*
          |--------------------------------------------------------------------------
          | Request Workspace Rows
          |--------------------------------------------------------------------------
          |
          | MeetingRequestService is responsible for composing:
          |
          | - available opportunities
          | - pending requests
          |
          | The opportunity engine itself remains inside
          | MeetingOpportunityService.
          |
          */
          $rows =
               $this->meetingRequestService->workspaceRows(
                    event: $event,
                    participant: $participant,
                    filters: $filters,
                    includeOpportunities: ! $hasMatchmaking
               );

          /*
          |--------------------------------------------------------------------------
          | Datatable
          |--------------------------------------------------------------------------
          */
          return DataTables::of($rows)
               /*
               |--------------------------------------------------------------------------
               | Direction
               |--------------------------------------------------------------------------
               */
               ->addColumn('direction', function ($row) {
                    /*
                    |--------------------------------------------------------------------------
                    | Available Opportunity
                    |--------------------------------------------------------------------------
                    */
                    if (($row->row_type ?? null) === 'opportunity') {
                         return '<span class="d-flex justify-content-center fs-xs badge bg-light border text-dark"><i class="ph-user-plus fs-xs me-1"></i>Available</span>';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Outgoing
                    |--------------------------------------------------------------------------
                    */
                    if (($row->direction ?? null) === 'outgoing') {
                         return '<span class="d-flex justify-content-center fs-xs badge bg-primary"><i class="ph-arrow-up-right fs-xs me-1"></i>Sent</span>';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Incoming
                    |--------------------------------------------------------------------------
                    */
                    return '
                         <span class="d-flex justify-content-center fs-xs badge bg-info">
                              <i class="ph-arrow-down-left fs-xs me-1"></i>
                              Received
                         </span>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Meeting Date
               |--------------------------------------------------------------------------
               */
               ->addColumn('meeting_date', function ($row) {
                    return '
                         <div class="fw-semibold fs-sm">'.e(\Carbon\Carbon::parse($row->start_at)->format('d M Y')).'</div>
                         <div class="text-muted fs-xs">
                              '.e(\Carbon\Carbon::parse($row->start_at)->format('h:i A'))
                              .'-
                              '.e(\Carbon\Carbon::parse($row->end_at)->format('h:i A')).'
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Representative
               |--------------------------------------------------------------------------
               */
               ->addColumn('participant', function ($row) {
                    /*
                    |--------------------------------------------------------------------------
                    | Target Participant
                    |--------------------------------------------------------------------------
                    */
                    $target = $row->targetParticipant ?? null;
                    /*
                    |--------------------------------------------------------------------------
                    | Fallback
                    |--------------------------------------------------------------------------
                    |
                    | MeetingRequestService normally provides targetParticipant.
                    |
                    */
                    if (! $target) {
                         $target = $row->receiverParticipant ?? $row->senderParticipant ?? null;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | User
                    |--------------------------------------------------------------------------
                    */
                    $user = $target?->organizationUser?->user;

                    /*
                    |--------------------------------------------------------------------------
                    | Organization
                    |--------------------------------------------------------------------------
                    */
                    $organization = $target?->eventOrganization?->organization;

                    /*
                    |--------------------------------------------------------------------------
                    | Avatar
                    |--------------------------------------------------------------------------
                    */
                    $avatar = $user?->avatar ? asset('storage/' . $user->avatar) : asset('images/defaults/avatar.png');

                    /*
                    |--------------------------------------------------------------------------
                    | HTML
                    |--------------------------------------------------------------------------
                    */
                    return '
                         <div class="d-flex align-items-center">
                              <img src="' . $avatar . '" class="rounded-circle img-fluid me-2" width="30" height="30" style="object-fit:cover;">
                              <div>
                                   <div class="fw-semibold fs-sm">' . e($user?->name ?? '-') . '</div>
                                   <div class="text-muted fs-xs">' . e($target?->organizationUser?->designation ?? '-') . '</div>
                              </div>
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Organization
               |--------------------------------------------------------------------------
               */
               ->addColumn('organization', function ($row) {
                    /*
                    |--------------------------------------------------------------------------
                    | Target Participant
                    |--------------------------------------------------------------------------
                    */
                    $target = $row->targetParticipant ?? null;
                    /*
                    |--------------------------------------------------------------------------
                    | Fallback
                    |--------------------------------------------------------------------------
                    |
                    | MeetingRequestService normally provides targetParticipant.
                    |
                    */
                    if (! $target) {
                         $target = $row->receiverParticipant ?? $row->senderParticipant ?? null;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Organization
                    |--------------------------------------------------------------------------
                    */
                    $organization = $target?->eventOrganization?->organization;

                    /*
                    |--------------------------------------------------------------------------
                    | Logo
                    |--------------------------------------------------------------------------
                    */
                    $logo = $organization?->logo ? asset('storage/' . $organization->logo) : asset('images/defaults/logo.png');

                    /*
                    |--------------------------------------------------------------------------
                    | HTML
                    |--------------------------------------------------------------------------
                    */
                    return '
                         <div class="d-flex align-items-center">
                              <img src="' . $logo . '" class="rounded img-fluid me-2" width="30" height="25" style="object-fit:cover;">
                              <div>
                                   <div class="fw-semibold fs-sm">' . e($organization?->name ?? '-') . '</div>
                                   <div class="text-muted fs-xs">' . e($organization?->country?->name ?? '-') . '</div>
                              </div>
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Status
               |--------------------------------------------------------------------------
               */
               ->addColumn('status', function ($row) {
                    /*
                    |--------------------------------------------------------------------------
                    | Opportunity
                    |--------------------------------------------------------------------------
                    */
                    if (($row->row_type ?? null) === 'opportunity') {
                         return '<span class="d-flex justify-content-center fs-xs badge bg-light border text-dark">Available</span>';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Request Status
                    |--------------------------------------------------------------------------
                    */
                    $status = $row->meeting_request_status ?? $row->status ?? MeetingRequest::STATUS_PENDING;
                    $badge = match ($status) {
                         MeetingRequest::STATUS_PENDING => 'bg-warning text-white',
                         MeetingRequest::STATUS_ACCEPTED => 'bg-success',
                         MeetingRequest::STATUS_REJECTED => 'bg-danger',
                         MeetingRequest::STATUS_CANCELLED => 'bg-secondary',
                         default => 'bg-light text-white border',
                    };

                    return '<span class="badge fs-xs ' . $badge . '">' . e(ucfirst($status)) . '</span>';
               })

               /*
               |--------------------------------------------------------------------------
               | Mode
               |--------------------------------------------------------------------------
               */
               ->addColumn('meeting_mode', function ($row) {
                    $mode = $row->meeting_request_mode ?? $row->mode ?? null;
                    return '<span class="d-flex justify-content-center fs-xs badge bg-light border text-dark">' . e($mode? ucfirst($mode): '-') . '</span>';
               })

               /*
               |--------------------------------------------------------------------------
               | Actions
               |--------------------------------------------------------------------------
               */
               ->addColumn('actions', function ($row) use ($event) {                    
                    $direction = $row->direction ?? null;
                    $status = $row->meeting_request_status ?? $row->status ?? MeetingRequest::STATUS_PENDING;

                    // INCOMING & PENDING: Needs Accept, Reject, Cancel (Note: typically 'Cancel' is for outgoing, but included per request)
                    if ($direction === 'incoming' && $status === MeetingRequest::STATUS_PENDING) {
                         return action_button('view', [
                              'btn-type' => 'btn-icon',
                              'title' => 'Review Incoming Request',
                              'label' => '',
                              'icon' => 'ph-hand',
                              'color' => 'info',
                              'class' => 'btn-view-profile state-incoming-pending'
                         ]);
                    } 

                    // OUTGOING & PENDING: Needs Cancel
                    if ($direction === 'outgoing' && $status === MeetingRequest::STATUS_PENDING) {
                         return action_button('view', [
                              'btn-type' => 'btn-icon',
                              'title' => 'Cancel Request',
                              'label' => '',
                              'icon' => 'ph-hand',
                              'color' => 'primary',
                              'class' => 'btn-view-profile state-outgoing-pending'
                         ]);
                    }

                    // DEFAULT / FALLBACK (Your original logic)
                    if (! auth()->user()->canPermission('meetings.create')) {
                         return '-';
                    }

                    return action_button('view', [
                         'btn-type' => 'btn-icon',
                         'title' => 'Request Meeting',
                         'label' => '',
                         'icon' => 'ph-handshake',
                         'color' => 'primary',
                         'class' => 'btn-view-profile state-default'
                    ]);
               })

               /*
               ->addColumn('actions', function ($row) use ($event) {
                    $direction = $row->direction ?? null;

                    if (! auth()->user()->canPermission('meetings.create')) {
                         return '-';
                    }

                    if ($direction === 'incoming' && ($row->meeting_request_status ?? $row->status) === MeetingRequest::STATUS_PENDING) {
                         return action_button('view', [
                              'btn-type' => 'btn-icon',
                              'title' => 'Request Pending',
                              'label' => '',
                              'icon' => 'ph-hand',
                              'color' => 'info',
                         ]);
                    } 

                    if ($direction === 'outgoing' && ($row->meeting_request_status ?? $row->status) === MeetingRequest::STATUS_PENDING) {
                         return action_button('view', [
                              'btn-type' => 'btn-icon',
                              'title' => 'Cancel Request',
                              'label' => '',
                              'icon' => 'ph-hand',
                              'color' => 'primary',
                         ]);
                    }

                    return action_button('view', [
                         'btn-type' => 'btn-icon',
                         'title' => 'Request Meeting',
                         'label' => '',
                         'icon' => 'ph-handshake',
                         'color' => 'primary',
                         'class' => 'btn-view-profile',
                    ]);
               })
               */

               /*
               |--------------------------------------------------------------------------
               | Raw Columns
               |--------------------------------------------------------------------------
               */
               ->rawColumns(['direction','meeting_date','participant','organization','status','meeting_mode','actions'])
               ->make(true);
     }

     /*
     |--------------------------------------------------------------------------
     | Meetings Datatable
     |--------------------------------------------------------------------------
     | Only actual Meeting records are returned here.
     | Pending MeetingRequests do NOT appear here.
     |--------------------------------------------------------------------------
     */
     public function meetingsDatatable(Request $request,Event $event): JsonResponse {
          /*
          |--------------------------------------------------------------------------
          | Permission
          |--------------------------------------------------------------------------
          */
          abort_unless(
               auth()->user()->canPermission('my-events.view'),
               403
          );

          /*
          |--------------------------------------------------------------------------
          | Workspace
          |--------------------------------------------------------------------------
          */
          $workspace = $this->workspace($event);
          $participant = $workspace['participant'];

          /*
          |--------------------------------------------------------------------------
          | Meeting Requests With Actual Meetings
          |--------------------------------------------------------------------------
          |
          | MeetingQueryService is deliberately not used here because the
          | representative workspace already has MeetingRequestService
          | as the scoped request layer.
          |
          */
          $meetings =
               $this->meetingRequestService
                    ->meetingsForParticipant(
                         event: $event,
                         participant: $participant,
                         filters: [
                         'status' => $request->get('status'),
                         'event_time_slot_id' =>
                              $request->get('event_time_slot_id'),
                         ]
                    );

          /*
          |--------------------------------------------------------------------------
          | Datatable
          |--------------------------------------------------------------------------
          */
          return DataTables::of($meetings)
               /*
               |--------------------------------------------------------------------------
               | Direction
               |--------------------------------------------------------------------------
               */
               ->addColumn('direction', function ($row) use ($participant) {
                    if ((int) $row->sender_participant_id === (int) $participant->id) {
                         return '
                         <span class="d-flex justify-content-center fs-xs badge bg-primary">
                              <i class="ph-arrow-up-right fs-xs me-1"></i>
                              Sent
                         </span>
                         ';
                    }

                    return '
                         <span class="d-flex justify-content-center fs-xs badge bg-info">
                              <i class="ph-arrow-down-left fs-xs me-1"></i>
                              Received
                         </span>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Meeting Date
               |--------------------------------------------------------------------------
               */
               ->addColumn('meeting_date', function ($row) {
                    return '
                         <div class="fw-semibold fs-sm">'.e(\Carbon\Carbon::parse($row->timeSlot->start_at)->format('d M Y')).'</div>
                         <div class="text-muted fs-xs">
                              '.e(\Carbon\Carbon::parse($row->timeSlot->start_at)->format('h:i A'))
                              .'-
                              '.e(\Carbon\Carbon::parse($row->timeSlot->end_at)->format('h:i A')).'
                         </div>
                    ';

                    if (! $row->timeSlot?->start_at) {
                         return '-';
                    }

                    return \Carbon\Carbon::parse($row->timeSlot->start_at)->format('d M Y');
               })

               /*
               |--------------------------------------------------------------------------
               | Representative
               |--------------------------------------------------------------------------
               */
               ->addColumn('participant', function ($row) use ($participant) {
                    $target = 
                         (int) $row->sender_participant_id ===
                         (int) $participant->id
                         ? $row->receiverParticipant
                         : $row->senderParticipant;

                    $user = $target?->organizationUser?->user;
                    $avatar = $user?->avatar ? asset('storage/' . $user->avatar) : asset('images/defaults/avatar.png');
                    return '
                         <div class="d-flex align-items-center">
                         <img src="' . $avatar . '" class="rounded-circle img-fluid me-2" width="30" height="30" style="object-fit:cover;">
                         <div>
                              <div class="fw-semibold fs-sm">' . e($user?->name ?? '-') . '</div>
                              <div class="text-muted fs-xs">' . e($target?->organizationUser?->designation ?? '-') . '</div>
                         </div>
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Organization
               |--------------------------------------------------------------------------
               */
               ->addColumn('organization', function ($row) use ($participant) {
                    $target = 
                         (int) $row->sender_participant_id ===
                         (int) $participant->id
                         ? $row->receiverParticipant
                         : $row->senderParticipant;

                    $organization = $target?->eventOrganization?->organization;
                    $logo = $organization?->logo ? asset('storage/' . $organization->logo) : asset('images/defaults/logo.png');
                    return '
                         <div class="d-flex align-items-center">
                         <img src="' . $logo . '" class="rounded img-fluid me-2" width="30" height="25" style="object-fit:cover;">
                         <div>
                              <div class="fw-semibold fs-sm">' . e($organization?->name ?? '-') . '</div>
                              <div class="text-muted fs-xs">' . e($organization?->country?->name ?? '-') . '</div>
                         </div>
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Check-In
               |--------------------------------------------------------------------------
               */
               ->addColumn('check_in', function ($row) {
                    /*
                    |--------------------------------------------------------------------------
                    | Existing Meeting Check-In
                    |--------------------------------------------------------------------------
                    |
                    | If MeetingService later exposes a dedicated check-in
                    | status, this column can consume it without changing
                    | the DataTable contract.
                    |
                    */
                    return '<span class="d-flex justify-content-center fs-xs badge bg-secondary">Pending</span>';
               })

               /*
               |--------------------------------------------------------------------------
               | Mode
               |--------------------------------------------------------------------------
               */
               ->addColumn('meeting_mode', function ($row) {
                    $mode = $row->meeting?->meeting_mode ?? $row->meeting_mode ?? $row->timeSlot?->mode ?? null;
                    return '<span class="d-flex justify-content-center fs-xs badge bg-light border text-dark">' . e($mode ? ucfirst($mode) : '-') . '</span>';
               })

               /*
               |--------------------------------------------------------------------------
               | Status
               |--------------------------------------------------------------------------
               */
               ->addColumn('status', function ($row) {
                    $meeting = $row->meeting;
                    $status = $meeting?->status ?? MeetingRequest::STATUS_ACCEPTED;
                    $badge = match ($status) {
                         Meeting::STATUS_PENDING => 'bg-warning text-dark',
                         Meeting::STATUS_ACCEPTED => 'bg-success',
                         Meeting::STATUS_REJECTED => 'bg-danger',
                         Meeting::STATUS_CANCELLED => 'bg-secondary',
                         Meeting::STATUS_COMPLETED => 'bg-primary',
                         Meeting::STATUS_NO_SHOW => 'bg-dark',
                         default => 'bg-light text-dark',
                    };

                    return '<span class="d-flex justify-content-center fs-xs badge ' . $badge . '">' . e(str_replace('_',' ',ucfirst($status))) . '</span>';
               })

               /*
               |--------------------------------------------------------------------------
               | Actions
               |--------------------------------------------------------------------------
               */               
               ->addColumn('actions', function (MeetingRequest $meetingRequest) use ($event) {
                    $buttons = '';

                    /*
                    |--------------------------------------------------------------------------
                    | Meeting
                    |--------------------------------------------------------------------------
                    */
                    $meeting = $meetingRequest->meeting;

                    /*
                    |--------------------------------------------------------------------------
                    | Pending Meeting Request
                    |--------------------------------------------------------------------------
                    */
                    if ($meetingRequest->status === MeetingRequest::STATUS_PENDING && ! $meeting) {
                         /*
                         |--------------------------------------------------------------------------
                         | Accept
                         |--------------------------------------------------------------------------
                         */
                         $buttons .= action_button('acceptMeeting', [
                              'btn-type' => 'btn-icon',
                              'title' => 'Accept Meeting Request',
                              'icon' => 'ph-check',
                              'color' => 'light text-success',

                              'url' => route(
                                   'events.meeting-requests.accept',
                                   [
                                        'event' => $event,
                                        'meetingRequest' => $meetingRequest,
                                   ]
                              ),

                              'method' => 'POST',
                              'table' => 'meetingsTable',
                         ]);

                         /*
                         |--------------------------------------------------------------------------
                         | Reject
                         |--------------------------------------------------------------------------
                         */
                         $buttons .= action_button('rejectMeeting', [
                              'btn-type' => 'btn-icon',
                              'title' => 'Reject Meeting Request',
                              'icon' => 'ph-x',
                              'color' => 'light text-danger',

                              'url' => route(
                                   'events.meeting-requests.reject',
                                   [
                                        'event' => $event,
                                        'meetingRequest' => $meetingRequest,
                                   ]
                              ),

                              'method' => 'POST',
                              'table' => 'meetingsTable',
                         ]);

                         /*
                         |--------------------------------------------------------------------------
                         | Cancel Request
                         |--------------------------------------------------------------------------
                         */
                         $buttons .= action_button('cancelMeeting', [
                              'btn-type' => 'btn-icon',
                              'title' => 'Cancel Meeting Request',
                              'icon' => 'ph-prohibit',
                              'color' => 'light text-warning',
                              'url' => route(
                                   'events.meeting-requests.cancel',
                                   [
                                        'event' => $event,
                                        'meetingRequest' => $meetingRequest,
                                   ]
                              ),

                              'method' => 'POST',
                              'table' => 'meetingsTable',
                         ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Accepted Meeting
                    |--------------------------------------------------------------------------
                    */
                    if ($meeting && $meeting->status === Meeting::STATUS_ACCEPTED) {
                         /*
                         |--------------------------------------------------------------------------
                         | Cancel Meeting
                         |--------------------------------------------------------------------------
                         */
                         $buttons .= action_button('cancelMeeting', [
                              'btn-type' => 'btn-icon',
                              'title' => 'Cancel Meeting',
                              'icon' => 'ph-prohibit',
                              'color' => 'light text-warning',

                              'url' => route(
                                   'events.meetings.cancel',
                                   [
                                        'event' => $event,
                                        'meeting' => $meeting,
                                   ]
                              ),

                              'method' => 'POST',
                              'table' => 'meetingsTable',
                         ]);

                         /*
                         |--------------------------------------------------------------------------
                         | QR Check-In
                         |--------------------------------------------------------------------------
                         |
                         | Add after V3 Check-In routes and controller methods are implemented.
                         |--------------------------------------------------------------------------
                         */


                         /*
                         |--------------------------------------------------------------------------
                         | Manual Check-In
                         |--------------------------------------------------------------------------
                         |
                         | Add after V3 Check-In routes and controller methods are implemented.
                         |--------------------------------------------------------------------------
                         */


                         /*
                         |--------------------------------------------------------------------------
                         | Complete
                         |--------------------------------------------------------------------------
                         */
                         $buttons .= action_button('completeMeeting', [
                              'btn-type' => 'btn-icon',
                              'title' => 'Complete Meeting',
                              'icon' => 'ph-check-circle',
                              'color' => 'light text-primary',

                              'url' => route(
                                   'events.meetings.complete',
                                   [
                                        'event' => $event,
                                        'meeting' => $meeting,
                                   ]
                              ),

                              'method' => 'POST',
                              'table' => 'meetingsTable',
                         ]);

                         /*
                         |--------------------------------------------------------------------------
                         | No Show
                         |--------------------------------------------------------------------------
                         */
                         $buttons .= action_button('noShowMeeting', [
                              'btn-type' => 'btn-icon',
                              'title' => 'No Show',
                              'icon' => 'ph-user-minus',
                              'color' => 'light text-danger',

                              'url' => route(
                                   'events.meetings.no-show',
                                   [
                                        'event' => $event,
                                        'meeting' => $meeting,
                                   ]
                              ),

                              'method' => 'POST',
                              'table' => 'meetingsTable',
                         ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | View Meeting
                    |--------------------------------------------------------------------------
                    */
                    if ($meeting) {
                         $buttons .= action_button('view', [
                              'btn-type' => 'btn-icon',
                              'href' => route(
                                   'my-events.meetings.show',
                                   [
                                        'event' => $event,
                                        'meeting' => $meeting,
                                   ]
                              ),
                              'title' => 'View Meeting',
                              'icon' => 'ph-eye',
                              'color' => 'light',
                         ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Actions
                    |--------------------------------------------------------------------------
                    */
                    return $buttons ? '<div class="d-flex gap-1">'.$buttons.'</div>' : '-';
               })

               /*
               |--------------------------------------------------------------------------
               | Raw Columns
               |--------------------------------------------------------------------------
               */
               ->rawColumns(['direction','meeting_date','participant','organization','check_in','meeting_mode','status','actions',])
               ->make(true);
     }

     /*
     |--------------------------------------------------------------------------
     | Participant Profile
     |--------------------------------------------------------------------------
     */
     public function profile(Request $request,Event $event,EventParticipant $participant,): View {
          /*
          |--------------------------------------------------------------------------
          | Workspace
          |--------------------------------------------------------------------------
          */
          $workspace = $this->workspace($event);
          $currentParticipant = $workspace['participant'];

          /*
          |--------------------------------------------------------------------------
          | Resolve Participant By ULID
          |--------------------------------------------------------------------------
          */
          $participant = EventParticipant::query()
               ->where('ulid', $participant->ulid)
               ->firstOrFail();

          /*
          |--------------------------------------------------------------------------
          | Prevent Viewing Yourself
          |--------------------------------------------------------------------------
          */
          abort_unless((int) $participant->id !== (int) $currentParticipant->id,
               404
          );

          /*
          |--------------------------------------------------------------------------
          | Ensure Participant Belongs To Event
          |--------------------------------------------------------------------------
          */
          abort_unless(
               $participant
                    ->eventOrganization()
                    ->where(
                         'event_id',
                         $event->id
                    )
                    ->exists(),
               404
          );

          /*
          |--------------------------------------------------------------------------
          | Selected Time Slot
          |--------------------------------------------------------------------------
          */
          $eventTimeSlotId = $request->integer(
               'event_time_slot_id'
          );

          /*
          |--------------------------------------------------------------------------
          | Participant Details
          |--------------------------------------------------------------------------
          */
          $participant->load([
               'organizationUser.user',
               'eventOrganization.organization',
               'eventOrganization.participantType',
          ]);

          /*
          |--------------------------------------------------------------------------
          | Drawer
          |--------------------------------------------------------------------------
          */
          return view(
               'my_events.meetings.partials.profile',
               [
                    'event'       => $event,
                    'participant' => $participant,
                    'workspace'   => $workspace,
                    'currentParticipant' => $currentParticipant,
                    'eventTimeSlotId' => $eventTimeSlotId,
               ]
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Show
     |--------------------------------------------------------------------------
     */
     public function show(Event $event,Meeting $meeting): View {
          /*
          |--------------------------------------------------------------------------
          | Event Scope
          |--------------------------------------------------------------------------
          */
          $this->ensureMeetingBelongsToEvent(
               event: $event,
               meeting: $meeting
          );

          /*
          |--------------------------------------------------------------------------
          | Relationships
          |--------------------------------------------------------------------------
          */
          $meeting->load([
               'meetingRequest',
               'timeSlot.schedule',

               'senderParticipant.organizationUser.user',
               'senderParticipant.eventOrganization.organization',
               'senderParticipant.eventOrganization.participantType',

               'receiverParticipant.organizationUser.user',
               'receiverParticipant.eventOrganization.organization',
               'receiverParticipant.eventOrganization.participantType',
          ]);

          /*
          |--------------------------------------------------------------------------
          | View
          |--------------------------------------------------------------------------
          */
          return view(
               'my_events.meetings.partials.show',
               array_merge(
                    $this->workspace($event),
                    [
                         'meeting' => $meeting,
                    ]
               )
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Ensure Meeting Belongs To Event
     |--------------------------------------------------------------------------
     */
     protected function ensureMeetingBelongsToEvent(Event $event,Meeting $meeting): void {
          abort_unless(
               $meeting->timeSlot()
                    ->whereHas(
                         'schedule',
                         fn ($query) =>
                         $query->where(
                              'event_id',
                              $event->id
                         )
                    )
                    ->exists(),
               404
          );
     }
}