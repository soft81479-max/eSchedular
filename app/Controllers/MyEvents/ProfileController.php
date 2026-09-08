<?php

namespace App\Http\Controllers\MyEvents;

use App\Models\Event;
use Illuminate\View\View;

class ProfileController extends WorkspaceController {

     public function index(Event $event): View {
          return view(
               'my_events.profile.index',
               $this->workspace($event)
          );
     }

}