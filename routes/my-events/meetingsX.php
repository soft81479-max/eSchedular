<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MyEvents\MeetingsController;

Route::middleware(['event.access','permission:my-events.view',])->group(function () {
     /*
     |--------------------------------------------------------------------------
     | My Meetings
     |--------------------------------------------------------------------------
     */

     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     */
     Route::get('/{event}/meetings',[MeetingsController::class, 'index'])->name('meetings.index');

      /*
     |--------------------------------------------------------------------------
     | Opportunities Datatable
     |--------------------------------------------------------------------------
     */
     Route::post('/{event}/meetings/datatable',[MeetingsController::class, 'opportunitiesDatatable'])->name('meetings.datatable');

     /*
     |--------------------------------------------------------------------------
     | Meetings Datatable
     |--------------------------------------------------------------------------
     */
     Route::post('/{event}/meetings/datatable',[MeetingsController::class, 'datatable'])->name('meetings.datatable');
     

     /*
     |--------------------------------------------------------------------------
     | Show
     |--------------------------------------------------------------------------
     */
     Route::get('/{event}/meetings/{meeting}',[MeetingsController::class, 'show'])->name('meetings.show');

     /*
     |--------------------------------------------------------------------------
     | Cancel Meeting
     |--------------------------------------------------------------------------
     */
     Route::post('/{event}/meetings/{meeting}/cancel',[MeetingsController::class, 'cancel'])->name('meetings.cancel');

     /*
     |--------------------------------------------------------------------------
     | Complete Meeting
     |--------------------------------------------------------------------------
     */
     Route::post('/{event}/meetings/{meeting}/complete',[MeetingsController::class, 'complete'])->name('meetings.complete');

     /*
     |--------------------------------------------------------------------------
     | No Show
     |--------------------------------------------------------------------------
     */
     Route::post('/{event}/meetings/{meeting}/no-show',[MeetingsController::class, 'noShow'])->name('meetings.no-show');
});