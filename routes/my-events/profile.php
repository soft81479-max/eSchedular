<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MyEvents\ProfileController;

Route::middleware([
     'event.access',
     'permission:my-events.view',
])->group(function () {

     /*
     |--------------------------------------------------------------------------
     | Meetings
     |--------------------------------------------------------------------------
     */
    Route::get(
        '/{event}/profile',
        [ProfileController::class, 'index']
    )->name('profile.index');
});