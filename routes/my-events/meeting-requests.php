<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MyEvents\MeetingRequestController;

Route::middleware([
     'event.access',
     'permission:my-events.view',
     ])->group(function () {

     /*
     |--------------------------------------------------------------------------
     | Meeting Requests
     |--------------------------------------------------------------------------
     */
     Route::prefix('{event}/meeting-requests')
          ->name('meeting-requests.')
          ->group(function () {
               /*
               |--------------------------------------------------------------------------
               | Workspace
               |--------------------------------------------------------------------------
               */
               Route::get('/',[MeetingRequestController::class, 'index'])->name('index');

               /*
               |--------------------------------------------------------------------------
               | Datatable
               |--------------------------------------------------------------------------
               */
               Route::post('/datatable',[MeetingRequestController::class, 'datatable'])->name('datatable');

               /*
               |--------------------------------------------------------------------------
               | Participant Profile
               |--------------------------------------------------------------------------
               */
               Route::get('/{participant}/profile',[MeetingRequestController::class, 'profile'])->name('profile');

               /*
               |--------------------------------------------------------------------------
               | Meeting Request
               |--------------------------------------------------------------------------
               */
               Route::get('/{participant}/request',[MeetingRequestController::class, 'create'])->name('create');
               Route::post('/request',[MeetingRequestController::class, 'store'])->name('store');

               /*
               |--------------------------------------------------------------------------
               | Actions
               |--------------------------------------------------------------------------
               */
               Route::post('/{meetingRequest}/accept',[MeetingRequestController::class, 'accept'])->name('accept');
               Route::post('/{meetingRequest}/reject',[MeetingRequestController::class, 'reject'])->name('reject');
               Route::post('/{meetingRequest}/cancel',[MeetingRequestController::class, 'cancel'])->name('cancel');

               /*
               |--------------------------------------------------------------------------
               | Profile / Details
               |--------------------------------------------------------------------------
               */
               Route::get('/{meetingRequest}',[MeetingRequestController::class, 'show'])->name('show');
          });
});