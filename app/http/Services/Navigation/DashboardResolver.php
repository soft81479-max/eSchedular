<?php

namespace App\Services\Navigation;

use App\Models\User;

class DashboardResolver {
    /**
     * Resolve dashboard route after authentication.
     */
    public function resolve(User $user): string
    {
        /*
        |--------------------------------------------------------------------------
        | V2.5 Unified Dashboard Strategy
        |--------------------------------------------------------------------------
        |
        | All authenticated users land on the same dashboard.
        | Dashboard content becomes role-aware.
        |
        */

        return 'dashboard';
    }
}