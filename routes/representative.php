<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Representative Workspace
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'representative',
])->prefix('representative')
 ->name('representative.')
 ->group(function () {

    require __DIR__.'/representative/dashboard.php';

    //require __DIR__.'/representative/availability.php';
    //require __DIR__.'/representative/matchmaking.php';
    //require __DIR__.'/representative/meeting_requests.php';
    //require __DIR__.'/representative/meetings.php';
    //require __DIR__.'/representative/schedule.php';
    //require __DIR__.'/representative/participants.php';
    //require __DIR__.'/representative/organizations.php';
    //require __DIR__.'/representative/sponsors.php';
    //require __DIR__.'/representative/profile.php';
});