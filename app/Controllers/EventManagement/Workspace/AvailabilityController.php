<?php

namespace App\Http\Controllers\EventManagement\Workspace;

use App\Models\Event;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\EventTimeSlot;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\EventOrganization;
use App\Models\EventParticipant;
use App\Models\ParticipantAvailability;

class AvailabilityController extends WorkspaceController
{
     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     */
     public function index(
          Event $event,
          EventOrganization $eventOrganization,
          EventParticipant $eventParticipant,
          Request $request
     ): View {

          /*
          |--------------------------------------------------------------------------
          | Validate Workspace Hierarchy
          |--------------------------------------------------------------------------
          */
          $this->validateHierarchy(
               event: $event,
               eventOrganization: $eventOrganization,
               eventParticipant: $eventParticipant
          );

          /*
          |--------------------------------------------------------------------------
          | Load Participant
          |--------------------------------------------------------------------------
          */
          $eventParticipant->load([
               'organizationUser',
               'eventOrganization.organization',
               'eventOrganization.participantType',
          ]);

          /*
          |--------------------------------------------------------------------------
          | Event Schedules
          |--------------------------------------------------------------------------
          */
          $event->load([
               'schedules' => function ($query) {

                    $query
                         ->withCount('timeSlots')
                         ->orderBy('schedule_date')
                         ->orderBy('sort_order');

               },
          ]);

          /*
          |--------------------------------------------------------------------------
          | Selected Schedule
          |--------------------------------------------------------------------------
          */
          $selectedSchedule = null;

          if ($request->filled('schedule')) {

               $selectedSchedule = $event
                    ->schedules()
                    ->with([
                         'timeSlots' => function ($query) {

                              $query
                                   ->orderBy('start_at')
                                   ->orderBy('sort_order');

                         },
                    ])
                    ->whereKey(
                         $request->input('schedule')
                    )
                    ->first();

          }

          /*
          |--------------------------------------------------------------------------
          | Default Schedule
          |--------------------------------------------------------------------------
          */
          if (!$selectedSchedule) {

               $selectedSchedule = $event
                    ->schedules()
                    ->with([
                         'timeSlots' => function ($query) {

                              $query
                                   ->orderBy('start_at')
                                   ->orderBy('sort_order');

                         },
                    ])
                    ->orderBy('schedule_date')
                    ->orderBy('sort_order')
                    ->first();

          }

          /*
          |--------------------------------------------------------------------------
          | Availability Map
          |--------------------------------------------------------------------------
          */
          $availabilityMap = [];

          if ($selectedSchedule) {

               $availabilityMap = ParticipantAvailability::query()

                    ->where(
                         'event_participant_id',
                         $eventParticipant->id
                    )

                    ->whereIn(
                         'event_time_slot_id',
                         $selectedSchedule
                              ->timeSlots
                              ->pluck('id')
                    )

                    ->pluck(
                         'status',
                         'event_time_slot_id'
                    )

                    ->toArray();

          }

          /*
          |--------------------------------------------------------------------------
          | View
          |--------------------------------------------------------------------------
          */
          return view(
               'event_management.workspace.organizations.representatives.availability.index',
               array_merge(
                    $this->workspace($event),
                    [
                         'eventOrganization' => $eventOrganization,
                         'eventParticipant' => $eventParticipant,
                         'selectedSchedule' => $selectedSchedule,
                         'availabilityMap' => $availabilityMap,
                    ]
               )
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Toggle Availability
     |--------------------------------------------------------------------------
     */
     public function toggle(
          Event $event,
          EventOrganization $eventOrganization,
          EventParticipant $eventParticipant,
          Request $request
     ): JsonResponse {

          /*
          |--------------------------------------------------------------------------
          | Validate Workspace Hierarchy
          |--------------------------------------------------------------------------
          */
          $this->validateHierarchy(
               event: $event,
               eventOrganization: $eventOrganization,
               eventParticipant: $eventParticipant
          );

          /*
          |--------------------------------------------------------------------------
          | Validation
          |--------------------------------------------------------------------------
          */
          $validated = $request->validate([
               'event_time_slot_id' => [
                    'required',
                    'integer',
                    'exists:event_time_slots,id',
               ],

               'status' => [
                    'required',
                    Rule::in(
                         ParticipantAvailability::statuses()
                    ),
               ],
          ]);

          /*
          |--------------------------------------------------------------------------
          | Event Time Slot
          |--------------------------------------------------------------------------
          */
          $slot = EventTimeSlot::query()

               ->whereKey(
                    $validated['event_time_slot_id']
               )

               ->whereHas(
                    'schedule',
                    function ($query) use ($event) {

                         $query->where(
                              'event_id',
                              $event->id
                         );

                    }
               )

               ->firstOrFail();

          /*
          |--------------------------------------------------------------------------
          | Bookable Slot
          |--------------------------------------------------------------------------
          */
          if (!$slot->is_bookable) {

               return response()->json([
                    'success' => false,
                    'message' => 'Availability cannot be changed for a non-bookable slot.',
               ], 422);

          }

          /*
          |--------------------------------------------------------------------------
          | Accepted Meeting Request
          |--------------------------------------------------------------------------
          */
          $hasAcceptedMeetingRequest = MeetingRequest::query()

               ->where(
                    'event_time_slot_id',
                    $slot->id
               )

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

               })

               ->where(
                    'status',
                    MeetingRequest::STATUS_ACCEPTED
               )

               ->exists();

          if (
               $hasAcceptedMeetingRequest
               &&
               $validated['status']
                    === ParticipantAvailability::STATUS_UNAVAILABLE
          ) {

               return response()->json([
                    'success' => false,
                    'message' => 'Participant already has an accepted meeting in this slot.',
               ], 422);

          }

          /*
          |--------------------------------------------------------------------------
          | Locked Meeting
          |--------------------------------------------------------------------------
          */
          $hasLockedMeeting = Meeting::query()

               ->where(
                    'event_time_slot_id',
                    $slot->id
               )

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

               })

               ->whereIn(
                    'status',
                    [
                         Meeting::STATUS_COMPLETED,
                         Meeting::STATUS_NO_SHOW,
                    ]
               )

               ->exists();

          if ($hasLockedMeeting) {

               return response()->json([
                    'success' => false,
                    'message' => 'Availability cannot be changed for a completed or no-show meeting.',
               ], 422);

          }

          /*
          |--------------------------------------------------------------------------
          | Save Availability
          |--------------------------------------------------------------------------
          */
          DB::transaction(function () use (
               $eventParticipant,
               $slot,
               $validated
          ) {

               ParticipantAvailability::updateOrCreate(
                    [
                         'event_participant_id' => $eventParticipant->id,
                         'event_time_slot_id' => $slot->id,
                    ],
                    [
                         'status' => $validated['status'],
                    ]
               );

          });

          return response()->json([
               'success' => true,
               'message' => 'Availability updated successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Validate Hierarchy
     |--------------------------------------------------------------------------
     */
     protected function validateHierarchy(
          Event $event,
          EventOrganization $eventOrganization,
          EventParticipant $eventParticipant
     ): void {

          /*
          |--------------------------------------------------------------------------
          | Organization Belongs To Event
          |--------------------------------------------------------------------------
          */
          abort_unless(
               $eventOrganization->event_id === $event->id,
               404
          );

          /*
          |--------------------------------------------------------------------------
          | Participant Belongs To Event Organization
          |--------------------------------------------------------------------------
          */
          abort_unless(
               $eventParticipant->event_organization_id
                    ===
               $eventOrganization->id,
               404
          );
     }
}