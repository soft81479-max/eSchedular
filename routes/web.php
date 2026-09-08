<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
require __DIR__.'/auth.php';

Route::middleware(['auth'])->group(function () {
    require __DIR__.'/dashboard.php';

    /*
    |--------------------------------------------------------------------------
    | Event Management
    |--------------------------------------------------------------------------
    */
    require __DIR__.'/event-management/events.php';

    /*
    |--------------------------------------------------------------------------
    | Representative Workspace (My Events)
    |--------------------------------------------------------------------------
    */
    require __DIR__.'/my-events.php';

    /*
    |--------------------------------------------------------------------------
    | Event Workspace
    |--------------------------------------------------------------------------
    */
    require __DIR__.'/event-management/workspace/index.php';

    /*
    |--------------------------------------------------------------------------
    | Organizations
    |--------------------------------------------------------------------------
    */
    require __DIR__.'/organizations/organizations.php';
    require __DIR__.'/organizations/organizers.php';
    //require __DIR__.'/organizations/representatives.php';
    //require __DIR__.'/organizations/sponsors.php';
    //require __DIR__.'/organizations/partners.php';

    /*
    |--------------------------------------------------------------------------
    | Networking
    |--------------------------------------------------------------------------
    */
    //require __DIR__.'/networking/matchmaking.php';
    //require __DIR__.'/networking/meetings.php';
    //require __DIR__.'/networking/availability.php';

    /*
    |--------------------------------------------------------------------------
    | Exhibition
    |--------------------------------------------------------------------------
    */
    //require __DIR__.'/exhibition/booths.php';

    /*
    |--------------------------------------------------------------------------
    | Administration
    |--------------------------------------------------------------------------
    */
    require __DIR__.'/administration/users.php';
    require __DIR__.'/administration/roles.php';
    require __DIR__.'/administration/permissions.php';
    require __DIR__.'/administration/menus.php';
    require __DIR__.'/administration/settings.php';

    /*
    |--------------------------------------------------------------------------
    | Configurations
    |--------------------------------------------------------------------------
    */
    require __DIR__.'/configurations/organization-types.php';
    require __DIR__.'/configurations/networking-modes.php';
    require __DIR__.'/configurations/matching-strategies.php';
    require __DIR__.'/configurations/participant-types.php';
    require __DIR__.'/configurations/meeting-types.php';
    require __DIR__.'/configurations/event-types.php';

    /*
    |--------------------------------------------------------------------------
    | Misc
    |--------------------------------------------------------------------------
    */
    //require __DIR__.'/communications.php';
    //require __DIR__.'/reports.php';
    //require __DIR__.'/audit.php';
});