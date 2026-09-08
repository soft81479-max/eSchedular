<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PermissionMiddleware {
     public function handle(Request $request,Closure $next,string $permission) {
          $user = auth()->user();
          if (!$user) {
               abort(401);
          }

          if (!$user->canPermission($permission)) {
               abort(403);
          }

          return $next($request);
     }
}