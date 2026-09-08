<?php

namespace App\Http\Controllers\EventManagement\Workspace;

use App\Models\Event;
use App\Models\EventTimeSlot;
use App\Models\MeetingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;
use App\Services\Networking\MeetingRequestService;
use App\Services\Networking\ParticipantRecommendationService;

class MatchmakingController extends WorkspaceController {
     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     */
     public function index(Event $event): View {
          return view(
               'event_management.workspace.matchmaking.index',
               $this->workspace($event)
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Datatable
     |--------------------------------------------------------------------------
     */
     public function datatable(Event $event,ParticipantRecommendationService $recommendationService): JsonResponse {
          abort_unless(
               auth()->user()->canPermission('events.view'),
               403
          );

          /*
          |--------------------------------------------------------------------------
          | Recommendations
          |--------------------------------------------------------------------------
          */
          $recommendations = $recommendationService->forEvent(
               event: $event
          );

          return DataTables::of($recommendations)
               ->addIndexColumn()

               /*
               |--------------------------------------------------------------------------
               | Slot
               |--------------------------------------------------------------------------
               */
               ->addColumn('slot', function ($row) {
                    $startAt = \Carbon\Carbon::parse($row->start_at);
                    $endAt = \Carbon\Carbon::parse($row->end_at);

                    return '
                         <div class="text-nowrap">
                              <div class="fw-semibold fs-md">'.$startAt->format('d M Y').'</div>
                              <div class="text-muted fs-xs">'.$startAt->format('h:i A').'-'.$endAt->format('h:i A').'</div>
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Participant
               |--------------------------------------------------------------------------
               |
               | Sender side of the recommendation pair.
               |
               */
               ->addColumn('participant', function ($row) {
                    $avatar = $row->sender_photo ? asset('storage/'.$row->sender_photo) : asset('images/defaults/avatar.png');
                    return '
                         <div class="d-flex align-items-center">
                              <img src="'.e($avatar).'" class="rounded-circle me-2" width="42" height="42" style="object-fit:cover;"alt="">
                              <div>
                                   <div class="fw-semibold fs-md">'.e($row->sender_name).'</div>
                                   <div class="text-muted fs-xs">'.e($row->sender_designation?? '-').'</div>
                                   <div class="text-muted fs-xs">'.e($row->sender_organization?? '-').'</div>
                              </div>
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Match With
               |--------------------------------------------------------------------------
               |
               | Receiver side of the recommendation pair.
               |
               */
               ->addColumn('match_with', function ($row) {
                    $avatar = $row->receiver_photo ? asset('storage/'.$row->receiver_photo) : asset('images/defaults/avatar.png');
                    return '
                         <div class="d-flex align-items-center">
                              <img src="'.e($avatar).'" class="rounded-circle me-2" width="42" height="42" style="object-fit:cover;" alt="">
                              <div>
                                   <div class="fw-semibold fs-md">'.e($row->receiver_name).'</div>
                                   <div class="text-muted fs-xs">'.e($row->receiver_designation?? '-').'</div>
                                   <div class="text-muted fs-xs">'.e($row->receiver_organization?? '-').'</div>
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
                              <div class="d-flex align-items-center justify-content-between gap-2">
                                   <span class="text-muted fs-sm">Participant</span>
                                   <span class="badge fs-xs '.$senderBadge.'">'.e(ucfirst($row->sender_availability)).'</span>
                              </div>

                              <div class="d-flex align-items-center justify-content-between gap-2">
                                   <span class="text-muted fs-sm">Match</span>
                                   <span class="badge fs-xs '.$receiverBadge.'">'.e(ucfirst($row->receiver_availability)).'</span>
                              </div>
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Compatibility
               |--------------------------------------------------------------------------
               */
               ->addColumn('compatibility', function ($row) {
                    return '
                         <span class="badge fs-xs bg-info">'.e((string) $row->compatibility_score).'</span>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Ranking
               |--------------------------------------------------------------------------
               */
               ->addColumn('ranking', function ($row) {
                    return '<span class="d-flex justify-content-center badge fs-xs bg-warning text-white"><i class="ph-star fs-xs me-1"></i>'.e((string) $row->ranking_score).'</span>';
               })

               /*
               |--------------------------------------------------------------------------
               | Actions
               |--------------------------------------------------------------------------
               */
               ->addColumn('actions', function ($row) use ($event) {
                    if (!auth()->user()->canPermission('meetings.create')) {
                         return '-';
                    }
                    return action_button('add', [
                         'modal-size' => 'modal-md',
                         'btn-type' => 'btn-icon',
                         'title' => 'Request Meeting',
                         'icon' => 'ph-handshake',
                         'color' => 'primary',
                         'button-text' => 'Send Request',
                         'createUrl' => route(
                              'events.matchmaking.create',
                              [
                                   $event,
                                   'sender_participant_id' => $row->sender_participant_id,
                                   'receiver_participant_id' => $row->receiver_participant_id,
                                   'event_time_slot_id' => $row->event_time_slot_id,
                              ]
                         ),
                         'storeUrl' => route('events.matchmaking.store',$event),
                         'table' => 'matchmakingsTable',
                    ]);
               })

               /*
               |--------------------------------------------------------------------------
               | Raw Columns
               |--------------------------------------------------------------------------
               */
               ->rawColumns(['slot','participant','match_with','availability','compatibility','ranking','actions',])
               ->make(true);
     }

     /*
     |--------------------------------------------------------------------------
     | Create
     |--------------------------------------------------------------------------
     */
     public function create(Request $request,Event $event,ParticipantRecommendationService $recommendationService): View {
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
          | Find Recommendation
          |--------------------------------------------------------------------------
          */
          $recommendation = $recommendationService
               ->forEvent(
                    event: $event
               )
               ->first(
                    function ($recommendation) use ($validated) {
                         return
                              (int) $recommendation->sender_participant_id === (int) $validated['sender_participant_id']
                              &&
                              (int) $recommendation->receiver_participant_id === (int) $validated['receiver_participant_id']
                              &&
                              (int) $recommendation->event_time_slot_id === (int) $validated['event_time_slot_id'];
                    }
               );

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
               'event_management.workspace.matchmaking.partials.form',
               [
                    'event' => $event,
                    'senderParticipantId' => $validated['sender_participant_id'],
                    'receiverParticipantId' => $validated['receiver_participant_id'],
                    'eventTimeSlotId' => $validated['event_time_slot_id'],
                    'recommendation' => $recommendation,
                    'slot' => $slot,
               ]
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Store
     |--------------------------------------------------------------------------
     */
     public function store(Request $request,Event $event,MeetingRequestService $meetingRequestService,ParticipantRecommendationService $recommendationService): JsonResponse {
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
          | Find Current Recommendation
          |--------------------------------------------------------------------------
          |
          | Never trust the recommendation state submitted by the browser.
          | The recommendation must still exist in the current pipeline.
          |--------------------------------------------------------------------------
          */
          $recommendation = $recommendationService
               ->forEvent(
                    event: $event
               )
               ->first(
                    function ($recommendation) use ($validated) {
                         return
                              (int) $recommendation->sender_participant_id === (int) $validated['sender_participant_id']
                              &&
                              (int) $recommendation->receiver_participant_id === (int) $validated['receiver_participant_id']
                              &&
                              (int) $recommendation->event_time_slot_id === (int) $validated['event_time_slot_id'];
                    }
               );

          /*
          |--------------------------------------------------------------------------
          | Recommendation Still Available
          |--------------------------------------------------------------------------
          */
          abort_unless(
               $recommendation,
               422,
               'This matchmaking recommendation is no longer available.'
          );

          /*
          |--------------------------------------------------------------------------
          | Create Matchmaking Meeting Request
          |--------------------------------------------------------------------------
          */
          $meetingRequest = $meetingRequestService->create(
               event: $event,
               senderParticipantId: $validated['sender_participant_id'],
               receiverParticipantId: $validated['receiver_participant_id'],
               eventTimeSlotId: $validated['event_time_slot_id'],
               meetingMode: $validated['meeting_mode'],
               message: $validated['message'] ?? null,
               recommendationScore: (int) $recommendation->compatibility_score,
               source: MeetingRequest::SOURCE_MATCHMAKING
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