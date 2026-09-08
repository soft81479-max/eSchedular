<?php

namespace App\Http\Controllers\MyEvents;

use App\Models\Event;
use Illuminate\View\View;
use App\Services\MyEvents\OrganizationService;

class OrganizationsController extends WorkspaceController {
     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     */
     public function index(Event $event,OrganizationService $organizationService,): View {
          $workspace = $this->workspace($event);
          return view(
               'my_events.organizations.index',
               array_merge(
                    $workspace,
                    $organizationService->workspace($workspace),
               ),
          );
     }
}