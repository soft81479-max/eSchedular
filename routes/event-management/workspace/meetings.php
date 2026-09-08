<?php

use App\Http\Controllers\EventManagement\Workspace\MeetingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Meeting Workspace
|--------------------------------------------------------------------------
*/
Route::middleware(['auth','event.access',])
     ->prefix('meetings')
     ->controller(MeetingController::class)
     ->group(function () {
          /*
          |--------------------------------------------------------------------------
          | View
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:meetings.view')->name('meetings.')->group(function () {
               /*
               |--------------------------------------------------------------------------
               | Index
               |--------------------------------------------------------------------------
               */
               Route::get('/','index')->name('index');

               /*
               |--------------------------------------------------------------------------
               | Opportunities Datatable
               |--------------------------------------------------------------------------
               */
               Route::post('/opportunities/datatable','opportunitiesDatatable')->name('opportunities.datatable');

               /*
               |--------------------------------------------------------------------------
               | Meetings Datatable
               |--------------------------------------------------------------------------
               */
               Route::post('/datatable','datatable')->name('datatable');

               /*
               |--------------------------------------------------------------------------
               | Show
               |--------------------------------------------------------------------------
               |
               | Keep dynamic meeting route after all fixed routes.
               |--------------------------------------------------------------------------
               */
               Route::get('/{meeting}','show')->name('show');
          });

          /*
          |--------------------------------------------------------------------------
          | Create Meeting Request
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:meetings.create')->name('meetings.')->group(function () {
               /*
               |--------------------------------------------------------------------------
               | Create Direct Meeting Request
               |--------------------------------------------------------------------------
               */
               Route::get('/request/create','create')->name('create');

               /*
               |--------------------------------------------------------------------------
               | Store Direct Meeting Request
               |--------------------------------------------------------------------------
               */
               Route::post('/request','store')->name('store');
          });

          /*
          |--------------------------------------------------------------------------
          | Meeting Request Lifecycle
          |--------------------------------------------------------------------------
          |
          | Route names:
          |
          | events.meeting-requests.accept
          | events.meeting-requests.reject
          | events.meeting-requests.cancel
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:meetings.edit')->prefix('requests')->name('meeting-requests.')->group(function () {
               /*
               |--------------------------------------------------------------------------
               | Accept Meeting Request
               |--------------------------------------------------------------------------
               */
               Route::post('/{meetingRequest}/accept','accept')->name('accept');

               /*
               |--------------------------------------------------------------------------
               | Reject Meeting Request
               |--------------------------------------------------------------------------
               */
               Route::post('/{meetingRequest}/reject','reject')->name('reject');

               /*
               |--------------------------------------------------------------------------
               | Cancel Meeting Request
               |--------------------------------------------------------------------------
               */
               Route::post('/{meetingRequest}/cancel','cancelRequest')->name('cancel');
          });

          /*
          |--------------------------------------------------------------------------
          | Meeting Lifecycle
          |--------------------------------------------------------------------------
          |
          | Route names:
          |
          | events.meetings.cancel
          | events.meetings.complete
          | events.meetings.no-show
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:meetings.edit')->name('meetings.')->group(function () {
               /*
               |--------------------------------------------------------------------------
               | Cancel Meeting
               |--------------------------------------------------------------------------
               */
               Route::post('/{meeting}/cancel','cancel')->name('cancel');

               /*
               |--------------------------------------------------------------------------
               | Complete Meeting
               |--------------------------------------------------------------------------
               */
               Route::post('/{meeting}/complete','complete')->name('complete');

               /*
               |--------------------------------------------------------------------------
               | No Show
               |--------------------------------------------------------------------------
               */
               Route::post('/{meeting}/no-show','noShow')->name('no-show');
          });
     });