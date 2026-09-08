<?php

namespace App\Http\Controllers\MyEvents;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\EventTimeSlot;

use App\Services\Networking\MeetingLifecycleService;
use App\Services\Networking\MeetingOpportunityService;
use App\Services\Networking\MeetingRequestService;

class MeetingRequestController extends WorkspaceController {
     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     */
     public function index(Event $event,MeetingRequestService $meetingRequestService,MeetingOpportunityService $meetingOpportunityService,): View {
          /*
          |--------------------------------------------------------------------------
          | Workspace
          |--------------------------------------------------------------------------
          */
          $workspace = $this->workspace($event);
          $participant = $workspace['participant'];

          /*
          |--------------------------------------------------------------------------
          | Meeting Request Statistics
          |--------------------------------------------------------------------------
          */
          $workspace['meetingRequestStatistics'] = $meetingRequestService->meetingRequestStatistics($participant);

          /*
          |--------------------------------------------------------------------------
          | Meeting Opportunities
          |--------------------------------------------------------------------------
          |
          | Universal direct meeting opportunities.
          |
          | This provides the participants and available slots from which
          | the logged-in participant can create a meeting request.
          |
          */
          $workspace['meetingOpportunities'] = $meetingOpportunityService->forParticipant(event: $event,participant: $participant);

          /*
          |--------------------------------------------------------------------------
          | View
          |--------------------------------------------------------------------------
          */
          return view('my_events.meeting_requests.index',$workspace);
     }

     /*
     |--------------------------------------------------------------------------
     | Opportunities Datatable
     |--------------------------------------------------------------------------
     */
     public function datatable(Request $request,Event $event,MeetingOpportunityService $meetingOpportunityService): JsonResponse {
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
          | Filters
          |--------------------------------------------------------------------------
          */
          $filters = [
               'participant_type' => request('participant_type'),
               'organization_id' => request('organization_id'),
               'availability' => request('availability'),
               'compatibility' => request('compatibility'),
          ];

          /*
          |--------------------------------------------------------------------------
          | Representative Meeting Opportunities
          |--------------------------------------------------------------------------
          |
          | IMPORTANT:
          |
          | The current logged-in participant is ALWAYS the sender.
          |
          |--------------------------------------------------------------------------
          */
          $opportunities = $meetingOpportunityService->forParticipantAsSender(
               event: $event,
               participant: $participant,
               filters: $filters
          );

          $customFilters = [
               /*
               |--------------------------------------------------------------------------
               | Participant Type
               |--------------------------------------------------------------------------
               */
               'participantType' => '
                    <select id="participantType" class="form-select form-select-sm">
                         <option value="">All Participant Types</option>' .
                         $opportunities
                              ->pluck('receiver_event_participant_type_id')
                              ->merge(
                                   $opportunities->pluck('sender_event_participant_type_id')
                              )
                              ->unique()
                              ->sort()
                              ->map(function ($id) use ($event) {
                                   $type = $event->participantTypes->firstWhere('id', $id);
                                   return $type
                                        ? '<option value="'.$type->id.'">'.e($type->name).'</option>'
                                        : '';
                              })
                              ->implode('')

                    . '</select>',

               /*
               |--------------------------------------------------------------------------
               | Organization
               |--------------------------------------------------------------------------
               */
               //'organization' => '
               //     <select id="organization_id" class="form-select form-select-sm">
               //          <option value="">All Organizations</option>' .
               //          $opportunities
               //               ->map(function ($row) use ($workspace) {
               //                    $isSender = $row->sender_participant_id == $workspace['participant']->id;
               //                    return [
               //                         'id' => $isSender
               //                              ? $row->receiver_organization_id
               //                              : $row->sender_organization_id,
               //
               //                         'name' => $isSender
               //                              ? $row->receiver_organization
               //                              : $row->sender_organization,
               //                    ];
               //
               //               })
               //               ->unique('id')
               //               ->sortBy('name')
               //               ->map(fn ($organization) => '
               //                    <option value="'.$organization['id'].'">
               //                         '.e($organization['name']).'
               //                    </option>
               //               ')
               //               ->implode('')
               //     . '</select>',

               /*
               |--------------------------------------------------------------------------
               | Availability
               |--------------------------------------------------------------------------
               */
               'availability' => '
                    <select id="availability" class="form-select form-select-sm">
                         <option value="">All Availability</option>
                         <option value="preferred">Preferred</option>
                         <option value="available">Available</option>
                    </select>
               ',

               /*
               |--------------------------------------------------------------------------
               | Compatibility
               |--------------------------------------------------------------------------
               */
               'compatibility' => '
                    <select id="compatibility" class="form-select form-select-sm">
                         <option value="">All Compatibility</option>
                         <option value="90">90% & Above</option>
                         <option value="80">80% & Above</option>
                         <option value="70">70% & Above</option>
                         <option value="60">60% & Above</option>
                         <option value="50">50% & Above</option>
                    </select>
               ',
          ];

          /*
          |--------------------------------------------------------------------------
          | Datatable
          |--------------------------------------------------------------------------
          */
          return DataTables::of($opportunities)
               ->addColumn('participant_id', function ($row) {
                    return $row->participant_id;
               })

               /*
               |--------------------------------------------------------------------------
               | Slot
               |--------------------------------------------------------------------------
               */
               ->addColumn('slot', function ($row) {
                    if (! $row->start_at) {
                         return '-';
                    }

                    $startAt = \Carbon\Carbon::parse($row->start_at);

                    return '
                         <div class="fw-semibold fs-sm">'.$startAt->format('d M Y').'</div>
                         <div class="text-muted fs-xs">'.$startAt->format('h:i A').'</div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Profile
               |--------------------------------------------------------------------------
               */
               ->addColumn('profile', function ($row) {
                    $avatar = $row->participant_photo
                         ? asset('storage/'.$row->participant_photo)
                         : asset('images/defaults/avatar.png');

                    return '
                         <div class="d-flex align-items-center">
                              <img
                                   src="'.$avatar.'"
                                   class="rounded-circle me-2"
                                   width="42"
                                   height="42"
                                   style="object-fit:cover;">

                              <div>
                                   <div class="fw-semibold">'.e($row->participant_name).'</div>
                                   <div class="text-muted fs-xs">'.e($row->participant_designation ?? '-').'</div>
                                   <div class="text-muted fs-xs">'.e($row->participant_organization ?? '-').'</div>
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
                    $availability = $row->participant_availability
                         ?? $row->receiver_availability
                         ?? null;

                    if (! $availability) {
                         return '<span class="badge fs-xs bg-secondary">-</span>';
                    }

                    $badge = match ($availability) {
                         'preferred' => 'bg-primary',
                         'available' => 'bg-success',
                         'unavailable' => 'bg-danger',
                         default => 'bg-secondary',
                    };

                    return '
                         <span class="badge fs-xs '.$badge.'">
                              '.e(ucfirst($availability)).'
                         </span>
                    ';
               })
               ->addColumn('compatibility', function ($row) {
                    $score = (int) (
                         $row->compatibility_score
                         ?? $row->match_priority_score
                         ?? 0
                    );

                    return '
                         <span class="badge fs-xs bg-info">
                              '.$score.'
                         </span>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Ranking
               |--------------------------------------------------------------------------
               */
               ->addColumn('ranking', function ($row) {
                    $ranking = (int) (
                         $row->ranking_score
                         ?? $row->match_priority_score
                         ?? 0
                    );

                    return '
                         <div class="d-flex justify-content-center align-items-center">
                              <span class="badge fs-sm bg-warning text-white">
                                   <i class="ph-star ph-sm me-1"></i>
                                   '.$ranking.'
                              </span>
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Recommendation
               |--------------------------------------------------------------------------
               */
               ->addColumn('recommendation', function ($row) {
                    $autoRecommend =
                         $row->auto_recommend
                         ?? $row->match_auto_recommend
                         ?? false;

                    if ($autoRecommend) {
                         return '
                              <span class="badge bg-success">
                                   Recommended
                              </span>
                         ';
                    }

                    return '
                         <span class="badge bg-secondary">
                              Compatible
                         </span>
                    ';
               })
               ->addColumn('event_time_slot_id', function ($row) {
                    return $row->event_time_slot_id;
               })
               ->addColumn('meeting_request_status', function ($row) {
                    $status = $row->meeting_request_status ?? null;

                    if (! $status) {
                         return '';
                    }

                    $badge = match ($status) {
                         'pending' => 'bg-warning text-dark',
                         'accepted' => 'bg-success',
                         'rejected' => 'bg-danger',
                         'cancelled' => 'bg-secondary',
                         default => 'bg-light text-dark',
                    };

                    return '
                         <span class="badge '.$badge.'">
                              '.e(ucfirst($status)).'
                         </span>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Raw Columns
               |--------------------------------------------------------------------------
               */
               ->rawColumns(['participant_id','profile','slot','availability','compatibility','ranking','recommendation','meeting_request_status','event_time_slot_id',])
               ->with('filters', $customFilters)
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
          abort_unless(
               (int) $participant->id !==
               (int) $currentParticipant->id,
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
               'my_events.meeting_requests.partials.participant',
               [
                    'event'       => $event,
                    'participant' => $participant,
                    'workspace'   => $workspace,
                    'eventTimeSlotId' => $eventTimeSlotId,
               ]
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Create Meeting Request
     |--------------------------------------------------------------------------
     */
     public function create(Request $request,Event $event,EventParticipant $participant,MeetingOpportunityService $meetingOpportunityService,): View {
          /*
          |--------------------------------------------------------------------------
          | Logged-in Participant
          |--------------------------------------------------------------------------
          */
          $sender = $this->workspaceService->participant($event);

          /*
          |--------------------------------------------------------------------------
          | Validate Request
          |--------------------------------------------------------------------------
          */
          $validated = $request->validate([
               'event_time_slot_id' => [
                    'required',
                    'integer',
                    'exists:event_time_slots,id',
               ],

          ]);


          /*
          |--------------------------------------------------------------------------
          | Cannot Request Yourself
          |--------------------------------------------------------------------------
          */
          abort_unless(
               $sender->id !== $participant->id,
               422
          );

          /*
          |--------------------------------------------------------------------------
          | Meeting Opportunities
          |--------------------------------------------------------------------------
          */
          $recommendation = $meetingOpportunityService
               ->forParticipantAsSender(
                    event: $event,
                    participant: $sender,
               )
               ->first(function ($recommendation) use (
                    $sender,
                    $participant,
                    $validated
               ) {
                    return
                         (int) $recommendation->participant_id === (int) $participant->id
                         &&
                         (int) $recommendation->event_time_slot_id === (int) $validated['event_time_slot_id'];
               });

          /*
          |--------------------------------------------------------------------------
          | Time Slot
          |--------------------------------------------------------------------------
          */
          $slot = EventTimeSlot::query()
               ->whereKey($validated['event_time_slot_id'])
               ->where('type', 'meeting')
               ->where('is_bookable', true)
               ->firstOrFail();

          /*
          |--------------------------------------------------------------------------
          | View
          |--------------------------------------------------------------------------
          */
          return view(
               'my_events.meeting_requests.partials.request_form',
               [
                    'event'                 => $event,
                    'senderParticipantId'   => $sender->id,
                    'receiverParticipantId' => $participant->id,
                    'participant'           => $participant,
                    'eventTimeSlotId'       => $validated['event_time_slot_id'],
                    'recommendation'        => $recommendation,
                    'slot'                  => $slot,
               ]
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Store
     |--------------------------------------------------------------------------
     */
     public function store(Request $request,Event $event,MeetingRequestService $meetingRequestService,): JsonResponse {
          /*
          |--------------------------------------------------------------------------
          | Logged-in Participant
          |--------------------------------------------------------------------------
          */
          $sender = $this->workspace($event)['participant'];

          /*
          |--------------------------------------------------------------------------
          | Validate
          |--------------------------------------------------------------------------
          */
          $validated = $request->validate([
               /*
               |--------------------------------------------------------------------------
               | Receiver
               |--------------------------------------------------------------------------
               */
               'receiver_participant_id' => [
                    'required',
                    'integer',
                    'different:' . $sender->id,
                    'exists:event_participants,id',
               ],

               /*
               |--------------------------------------------------------------------------
               | Meeting Slot
               |--------------------------------------------------------------------------
               */
               'event_time_slot_id' => [
                    'required',
                    'integer',
                    'exists:event_time_slots,id',
               ],

               /*
               |--------------------------------------------------------------------------
               | Meeting Mode
               |--------------------------------------------------------------------------
               */
               'meeting_mode' => [
                    'required',
                    'string',
                    'in:physical,virtual,hybrid',
               ],

               /*
               |--------------------------------------------------------------------------
               | Message
               |--------------------------------------------------------------------------
               */
               'message' => [
                    'nullable',
                    'string',
                    'max:1000',
               ],
          ]);

          /*
          |--------------------------------------------------------------------------
          | Receiver Participant
          |--------------------------------------------------------------------------
          |
          | Resolve it here so we can verify that the participant actually belongs
          | to the current event.
          |--------------------------------------------------------------------------
          */
          $receiver = EventParticipant::query()
               ->with('eventOrganization')
               ->findOrFail(
                    $validated['receiver_participant_id']
               );

          /*
          |--------------------------------------------------------------------------
          | Receiver Event Validation
          |--------------------------------------------------------------------------
          */
          abort_unless(
               $receiver->eventOrganization?->event_id === $event->id,
               404
          );

          /*
          |--------------------------------------------------------------------------
          | Create Meeting Request
          |--------------------------------------------------------------------------
          */
          $meetingRequest = $meetingRequestService->create(
               event: $event,
               senderParticipantId: $sender->id,
               receiverParticipantId: $validated['receiver_participant_id'],
               eventTimeSlotId: $validated['event_time_slot_id'],
               meetingMode: $validated['meeting_mode'],
               message: $validated['message'] ?? null,
               recommendationScore: $validated['recommendation_score'] ?? null,
               source: MeetingRequest::SOURCE_DIRECT,
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