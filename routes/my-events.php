<?php

use Illuminate\Support\Facades\Route;

Route::middleware([
     'auth',
])->prefix('my-events')
  ->name('my-events.')
  ->group(function () {

     /*
     |--------------------------------------------------------------------------
     | My Events
     |--------------------------------------------------------------------------
     */
     require __DIR__.'/my-events/index.php';

     /*
     |--------------------------------------------------------------------------
     | My Event Workspace
     |--------------------------------------------------------------------------
     */
     require __DIR__.'/my-events/overview.php';
     require __DIR__.'/my-events/availability.php';
     require __DIR__.'/my-events/matchmaking.php';
     require __DIR__.'/my-events/meeting-requests.php';
     require __DIR__.'/my-events/meetings.php';
     require __DIR__.'/my-events/schedule.php';
     require __DIR__.'/my-events/participants.php';
     require __DIR__.'/my-events/organizations.php';
     require __DIR__.'/my-events/profile.php';
});