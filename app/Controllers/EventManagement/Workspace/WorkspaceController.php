<?php

namespace App\Http\Controllers\EventManagement\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\Shared\EventAccessService;

use App\Services\Shared\MyWorkspaceBuilder;

abstract class WorkspaceController extends Controller {
     public function __construct(
          protected EventAccessService $eventAccessService,
          protected MyWorkspaceBuilder $workspaceBuilder,
     ) {
     }

     protected function workspace(Event $event): array {
          if (!auth()->user()->canPermission('events.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $event = $this->eventAccessService->show($event);

          return [
               'event'   => $event,
               'toolbar' => $this->workspaceBuilder->buildForUser($event, auth()->user()),
          ];
     }
}