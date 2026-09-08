<?php

namespace App\Http\Controllers\MyEvents;

use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

use Illuminate\View\View;
use Illuminate\Http\Request;
use App\Services\MyEvents\MatchmakingService;
use App\Services\MyEvents\MyEventWorkspaceService;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventTimeSlot;

use App\Models\MeetingRequest;
use App\Services\Networking\MeetingLifecycleService;
use App\Services\Networking\MeetingRequestService;
use App\Services\Networking\ParticipantRecommendationService;

class MatchmakingController extends WorkspaceController {
     public function __construct(
          MyEventWorkspaceService $workspaceService,
          protected MatchmakingService $matchmakingService,
     ) {
          parent::__construct($workspaceService);
     }

     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     */
     public function index(Event $event): View {
          $workspace = $this->workspace($event);
          return view(
               'my_events.matchmaking.index',
               array_merge(
                    $workspace,
                    $this->matchmakingService->workspace(
                         $workspace
                    )
               )
          );
     }

     /*
     |--------------------------------------------------------------------------
     | March Making DataTable
     |--------------------------------------------------------------------------
     */
     public function datatable(Event $event): JsonResponse {
          abort_unless(
               auth()->user()->canPermission('my-events.view'),
               403
          );

          $workspace = $this->workspace($event);
          $filters = [
               'participant_type' => request('participant_type'),
               'organization_id' => request('organization_id'),
               'availability' => request('availability'),
               'compatibility' => request('compatibility'),
          ];

          $recommendations = $this->matchmakingService
               ->recommendationsForDatatable(
                    workspace: $workspace,
                    filters: $filters,
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
                         $recommendations
                              ->pluck('receiver_event_participant_type_id')
                              ->merge(
                                   $recommendations->pluck('sender_event_participant_type_id')
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
               'organization' => '
                    <select id="organization_id" class="form-select form-select-sm">
                         <option value="">All Organizations</option>' .
                         $recommendations
                              ->map(function ($row) use ($workspace) {
                                   $isSender = $row->sender_participant_id == $workspace['participant']->id;
                                   return [
                                        'id' => $isSender
                                             ? $row->receiver_organization_id
                                             : $row->sender_organization_id,

                                        'name' => $isSender
                                             ? $row->receiver_organization
                                             : $row->sender_organization,
                                   ];

                              })
                              ->unique('id')
                              ->sortBy('name')
                              ->map(fn ($organization) => '
                                   <option value="'.$organization['id'].'">
                                        '.e($organization['name']).'
                                   </option>
                              ')
                              ->implode('')
                    . '</select>',

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

          return DataTables::of($recommendations)
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
                    $badge = match ($row->participant_availability) {
                         'preferred' => 'bg-primary',
                         'available' => 'bg-success',
                         default => 'bg-secondary',
                    };
                    return '<span class="badge fs-xs '.$badge.'">'.ucfirst($row->participant_availability).'</span>';
               })

               /*
               |--------------------------------------------------------------------------
               | Compatibility
               |--------------------------------------------------------------------------
               */
               ->addColumn('compatibility', function ($row) {
                    return '<span class="badge fs-xs bg-info">'.e($row->compatibility_score).'</span>';
               })

               /*
               |--------------------------------------------------------------------------
               | Ranking
               |--------------------------------------------------------------------------
               */
               ->addColumn('ranking', function ($row) {
                    return '
                         <div class="d-flex justify-content-center badge fs-sm bg-warning text-white align-items-center"><i class="ph-star ph-sm me-1"></i>'.e($row->ranking_score).'</div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Recommendation
               |--------------------------------------------------------------------------
               */
               ->addColumn('recommendation', function ($row) {
                    if ($row->auto_recommend) {
                         return '<span class="badge bg-success">Recommended</span>';
                    }

                    return '<span class="badge bg-secondary">Compatible</span>';
               })
               ->addColumn('event_time_slot_id', function ($row) {
                    return $row->event_time_slot_id;
               })

               /*
               |--------------------------------------------------------------------------
               | Raw Columns
               |--------------------------------------------------------------------------
               */
               ->rawColumns(['participant_id','profile','slot','availability','compatibility','ranking','recommendation','event_time_slot_id',])
               ->with('filters', $customFilters)
               ->make(true);
     }

     /*
     |--------------------------------------------------------------------------
     | Profile
     |--------------------------------------------------------------------------
     */
     public function profile(Request $request,Event $event,EventParticipant $participant,): View {
          /*
          |--------------------------------------------------------------------------
          | Participant Belongs To Event
          |--------------------------------------------------------------------------
          */
          abort_unless(
               $participant->eventOrganization?->event_id === $event->id,
               404
          );

          /*
          |--------------------------------------------------------------------------
          | Load Relationships
          |--------------------------------------------------------------------------
          */
          $participant->load([
               'organizationUser.user',
               'organizationUser.organization',
               'eventOrganization',
          ]);

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
          | View
          |--------------------------------------------------------------------------
          */
          return view(
               'my_events.matchmaking.partials.profile_content',
               compact(
                    'event',
                    'participant',
                    'eventTimeSlotId',
               )
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Show
     |--------------------------------------------------------------------------
     */
     public function show(Event $event,MeetingRequest $meetingRequest,): View {
          abort_unless(
               auth()->user()->canPermission('my-events.view'),
               403
          );

          /*
          |--------------------------------------------------------------------------
          | Request belongs to Event
          |--------------------------------------------------------------------------
          */
          abort_unless(
               $meetingRequest->timeSlot?->schedule?->event_id === $event->id,
               404
          );

          /*
          |--------------------------------------------------------------------------
          | Relationships
          |--------------------------------------------------------------------------
          */
          $meetingRequest->load([
               'senderParticipant.organizationUser.user',
               'senderParticipant.organizationUser.organization',

               'receiverParticipant.organizationUser.user',
               'receiverParticipant.organizationUser.organization',

               'timeSlot.schedule',
          ]);

          /*
          |--------------------------------------------------------------------------
          | Logged-in Participant
          |--------------------------------------------------------------------------
          */
          $participant = $this->workspaceService
               ->participant($event);

          /*
          |--------------------------------------------------------------------------
          | View
          |--------------------------------------------------------------------------
          */
          return view(
               'my_events.meeting_requests.partials.content',
               compact(
                    'event',
                    'meetingRequest',
                    'participant',
               )
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Accept Meeting Request
     |--------------------------------------------------------------------------
     */
     public function accept(Event $event,MeetingRequest $meetingRequest,MeetingLifecycleService $meetingLifecycleService,): JsonResponse {
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
          | Logged-in Participant
          |--------------------------------------------------------------------------
          */
          $participant = $this->workspaceService
               ->participant($event);

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
          | Receiver Authorization
          |--------------------------------------------------------------------------
          */
          $this->ensureReceiver(
               meetingRequest: $meetingRequest,
               participant: $participant
          );

          /*
          |--------------------------------------------------------------------------
          | Accept
          |--------------------------------------------------------------------------
          */
          $meeting = $meetingLifecycleService->accept(
               meetingRequest: $meetingRequest
          );

          /*
          |--------------------------------------------------------------------------
          | Response
          |--------------------------------------------------------------------------
          */
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
     public function reject(Event $event,MeetingRequest $meetingRequest,MeetingLifecycleService $meetingLifecycleService,): JsonResponse {
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
          | Logged-in Participant
          |--------------------------------------------------------------------------
          */
          $participant = $this->workspaceService
               ->participant($event);

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
          | Receiver Authorization
          |--------------------------------------------------------------------------
          */
          $this->ensureReceiver(
               meetingRequest: $meetingRequest,
               participant: $participant
          );

          /*
          |--------------------------------------------------------------------------
          | Reject
          |--------------------------------------------------------------------------
          */
          $meetingLifecycleService->reject(
               meetingRequest: $meetingRequest
          );

          /*
          |--------------------------------------------------------------------------
          | Response
          |--------------------------------------------------------------------------
          */
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
     public function cancelRequest(Event $event,MeetingRequest $meetingRequest,MeetingLifecycleService $meetingLifecycleService,): JsonResponse {
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
          | Logged-in Participant
          |--------------------------------------------------------------------------
          */
          $participant = $this->workspaceService
               ->participant($event);

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
          | Sender Authorization
          |--------------------------------------------------------------------------
          */
          $this->ensureSender(
               meetingRequest: $meetingRequest,
               participant: $participant
          );

          /*
          |--------------------------------------------------------------------------
          | Cancel Request
          |--------------------------------------------------------------------------
          */
          $meetingLifecycleService->cancelRequest(
               meetingRequest: $meetingRequest
          );

          /*
          |--------------------------------------------------------------------------
          | Response
          |--------------------------------------------------------------------------
          */
          return response()->json([
               'success' => true,
               'message' => 'Meeting request cancelled successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Ensure Receiver
     |--------------------------------------------------------------------------
     */
     protected function ensureReceiver(MeetingRequest $meetingRequest,EventParticipant $participant,): void {
          abort_unless(
               $meetingRequest->receiver_participant_id === $participant->id,
               403
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Ensure Sender
     |--------------------------------------------------------------------------
     */
     protected function ensureSender(MeetingRequest $meetingRequest,EventParticipant $participant,): void {
          abort_unless(
               $meetingRequest->sender_participant_id === $participant->id,
               403
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Ensure Meeting Request Belongs To Event
     |--------------------------------------------------------------------------
     */
     protected function ensureMeetingRequestBelongsToEvent(Event $event,MeetingRequest $meetingRequest,): void {
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

     /*
     |--------------------------------------------------------------------------
     | Ensure Meeting Belongs To Event
     |--------------------------------------------------------------------------
     */
     protected function ensureMeetingBelongsToEvent(Event $event,Meeting $meeting,): void {
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
     | Create
     |--------------------------------------------------------------------------
     */
     public function create(Request $request,Event $event,EventParticipant $participant,ParticipantRecommendationService $recommendationService,): View {
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
          | Find Recommendation
          |--------------------------------------------------------------------------
          */
          $recommendation = $recommendationService
               ->forParticipant(
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
          | Recommendation Available
          |--------------------------------------------------------------------------
          */
          abort_unless(
               $recommendation,
               404,
               'This matchmaking recommendation is no longer available.'
          );

          /*
          |--------------------------------------------------------------------------
          | Time Slot
          |--------------------------------------------------------------------------
          */
          $slot = EventTimeSlot::query()
               ->findOrFail(
                    $validated['event_time_slot_id']
               );

          /*
          |--------------------------------------------------------------------------
          | Modal
          |--------------------------------------------------------------------------
          */
          return view(
               'my_events.matchmaking.partials.request_form',
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
     public function store(Request $request,Event $event,MeetingRequestService $meetingRequestService,ParticipantRecommendationService $recommendationService,): JsonResponse {
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
          | Find Recommendation
          |--------------------------------------------------------------------------
          */
          $recommendation = $recommendationService
               ->forParticipant(
                    event: $event,
                    participant: $sender,
               )
               ->first(function ($recommendation) use ($validated) {
                    return
                         (int) $recommendation->participant_id ===
                              (int) $validated['receiver_participant_id']
                         &&
                         (int) $recommendation->event_time_slot_id ===
                              (int) $validated['event_time_slot_id'];
               });

          /*
          |--------------------------------------------------------------------------
          | Recommendation Available
          |--------------------------------------------------------------------------
          */
          abort_unless(
               $recommendation,
               422,
               'This matchmaking recommendation is no longer available.'
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
               recommendationScore: (int) $recommendation->compatibility_score,
               source: MeetingRequest::SOURCE_MATCHMAKING,
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
}