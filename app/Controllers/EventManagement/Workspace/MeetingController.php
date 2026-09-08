<?php

namespace App\Http\Controllers\EventManagement\Workspace;

use App\Models\Event;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\MeetingActivity;
use App\Models\EventTimeSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;
use App\Services\Networking\MeetingRequestService;
use App\Services\Networking\MeetingQueryService;
use App\Services\Networking\MeetingLifecycleService;
use App\Services\Networking\MeetingOpportunityService;

class MeetingController extends WorkspaceController {
     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     */
     public function index(Event $event): View {
          return view(
               'event_management.workspace.meetings.index',
               $this->workspace($event)
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Datatable
     |--------------------------------------------------------------------------
     */
     public function datatable(Request $request,Event $event,MeetingQueryService $meetingQueryService): JsonResponse {
          /*
          |--------------------------------------------------------------------------
          | Permission
          |--------------------------------------------------------------------------
          */
          abort_unless(
               auth()->user()->canPermission('meetings.view'),
               403
          );

          /*
          |--------------------------------------------------------------------------
          | Filters
          |--------------------------------------------------------------------------
          */
          $filters = [
               'status' => $request->input('status'),
               'schedule' => $request->input('schedule'),
               'event_time_slot_id' => $request->input('event_time_slot_id'),
               'participant_id' => $request->input('participant_id'),
               'organization_id' => $request->input('organization_id'),
               'meeting_mode' => $request->input('meeting_mode'),
               'source' => $request->input('source'),
               'is_recommended' => $request->input('is_recommended'),
          ];

          /*
          |--------------------------------------------------------------------------
          | Meeting Lifecycle
          |--------------------------------------------------------------------------
          |
          | Query source is MeetingRequest.
          |
          | Pending Request:
          |     MeetingRequest exists
          |     Meeting does not exist yet
          |
          | Accepted / Active Meeting:
          |     MeetingRequest exists
          |     Meeting exists
          |--------------------------------------------------------------------------
          */
          $meetingRequests = $meetingQueryService->query(
               event: $event,
               filters: $filters
          );

          /*
          |--------------------------------------------------------------------------
          | Datatable
          |--------------------------------------------------------------------------
          */
          return DataTables::eloquent($meetingRequests)
               ->addIndexColumn()

               /*
               |--------------------------------------------------------------------------
               | Slot
               |--------------------------------------------------------------------------
               */
               ->addColumn('slot', function (MeetingRequest $meetingRequest) {
                    $slot = $meetingRequest->timeSlot;

                    if (! $slot) {
                         return '-';
                    }

                    return '
                         <div class="fw-semibold">'.$slot->start_at->format('d M Y').'</div>
                         <div class="text-muted fs-xs">
                              '.$slot->start_at->format('h:i A').'
                              -
                              '.$slot->end_at->format('h:i A').'
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Sender
               |--------------------------------------------------------------------------
               */
               ->addColumn('sender', function (MeetingRequest $meetingRequest) {
                    $participant = $meetingRequest->senderParticipant;

                    if (! $participant) {
                         return '-';
                    }

                    $name = $participant->display_name ?: '-';
                    $designation = $participant->organizationUser?->designation?? '-';
                    $organization = $participant->eventOrganization?->organization?->name?? '-';
                    $avatar = $participant->avatar_url ?: asset('images/defaults/avatar.png');

                    return '
                         <div class="d-flex align-items-center">
                              <img src="'.e($avatar).'"class="rounded-circle me-2" width="40" height="40" style="object-fit:cover;">
                              <div>
                                   <div class="fw-semibold">'.e($name).'</div>
                                   <div class="text-muted fs-xs">'.e($designation).'</div>
                                   <div class="text-muted fs-xs">'.e($organization).'</div>
                              </div>
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Receiver
               |--------------------------------------------------------------------------
               */
               ->addColumn('receiver', function (MeetingRequest $meetingRequest) {
                    $participant = $meetingRequest->receiverParticipant;

                    if (! $participant) {
                         return '-';
                    }

                    $name = $participant->display_name ?: '-';
                    $designation = $participant->organizationUser?->designation?? '-';
                    $organization = $participant->eventOrganization?->organization?->name?? '-';
                    $avatar = $participant->avatar_url ?: asset('images/defaults/avatar.png');

                    return '
                         <div class="d-flex align-items-center">
                              <img src="'.e($avatar).'" class="rounded-circle me-2" width="40" height="40" style="object-fit:cover;">
                              <div>
                                   <div class="fw-semibold">'.e($name).'</div>
                                   <div class="text-muted fs-xs">'.e($designation).'</div>
                                   <div class="text-muted fs-xs">'.e($organization).'</div>
                              </div>
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Status
               |--------------------------------------------------------------------------
               */
               ->addColumn('meeting_status', function (MeetingRequest $meetingRequest) {
                    /*
                    |--------------------------------------------------------------------------
                    | Effective Lifecycle Status
                    |--------------------------------------------------------------------------
                    |
                    | Before acceptance:
                    |     MeetingRequest status
                    |
                    | After Meeting creation:
                    |     Meeting status
                    |--------------------------------------------------------------------------
                    */
                    $status = $meetingRequest->meeting?->status ?? $meetingRequest->status;
                    $badge = match ($status) {
                         'pending' => 'bg-warning',
                         'accepted' => 'bg-primary',
                         'completed' => 'bg-success',
                         'cancelled' => 'bg-secondary',
                         'rejected' => 'bg-danger',
                         'expired' => 'bg-dark',
                         'no_show' => 'bg-danger',
                         default => 'bg-light text-body',
                    };

                    return '
                         <span class="badge '.$badge.'">'.e(ucwords(str_replace('_',' ',$status))).'</span>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Mode
               |--------------------------------------------------------------------------
               */
               ->addColumn('mode', function (MeetingRequest $meetingRequest) {
                    /*
                    |--------------------------------------------------------------------------
                    | Prefer Actual Meeting Mode
                    |--------------------------------------------------------------------------
                    */
                    $meetingMode = $meetingRequest->meeting?->meeting_mode;

                    /*
                    |--------------------------------------------------------------------------
                    | Fallback To Requested Meeting Mode
                    |--------------------------------------------------------------------------
                    |
                    | Keep this fallback only if meeting_requests has meeting_mode.
                    |--------------------------------------------------------------------------
                    */
                    $meetingMode ??= $meetingRequest->meeting_mode ?? null;

                    if (! $meetingMode) {
                         return '-';
                    }

                    $icon = match ($meetingMode) {
                         'physical' => 'ph-map-pin',
                         'virtual' => 'ph-video-camera',
                         'hybrid' => 'ph-arrows-left-right',
                         default => 'ph-handshake',
                    };

                    return '
                         <span class="d-flex justify-content-center fs-xs badge bg-light text-body border">
                              <i class="'.$icon.' fs-xs me-1"></i>
                              '.e(ucfirst($meetingMode)).'
                         </span>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Check-In
               |--------------------------------------------------------------------------
               */
               ->addColumn('check_in', function (MeetingRequest $meetingRequest) {
                    /*
                    |--------------------------------------------------------------------------
                    | Pending Request
                    |--------------------------------------------------------------------------
                    */
                    if (! $meetingRequest->meeting) {
                         return '<span class="text-muted"><i class="ph-minus"></i></span>';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Temporary Until V3 Check-In Is Implemented
                    |--------------------------------------------------------------------------
                    */
                    return '<span class="text-muted"><i class="ph-minus"></i></span>';
               })

               /*
               |--------------------------------------------------------------------------
               | Created
               |--------------------------------------------------------------------------
               */
               ->addColumn('created', function (MeetingRequest $meetingRequest) {
                    if (! $meetingRequest->created_at) {
                         return '-';
                    }

                    return '
                         <div class="fw-semibold fs-sm">'.$meetingRequest->created_at->format('d M Y').'</div>
                         <div class="text-muted fs-xs">'.$meetingRequest->created_at->format('h:i A').'</div>
                    ';
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
                                        $event,
                                        $meetingRequest,
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
                                        $event,
                                        $meetingRequest,
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
                                        $event,
                                        $meetingRequest,
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
                                        $event,
                                        $meeting,
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
                                        $event,
                                        $meeting,
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
                                        $event,
                                        $meeting,
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
                                   'events.meetings.show',
                                   [
                                        $event,
                                        $meeting,
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
               ->rawColumns(['slot','sender','receiver','meeting_status','mode','check_in','created','actions',])
               ->make(true);
     }

     /*
     |--------------------------------------------------------------------------
     | Opportunities Datatable
     |--------------------------------------------------------------------------
     */
     public function opportunitiesDatatable(Request $request,Event $event,MeetingOpportunityService $meetingOpportunityService): JsonResponse {
          /*
          |--------------------------------------------------------------------------
          | Permission
          |--------------------------------------------------------------------------
          */
          abort_unless(
               auth()->user()->canPermission('meetings.view'),
               403
          );

          /*
          |--------------------------------------------------------------------------
          | Filters
          |--------------------------------------------------------------------------
          */
          $filters = [
               'status' => $request->input('status'),
               'event_time_slot_id' => $request->input('event_time_slot_id'),
               'sender_participant_id' => $request->input('sender_participant_id'),
               'receiver_participant_id' => $request->input('receiver_participant_id'),
               'organization_id' => $request->input('organization_id'),
               'event_participant_type_id' => $request->input('event_participant_type_id'),
          ];

          /*
          |--------------------------------------------------------------------------
          | Meeting Opportunities
          |--------------------------------------------------------------------------
          */
          $opportunities = $meetingOpportunityService->forEvent(
               event: $event,
               filters: $filters
          );

          /*
          |--------------------------------------------------------------------------
          | Datatable
          |--------------------------------------------------------------------------
          */
          return DataTables::of($opportunities)
               ->addIndexColumn()
               /*
               |--------------------------------------------------------------------------
               | Slot
               |--------------------------------------------------------------------------
               */
               ->addColumn('slot', function ($row) {
                    return '
                         <div class="fw-semibold">'.e(\Carbon\Carbon::parse($row->start_at)->format('d M Y')).'</div>
                         <div class="text-muted fs-xs">
                              '.e(\Carbon\Carbon::parse($row->start_at)->format('h:i A'))
                              .'-
                              '.e(\Carbon\Carbon::parse($row->end_at)->format('h:i A')).'
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Sender
               |--------------------------------------------------------------------------
               */
               ->addColumn('sender', function ($row) {
                    $avatar = $row->sender_photo ? asset('storage/'.$row->sender_photo) : asset('images/defaults/avatar.png');

                    return '
                         <div class="d-flex align-items-center">
                              <img src="'.e($avatar).'" class="rounded-circle me-2" width="40" height="40" style="object-fit:cover;">
                              <div>
                                   <div class="fw-semibold">'.e($row->sender_name).'</div>
                                   <div class="text-muted fs-xs">'.e($row->sender_designation ?? '-').'</div>
                                   <div class="text-muted fs-xs">'.e($row->sender_organization ?? '-').'</div>
                              </div>
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Receiver
               |--------------------------------------------------------------------------
               */
               ->addColumn('receiver', function ($row) {
                    $avatar = $row->receiver_photo ? asset('storage/'.$row->receiver_photo) : asset('images/defaults/avatar.png');

                    return '
                         <div class="d-flex align-items-center">
                              <img src="'.e($avatar).'" class="rounded-circle me-2" width="40" height="40" style="object-fit:cover;">
                              <div>
                                   <div class="fw-semibold">'.e($row->receiver_name).'</div>
                                   <div class="text-muted fs-xs">'.e($row->receiver_designation ?? '-').'</div>
                                   <div class="text-muted fs-xs">'.e($row->receiver_organization ?? '-').'</div>
                              </div>
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Availability
               |--------------------------------------------------------------------------
               */
               ->addColumn('availability', function ($row) {
                    $senderBadge = match ($row->sender_availability) {
                         'preferred' => 'bg-primary',
                         'available' => 'bg-success',
                         default => 'bg-secondary',
                    };

                    $receiverBadge = match ($row->receiver_availability) {
                         'preferred' => 'bg-primary',
                         'available' => 'bg-success',
                         default => 'bg-secondary',
                    };

                    return '
                         <div class="d-flex flex-column gap-1">
                              <div class="d-flex justify-content-between">
                                   <span class="text-muted fs-sm me-1">Sender:</span>
                                   <span class="badge fs-xs '.$senderBadge.'">'.e(ucfirst($row->sender_availability)).'</span>
                              </div>

                              <div class="d-flex justify-content-between">
                                   <span class="text-muted fs-sm me-1">Receiver:</span>
                                   <span class="badge fs-xs '.$receiverBadge.'">'.e(ucfirst($row->receiver_availability)).'</span>
                              </div>
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Actions
               |--------------------------------------------------------------------------
               */
               ->addColumn('actions', function ($row) use ($event) {
                    if (! auth()->user()->canPermission('meetings.create')) {
                         return '-';
                    }

                    return action_button('add', [
                         'modal-size' => 'modal-md',
                         'btn-type' => 'btn-icon',
                         'title' => 'Request Meeting',
                         'label' => '',
                         'icon' => 'ph-handshake',
                         'color' => 'primary',
                         'button-text' => 'Send Request',

                         'createUrl' => route('events.meetings.create',[
                                   $event,
                                   'sender_participant_id' => $row->sender_participant_id,
                                   'receiver_participant_id' => $row->receiver_participant_id,
                                   'event_time_slot_id' => $row->event_time_slot_id,
                              ]
                         ),
                         'storeUrl' => route('events.meetings.store',$event),
                         'table' => 'meetingOpportunitiesTable',
                    ]);
               })

               /*
               |--------------------------------------------------------------------------
               | Raw Columns
               |--------------------------------------------------------------------------
               */
               ->rawColumns(['slot','sender','receiver','availability','actions',])
               ->make(true);
     }

     /*
     |--------------------------------------------------------------------------
     | Create Meeting Request
     |--------------------------------------------------------------------------
     */
     public function create(Request $request,Event $event,MeetingOpportunityService $meetingOpportunityService): View {
          /*
          |--------------------------------------------------------------------------
          | Validate Request
          |--------------------------------------------------------------------------
          */
          $validated = $request->validate([
               'sender_participant_id' => [
                    'required',
                    'integer',
                    'exists:event_participants,id',
               ],

               'receiver_participant_id' => [
                    'required',
                    'integer',
                    'different:sender_participant_id',
                    'exists:event_participants,id',
               ],

               'event_time_slot_id' => [
                    'required',
                    'integer',
                    'exists:event_time_slots,id',
               ],
          ]);

          /*
          |--------------------------------------------------------------------------
          | Find Current Meeting Opportunity
          |--------------------------------------------------------------------------
          |
          | Never trust the participant pair or time slot coming from the browser.
          | The opportunity must still exist in the current opportunity pipeline.
          |--------------------------------------------------------------------------
          */
          $opportunity = $meetingOpportunityService
               ->forEvent(
                    event: $event
               )
               ->first(function ($opportunity) use ($validated) {
                    return
                         (int) $opportunity->sender_participant_id === (int) $validated['sender_participant_id']
                         &&
                         (int) $opportunity->receiver_participant_id === (int) $validated['receiver_participant_id']
                         &&
                         (int) $opportunity->event_time_slot_id === (int) $validated['event_time_slot_id'];
               });

          /*
          |--------------------------------------------------------------------------
          | Opportunity Still Available
          |--------------------------------------------------------------------------
          */
          abort_unless(
               $opportunity,
               404,
               'This meeting opportunity is no longer available.'
          );

          /*
          |--------------------------------------------------------------------------
          | Event Time Slot
          |--------------------------------------------------------------------------
          */
          $slot = EventTimeSlot::query()
               ->whereHas(
                    'schedule',
                    function ($query) use ($event) {
                         $query->where(
                              'event_id',
                              $event->id
                         );
                    }
               )
               ->findOrFail(
                    $validated['event_time_slot_id']
               );

          /*
          |--------------------------------------------------------------------------
          | Modal
          |--------------------------------------------------------------------------
          */
          return view(
               'event_management.workspace.meetings.create',
               [
                    'event' => $event,
                    'senderParticipantId' => $validated['sender_participant_id'],
                    'receiverParticipantId' => $validated['receiver_participant_id'],
                    'eventTimeSlotId' => $validated['event_time_slot_id'],
                    'opportunity' => $opportunity,
                    'slot' => $slot,
               ]
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Store Meeting Request
     |--------------------------------------------------------------------------
     */
     public function store(Request $request,Event $event,MeetingOpportunityService $meetingOpportunityService,MeetingRequestService $meetingRequestService): JsonResponse {
          /*
          |--------------------------------------------------------------------------
          | Validate Request
          |--------------------------------------------------------------------------
          */
          $validated = $request->validate([
               'sender_participant_id' => [
                    'required',
                    'integer',
                    'exists:event_participants,id',
               ],

               'receiver_participant_id' => [
                    'required',
                    'integer',
                    'different:sender_participant_id',
                    'exists:event_participants,id',
               ],

               'event_time_slot_id' => [
                    'required',
                    'integer',
                    'exists:event_time_slots,id',
               ],

               'meeting_mode' => [
                    'required',
                    'string',
                    'in:physical,virtual,hybrid',
               ],

               'message' => [
                    'nullable',
                    'string',
                    'max:1000',
               ],
          ]);

          /*
          |--------------------------------------------------------------------------
          | Find Current Meeting Opportunity
          |--------------------------------------------------------------------------
          |
          | Revalidate the opportunity at store time because availability,
          | networking configuration, matching rules, or meeting conflicts may
          | have changed after the modal was opened.
          |--------------------------------------------------------------------------
          */
          $opportunity = $meetingOpportunityService
               ->forEvent(
                    event: $event
               )
               ->first(
                    function ($opportunity) use ($validated) {
                         return
                              (int) $opportunity->sender_participant_id === (int) $validated['sender_participant_id']
                              &&
                              (int) $opportunity->receiver_participant_id === (int) $validated['receiver_participant_id']
                              &&
                              (int) $opportunity->event_time_slot_id === (int) $validated['event_time_slot_id'];
                    }
               );

          /*
          |--------------------------------------------------------------------------
          | Opportunity Still Available
          |--------------------------------------------------------------------------
          */
          abort_unless(
               $opportunity,
               422,
               'This meeting opportunity is no longer available.'
          );

          /*
          |--------------------------------------------------------------------------
          | Create Direct Meeting Request
          |--------------------------------------------------------------------------
          */
          $meetingRequest = $meetingRequestService->create(
               event: $event,
               senderParticipantId: $validated['sender_participant_id'],
               receiverParticipantId: $validated['receiver_participant_id'],
               eventTimeSlotId: $validated['event_time_slot_id'],
               meetingMode: $validated['meeting_mode'],
               message: $validated['message'] ?? null,
               source: MeetingRequest::SOURCE_DIRECT
          );

          /*
          |--------------------------------------------------------------------------
          | Response
          |--------------------------------------------------------------------------
          */
          return response()->json([
               'success' => true,
               'message' => 'Meeting request sent successfully.',
               'data' => [
                    'ulid' => $meetingRequest->ulid,
               ],
          ]);
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
               'event_management.workspace.meetings.show',
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
     | Accept Meeting Request
     |--------------------------------------------------------------------------
     */
     public function accept(Event $event,MeetingRequest $meetingRequest,MeetingLifecycleService $meetingLifecycleService): JsonResponse {
          /*
          |--------------------------------------------------------------------------
          | Event Scope
          |--------------------------------------------------------------------------
          */
          $this->ensureMeetingRequestBelongsToEvent(
               event: $event,
               meetingRequest: $meetingRequest
          );

          /*
          |--------------------------------------------------------------------------
          | Accept
          |--------------------------------------------------------------------------
          */
          $meeting = $meetingLifecycleService->accept(
               meetingRequest: $meetingRequest
          );

          return response()->json([
               'success' => true,
               'message' => 'Meeting request accepted successfully.',
               'data' => [
                    'ulid' => $meeting->ulid,
               ],
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Reject Meeting Request
     |--------------------------------------------------------------------------
     */
     public function reject(Event $event,MeetingRequest $meetingRequest,MeetingLifecycleService $meetingLifecycleService): JsonResponse {
          /*
          |--------------------------------------------------------------------------
          | Event Scope
          |--------------------------------------------------------------------------
          */
          $this->ensureMeetingRequestBelongsToEvent(
               event: $event,
               meetingRequest: $meetingRequest
          );

          /*
          |--------------------------------------------------------------------------
          | Reject
          |--------------------------------------------------------------------------
          */
          $meetingLifecycleService->reject(
               meetingRequest: $meetingRequest
          );

          return response()->json([
               'success' => true,
               'message' => 'Meeting request rejected successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Cancel Meeting Request
     |--------------------------------------------------------------------------
     */
     public function cancelRequest(Event $event,MeetingRequest $meetingRequest,MeetingLifecycleService $meetingLifecycleService): JsonResponse {
          /*
          |--------------------------------------------------------------------------
          | Event Scope
          |--------------------------------------------------------------------------
          */
          $this->ensureMeetingRequestBelongsToEvent(
               event: $event,
               meetingRequest: $meetingRequest
          );

          /*
          |--------------------------------------------------------------------------
          | Cancel Request
          |--------------------------------------------------------------------------
          */
          $meetingLifecycleService->cancelRequest(
               meetingRequest: $meetingRequest
          );

          return response()->json([
               'success' => true,
               'message' => 'Meeting request cancelled successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Cancel Meeting
     |--------------------------------------------------------------------------
     */
     public function cancel(Event $event,Meeting $meeting,MeetingLifecycleService $meetingLifecycleService): JsonResponse {
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
          | Cancel
          |--------------------------------------------------------------------------
          */
          $meetingLifecycleService->cancel(
               meeting: $meeting
          );

          return response()->json([
               'success' => true,
               'message' => 'Meeting cancelled successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Complete Meeting
     |--------------------------------------------------------------------------
     */
     public function complete(Event $event,Meeting $meeting,MeetingLifecycleService $meetingLifecycleService): JsonResponse {
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
          | Complete
          |--------------------------------------------------------------------------
          */
          $meetingLifecycleService->complete(
               meeting: $meeting
          );

          return response()->json([
               'success' => true,
               'message' => 'Meeting completed successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | No Show
     |--------------------------------------------------------------------------
     */
     public function noShow(Event $event,Meeting $meeting,MeetingLifecycleService $meetingLifecycleService): JsonResponse {
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
          | No Show
          |--------------------------------------------------------------------------
          */
          $meetingLifecycleService->noShow(
               meeting: $meeting
          );

          return response()->json([
               'success' => true,
               'message' => 'Meeting marked as no-show successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Ensure Meeting Belongs To Event
     |--------------------------------------------------------------------------
     */
     protected function ensureMeetingBelongsToEvent(Event $event,Meeting $meeting): void {
          $belongsToEvent = $meeting
               ->timeSlot()
               ->whereHas(
                    'schedule',
                    function ($query) use ($event) {
                         $query->where(
                              'event_id',
                              $event->id
                         );
                    }
               )
               ->exists();

          abort_unless(
               $belongsToEvent,
               404
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Ensure Meeting Request Belongs To Event
     |--------------------------------------------------------------------------
     */
     protected function ensureMeetingRequestBelongsToEvent(Event $event,MeetingRequest $meetingRequest): void {
          $belongsToEvent = $meetingRequest
               ->timeSlot()
               ->whereHas(
                    'schedule',
                    function ($query) use ($event) {
                         $query->where(
                              'event_id',
                              $event->id
                         );
                    }
               )
               ->exists();

          abort_unless(
               $belongsToEvent,
               404
          );
     }
}