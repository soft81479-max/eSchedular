<?php

namespace App\Http\Controllers\EventManagement\Workspace;

use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\View\View;

class ParticipantController extends WorkspaceController {
     public function index(Event $event): View {
          $participants = EventParticipant::query()
               ->with([
                    'organizationUser.user',
                    'eventOrganization.organization',
                    'eventOrganization.participantType',
               ])
               ->whereHas('eventOrganization', function ($query) use ($event) {
                    $query->where('event_id', $event->id);
               })
               ->orderByDesc('is_primary')
               ->orderBy('badge_name')
               ->paginate(200);

          return view(
               'event_management.workspace.participants.index',
               array_merge(
                    $this->workspace($event),
                    compact('participants')
               )
          );
     }
}