<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Shared\EventAccessService;

class EnsureEventAccess {
    protected EventAccessService $eventAccessService;

    public function __construct(EventAccessService $eventAccessService) {
        $this->eventAccessService = $eventAccessService;
    }

    /*
    |--------------------------------------------------------------------------
    | Handle
    |--------------------------------------------------------------------------
    */
    public function handle(Request $request,Closure $next) {
        $event = $request->route('event');
        
        /*
        |--------------------------------------------------------------------------
        | Skip If No Event
        |--------------------------------------------------------------------------
        */
        if (! $event) {
            return $next($request);
        }
        
        if (!$event instanceof \App\Models\Event) {
            $event = \App\Models\Event::findOrFail($event);
        }

        /*
        |--------------------------------------------------------------------------
        | Check Access
        |--------------------------------------------------------------------------
        */
        if (! $this->eventAccessService->canAccess($event)) {
            abort(403);
        }

        return $next($request);
    }
}