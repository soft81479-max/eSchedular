<?php

use App\Http\Controllers\MyEvents\MeetingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Meeting Workspace
|--------------------------------------------------------------------------
*/
Route::middleware(['auth','event.access',])
     ->prefix('{event}/meetings')
     ->controller(MeetingController::class)
     ->group(function () {
          /*
          |--------------------------------------------------------------------------
          | View
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:my-events.view')
               ->name('meetings.')
               ->group(function () {
                    /*
                    |--------------------------------------------------------------------------
                    | Index
                    |--------------------------------------------------------------------------
                    */
                    Route::get('/', 'index')->name('index');

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
                    Route::post('/datatable','meetingsDatatable')->name('datatable');

                    /*
                    |--------------------------------------------------------------------------
                    | Participant Profile
                    |--------------------------------------------------------------------------
                    */
                    Route::get('/{participant}/profile','profile')->name('profile');

                    /*
                    |--------------------------------------------------------------------------
                    | Show Meeting
                    |--------------------------------------------------------------------------
                    |
                    | Keep dynamic meeting route after all fixed routes.
                    |--------------------------------------------------------------------------
                    */
                    Route::get('/{meeting}','show')->name('show');
               });
     });