<?php

namespace App\Http\Controllers\Representative;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Representative\RepresentativeWorkspaceService;

abstract class WorkspaceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */
    public function __construct(
        protected RepresentativeWorkspaceService $workspaceService
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Workspace
    |--------------------------------------------------------------------------
    */
    protected function workspace(Event $event): array
    {
        return $this->workspaceService->workspace($event);
    }
}