<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MyEvents\ScheduleController;

Route::middleware([
    'event.access',
    'permission:my-events.view',
    ])->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Meetings
        |--------------------------------------------------------------------------
        */
        Route::get('/{event}/schedule',[ScheduleController::class, 'index'])->name('schedule.index');

        /*
        |--------------------------------------------------------------------------
        | Calendar
        |--------------------------------------------------------------------------
        */
        Route::get('/{event}/schedule/calendar',[ScheduleController::class, 'calendar'])->name('schedule.calendar');

        /*
        |--------------------------------------------------------------------------
        | Timeline
        |--------------------------------------------------------------------------
        */    
        Route::get('/{event}/schedule/timeline',[ScheduleController::class, 'timeline'])->name('schedule.timeline');

        /*
        |--------------------------------------------------------------------------
        | Show
        |--------------------------------------------------------------------------
        */
        Route::get('/{event}/schedule/{eventTimeSlot}',[ScheduleController::class, 'show'])->name('schedule.show');
});