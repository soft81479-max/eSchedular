<?php

namespace App\Http\Controllers\MyEvents;

use App\Models\Event;
use Illuminate\View\View;

class OverviewController extends WorkspaceController {
     public function index(Event $event): View {
          return view(
               'my_events.overview.index',
               $this->workspace($event)
          );
     }

}