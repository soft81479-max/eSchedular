<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MyEvents\AvailabilityController;

Route::middleware([
     'event.access',
     'permission:my-events.view',
])->group(function () {

     /*
     |--------------------------------------------------------------------------
     | Availability
     |--------------------------------------------------------------------------
     */

     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     */
     Route::get(
          '/{event}/availability',
          [AvailabilityController::class, 'index']
     )->name('availability.index');

     /*
     |--------------------------------------------------------------------------
     | Calendar
     |--------------------------------------------------------------------------
     */
     Route::get(
          '/{event}/availability/calendar',
          [AvailabilityController::class, 'calendar']
     )->name('availability.calendar');

     /*
     |--------------------------------------------------------------------------
     | Slots
     |--------------------------------------------------------------------------
     */
     Route::get(
          '/{event}/availability/slots',
          [AvailabilityController::class, 'slots']
     )->name('availability.slots');

     /*
     |--------------------------------------------------------------------------
     | Show Slot
     |--------------------------------------------------------------------------
     */
     Route::get(
          '/{event}/availability/{eventTimeSlot}',
          [AvailabilityController::class, 'show']
     )->name('availability.show');

     /*
     |--------------------------------------------------------------------------
     | Update Slot
     |--------------------------------------------------------------------------
     */
     Route::put(
          '/{event}/availability/{eventTimeSlot}',
          [AvailabilityController::class, 'update']
     )->name('availability.update');

});