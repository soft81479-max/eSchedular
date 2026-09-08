<?php

namespace App\Http\Controllers\MyEvents;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

use Yajra\DataTables\Facades\DataTables;

use App\Models\Event;
use App\Models\Meeting;
use App\Models\EventParticipant;

use App\Services\MyEvents\MeetingService;
use App\Services\Networking\MeetingLifecycleService;

class MeetingsControllerX extends WorkspaceController {
     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     */
     public function index(Event $event,MeetingService $meetingService,): View {
          /*
          |--------------------------------------------------------------------------
          | Workspace
          |--------------------------------------------------------------------------
          */
          $workspace = $this->workspace($event);

          /*
          |--------------------------------------------------------------------------
          | Meeting Statistics
          |--------------------------------------------------------------------------
          */
          $workspace['meetingStatistics'] = $meetingService->meetingStatistics(
               $workspace['event'],
               $workspace['participant']
          );

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
     | Datatable
     |--------------------------------------------------------------------------
     */
     public function datatable(Event $event,MeetingService $meetingService,): JsonResponse {
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
          | Custom Filters
          |--------------------------------------------------------------------------
          */
          $customFilters = [
               /*
               |--------------------------------------------------------------------------
               | Direction
               |--------------------------------------------------------------------------
               */
               'direction' => '
                    <select id="direction" class="form-select form-select-sm">
                         <option value="">All Meetings</option>
                         <option value="incoming">Incoming</option>
                         <option value="outgoing">Outgoing</option>
                    </select>
               ',

               /*
               |--------------------------------------------------------------------------
               | Status
               |--------------------------------------------------------------------------
               */
               'status' => '
                    <select id="status" class="form-select form-select-sm">
                         <option value="">All Statuses</option>
                         <option value="'.Meeting::STATUS_ACCEPTED.'">Accepted</option>
                         <option value="'.Meeting::STATUS_COMPLETED.'">Completed</option>
                         <option value="'.Meeting::STATUS_CANCELLED.'">Cancelled</option>
                         <option value="'.Meeting::STATUS_NO_SHOW.'">No Show</option>
                    </select>
               ',

               /*
               |--------------------------------------------------------------------------
               | Meeting Mode
               |--------------------------------------------------------------------------
               */
               'meeting_mode' => '
                    <select id="meeting_mode" class="form-select form-select-sm">
                         <option value="">All Modes</option>
                         <option value="'.Meeting::MODE_PHYSICAL.'">Physical</option>
                         <option value="'.Meeting::MODE_VIRTUAL.'">Virtual</option>
                         <option value="'.Meeting::MODE_HYBRID.'">Hybrid</option>
                    </select>
               ',
          ];

          /*
          |--------------------------------------------------------------------------
          | Workspace
          |--------------------------------------------------------------------------
          */
          $workspace = $this->workspace($event);

          /*
          |--------------------------------------------------------------------------
          | Filters
          |--------------------------------------------------------------------------
          */
          $filters = [
               'direction' => request('direction'),
               'status' => request('status'),
               'mode' => request('meeting_mode'),
          ];

          /*
          |--------------------------------------------------------------------------
          | Meetings
          |--------------------------------------------------------------------------
          */
          $meetings = $meetingService
               ->meetingsForDatatable(
                    workspace: $workspace,
                    filters: $filters,
               );

          /*
          |--------------------------------------------------------------------------
          | Participant
          |--------------------------------------------------------------------------
          */
          $participant = $workspace['participant'];

          return DataTables::of($meetings)
               /*
               |--------------------------------------------------------------------------
               | Direction
               |--------------------------------------------------------------------------
               */
               ->addColumn('direction', function ($row) {
                    $badge = $row->direction === 'Incoming'
                         ? 'bg-success'
                         : 'bg-primary';

                    return '
                         <span class="badge '.$badge.'">
                              '.$row->direction.'
                         </span>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Representative
               |--------------------------------------------------------------------------
               */
               ->addColumn('representative', function ($row) {
                    return '
                         <div class="d-flex align-items-center">
                              <img src="'.$row->participant_photo.'" class="rounded-circle me-2" width="42" height="42" style="object-fit:cover;">
                              <div>
                                   <div class="fw-semibold">'.e($row->participant_name).'</div>
                                   <div class="text-muted fs-xs">'.e($row->participant_title).'</div>
                                   <div class="text-muted fs-xs">'.e($row->participant_organization).'</div>
                              </div>
                         </div>
                    ';
               })

               /*
               |--------------------------------------------------------------------------
               | Meeting Date
               |--------------------------------------------------------------------------
               */
               ->addColumn('meeting_date', function ($row) {
                    return optional(
                         $row->timeSlot?->start_at
                    )?->format('d M Y');
               })

               /*
               |--------------------------------------------------------------------------
               | Meeting Time
               |--------------------------------------------------------------------------
               */
               ->addColumn('meeting_time', function ($row) {
                    return
                         optional($row->timeSlot?->start_at)?->format('h:i A')
                         .' - '.
                         optional($row->timeSlot?->end_at)?->format('h:i A');
               })

               /*
               |--------------------------------------------------------------------------
               | Status
               |--------------------------------------------------------------------------
               */
               ->addColumn('status', function ($row) {
                    return $row->status_badge;
               })

               /*
               |--------------------------------------------------------------------------
               | Attendance
               |--------------------------------------------------------------------------
               */
               ->addColumn('attendance', function ($row) {
                    if ($row->status === Meeting::STATUS_NO_SHOW) {
                         return '<span class="badge bg-dark">No Show</span>';
                    }
                    if ($row->started_at) {
                         return '<span class="badge bg-success">Checked In</span>';
                    }
                    return '<span class="badge bg-secondary">Pending</span>';
               })

               /*
               |--------------------------------------------------------------------------
               | Mode
               |--------------------------------------------------------------------------
               */
               ->addColumn('mode', function ($row) {
                    return ucfirst(
                         $row->meeting_mode
                    );
               })

               /*
               |--------------------------------------------------------------------------
               | Actions
               |--------------------------------------------------------------------------
               */
               ->addColumn('actions', function ($row) use ($event, $participant) {
                    $buttons = '';

                    /*
                    |--------------------------------------------------------------------------
                    | Accepted Meeting
                    |--------------------------------------------------------------------------
                    */
                    if ($row->status === Meeting::STATUS_ACCEPTED) {
                         /*
                         |--------------------------------------------------------------------------
                         | Complete
                         |--------------------------------------------------------------------------
                         */
                         $buttons .= action_button('completeMeeting', [
                              'btn-type' => 'btn-icon',
                              'title' => 'Complete Meeting',
                              'icon' => 'ph-check-circle',
                              'color' => 'light text-success',
                              'url' => route(
                                   'my-events.meetings.complete',
                                   [
                                        $event,
                                        $row,
                                   ]
                              ),
                              'method' => 'POST',
                              'table' => 'meetingsTable',
                         ]);

                         /*
                         |--------------------------------------------------------------------------
                         | Cancel
                         |--------------------------------------------------------------------------
                         */
                         $buttons .= action_button('cancelMeeting', [
                              'btn-type' => 'btn-icon',
                              'title' => 'Cancel Meeting',
                              'icon' => 'ph-prohibit',
                              'color' => 'light text-warning',
                              'url' => route(
                                   'my-events.meetings.cancel',
                                   [
                                        $event,
                                        $row,
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
                              'title' => 'Mark No Show',
                              'icon' => 'ph-user-minus',
                              'color' => 'light text-danger',
                              'url' => route(
                                   'my-events.meetings.no-show',
                                   [
                                        $event,
                                        $row,
                                   ]
                              ),
                              'method' => 'POST',
                              'table' => 'meetingsTable',
                         ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | View
                    |--------------------------------------------------------------------------
                    */
                    $buttons .= action_button('view', [
                         'btn-type'   => 'btn-icon',
                         'title'      => 'View',
                         'icon'       => 'ph-eye',
                         'color'      => 'light',
                         'class'      => 'btn-view-request'
                    ]);

                    return $buttons
                         ? '<div class="d-flex justify-content-center align-items-center gap-1">'.$buttons.'</div>'
                         : '-';
               })

               /*
               |--------------------------------------------------------------------------
               | Raw Columns
               |--------------------------------------------------------------------------
               */
               ->rawColumns(['direction','representative','status','attendance','actions',])
               ->with('filters',$customFilters)
               ->make(true);
     }

     /*
     |--------------------------------------------------------------------------
     | Show
     |--------------------------------------------------------------------------
     */
     public function show(Event $event,Meeting $meeting,): View {
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
          | Event Scope
          |--------------------------------------------------------------------------
          */
          $this->ensureMeetingBelongsToEvent(
               event: $event,
               meeting: $meeting
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
          | Relationships
          |--------------------------------------------------------------------------
          */
          $meeting->load([
               'meetingRequest',

               'senderParticipant.organizationUser.user',
               'senderParticipant.organizationUser.organization',

               'receiverParticipant.organizationUser.user',
               'receiverParticipant.organizationUser.organization',

               'timeSlot.schedule',
          ]);

          /*
          |--------------------------------------------------------------------------
          | Other Participant
          |--------------------------------------------------------------------------
          */
          $otherParticipant = $meeting->otherParticipant(
               $participant
          );

          /*
          |--------------------------------------------------------------------------
          | View
          |--------------------------------------------------------------------------
          */
          return view(
               'my_events.meetings.partials.content',
               compact(
                    'event',
                    'meeting',
                    'participant',
                    'otherParticipant'
               )
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
     | Ensure Sender
     |--------------------------------------------------------------------------
     */
     protected function ensureSender(Meeting $meeting,EventParticipant $participant,): void {
          abort_unless(
               $meeting->sender_participant_id === $participant->id,
               403
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Ensure Receiver
     |--------------------------------------------------------------------------
     */
     protected function ensureReceiver(Meeting $meeting,EventParticipant $participant,): void {
          abort_unless(
               $meeting->receiver_participant_id === $participant->id,
               403
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Ensure Participant
     |--------------------------------------------------------------------------
     */
     protected function ensureParticipant(Meeting $meeting,EventParticipant $participant,): void {
          abort_unless(
               in_array(
                    $participant->id,
                    [
                         $meeting->sender_participant_id,
                         $meeting->receiver_participant_id,
                    ]
               ),
               403
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Cancel Meeting
     |--------------------------------------------------------------------------
     */
     public function cancel(Event $event,Meeting $meeting,MeetingLifecycleService $meetingLifecycleService,): JsonResponse {
          /*
          |--------------------------------------------------------------------------
          | Logged-in Participant
          |--------------------------------------------------------------------------
          */
          $participant = $this->workspace($event)['participant'];

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
          | Authorization
          |--------------------------------------------------------------------------
          */
          $this->ensureSender(
               meeting: $meeting,
               participant: $participant
          );

          /*
          |--------------------------------------------------------------------------
          | Cancel
          |--------------------------------------------------------------------------
          */
          $meetingLifecycleService->cancel(
               meeting: $meeting
          );

          /*
          |--------------------------------------------------------------------------
          | Response
          |--------------------------------------------------------------------------
          */
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
     public function complete(Event $event,Meeting $meeting,MeetingLifecycleService $meetingLifecycleService,): JsonResponse {
          /*
          |--------------------------------------------------------------------------
          | Logged-in Participant
          |--------------------------------------------------------------------------
          */
          $participant = $this->workspace($event)['participant'];

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
          | Authorization
          |--------------------------------------------------------------------------
          */
          $this->ensureParticipant(
               meeting: $meeting,
               participant: $participant
          );

          /*
          |--------------------------------------------------------------------------
          | Complete
          |--------------------------------------------------------------------------
          */
          $meetingLifecycleService->complete(
               meeting: $meeting
          );

          /*
          |--------------------------------------------------------------------------
          | Response
          |--------------------------------------------------------------------------
          */
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
     public function noShow(Event $event,Meeting $meeting,MeetingLifecycleService $meetingLifecycleService,): JsonResponse {
          /*
          |--------------------------------------------------------------------------
          | Logged-in Participant
          |--------------------------------------------------------------------------
          */
          $participant = $this->workspace($event)['participant'];

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
          | Authorization
          |--------------------------------------------------------------------------
          */
          $this->ensureParticipant(
               meeting: $meeting,
               participant: $participant
          );

          /*
          |--------------------------------------------------------------------------
          | No Show
          |--------------------------------------------------------------------------
          */
          $meetingLifecycleService->noShow(
               meeting: $meeting
          );

          /*
          |--------------------------------------------------------------------------
          | Response
          |--------------------------------------------------------------------------
          */
          return response()->json([
               'success' => true,
               'message' => 'Meeting marked as no-show successfully.',
          ]);
     }
}
