<?php

namespace App\Http\Controllers\MyEvents;

use App\Models\Event;
use Illuminate\View\View;
use App\Services\MyEvents\ParticipantService;

class ParticipantController extends WorkspaceController {
    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */
    public function index(Event $event,ParticipantService $participantService,): View {
        $workspace = $this->workspace($event);
        return view(
            'my_events.participants.index',
            array_merge(
                $workspace,
                $participantService->workspace($workspace),
            ),
        );
    }
}