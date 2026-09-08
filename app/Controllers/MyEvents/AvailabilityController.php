<?php

namespace App\Http\Controllers\MyEvents;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

use Carbon\Carbon;

use App\Models\Event;
use App\Models\EventTimeSlot;

use App\Services\MyEvents\AvailabilityService;

class AvailabilityController extends WorkspaceController {
    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */
    public function index(Event $event,AvailabilityService $availabilityService,): View {
        $workspace = $this->workspace($event);

        $workspace['initialMonth'] = $availabilityService->initialMonth(
            event: $workspace['event']
        );

        $workspace['initialDate'] = $availabilityService->initialDate(
            event: $workspace['event']
        );

        $workspace = array_merge(
            $workspace,
            $availabilityService->workspace($workspace)
        );

        return view(
            'my_events.availability.index',
            $workspace
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Calendar
    |--------------------------------------------------------------------------
    */
    public function calendar(Request $request,Event $event,AvailabilityService $availabilityService,): JsonResponse {
        $workspace = $this->workspace($event);

        return response()->json(
            $availabilityService->calendar(
                event: $workspace['event'],
                participant: $workspace['participant'],
                month: $request->filled('month')
                    ? Carbon::createFromFormat('Y-m', $request->month)
                    : null,
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Slots
    |--------------------------------------------------------------------------
    */
    public function slots(Request $request,Event $event,AvailabilityService $availabilityService,): JsonResponse {
        $workspace = $this->workspace($event);

        return response()->json(
            $availabilityService->slots(
                event: $workspace['event'],
                participant: $workspace['participant'],
                date: $request->filled('date')
                    ? Carbon::parse($request->date)
                    : null,
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */
    public function show(Event $event,EventTimeSlot $eventTimeSlot,AvailabilityService $availabilityService,): View {
        abort_unless(
            auth()->user()->canPermission('my-events.view'),
            403
        );

        $this->ensureTimeSlotBelongsToEvent(
            event: $event,
            slot: $eventTimeSlot
        );

        $workspace = $this->workspace($event);

        $slot = $availabilityService->slot(
            slot: $eventTimeSlot,
            participant: $workspace['participant']
        );

        return view(
            'my_events.availability.partials.content',
            [
                'event'       => $event,
                'participant' => $workspace['participant'],
                'slot'        => $slot,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */
    public function update(Request $request,Event $event,EventTimeSlot $eventTimeSlot,AvailabilityService $availabilityService,): JsonResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                'string',
            ],
        ]);

        $this->ensureTimeSlotBelongsToEvent(
            event: $event,
            slot: $eventTimeSlot
        );

        $workspace = $this->workspace($event);

        $slot = $availabilityService->update(
            slot: $eventTimeSlot,
            participant: $workspace['participant'],
            attributes: $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Availability updated successfully.',
            'data'    => $slot,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Ensure Time Slot Belongs To Event
    |--------------------------------------------------------------------------
    */
    protected function ensureTimeSlotBelongsToEvent(Event $event,EventTimeSlot $slot,): void {
        abort_unless(
            $slot->schedule()
                ->where('event_id', $event->id)
                ->exists(),
            404
        );
    }
}