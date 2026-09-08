<?php

namespace App\Http\Controllers\Representative;

use App\Models\Event;
use Illuminate\View\View;

class DashboardController extends WorkspaceController
{
    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    public function index(Event $event,RepresentativeDashboardService $dashboard): View {

     $workspace = $this->workspace($event);

     return view(
          'representative.workspace.dashboard.index',

          array_merge(

               $workspace,

               $dashboard->dashboard(
                    $event,
                    $workspace['participant']
               )

          )
     );
     }
}