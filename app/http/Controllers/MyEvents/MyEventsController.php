<?php

namespace App\Http\Controllers\MyEvents;

use Illuminate\View\View;
use App\Http\Controllers\Controller;
use App\Services\MyEvents\MyEventsService;

class MyEventsController extends Controller {
     public function __construct(
          protected MyEventsService $myEventsService,
     ) {
     }

     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     */
     public function index(): View {
          abort_unless(
               auth()->user()->canPermission('my-events.view'),
               403
          );
          return view(
               'my_events.index',
               [
                    'cards' => $this->myEventsService->cards(),
               ]
          );
     }
}