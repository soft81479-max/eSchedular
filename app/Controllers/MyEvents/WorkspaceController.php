<?php

namespace App\Http\Controllers\MyEvents;

use App\Models\Event;
use App\Http\Controllers\Controller;
use App\Services\MyEvents\MyEventWorkspaceService;

abstract class WorkspaceController extends Controller {
     public function __construct(
          protected MyEventWorkspaceService $workspaceService,
     ) {
     }

     protected function workspace(Event $event): array
     {
          return $this->workspaceService->workspace($event);
     }
}