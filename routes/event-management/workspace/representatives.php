<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\EventManagement\Workspace\RepresentativeController;

/*
|--------------------------------------------------------------------------
| Event Representatives Workspace Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth','event.access',)
    ->prefix('organizations/{eventOrganization}/representatives')
    ->name('organizations.representatives.')
    ->controller(RepresentativeController::class)
    ->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Index
        |--------------------------------------------------------------------------
        */
        Route::get('/','index')->middleware('permission:representatives.view')->name('index');

        /*
        |--------------------------------------------------------------------------
        | Datatable
        |--------------------------------------------------------------------------
        */
        Route::post('/datatable','datatable')->middleware('permission:representatives.view')->name('datatable');

        /*
        |--------------------------------------------------------------------------
        | Create
        |--------------------------------------------------------------------------
        */
        Route::get('/create','create')->middleware('permission:representatives.create')->name('create');
        Route::post('/','store')->middleware('permission:representatives.create')->name('store');

        /*
        |--------------------------------------------------------------------------
        | Edit
        |--------------------------------------------------------------------------
        */
        Route::get('/{eventParticipant}/edit','edit')->middleware('permission:representatives.edit')->name('edit');
        Route::put('/{eventParticipant}','update')->middleware('permission:representatives.edit')->name('update');
        Route::post('/{eventParticipant}/send-access','sendAccess')->middleware('permission:representatives.edit')->name('send-access');
        Route::post('/{eventParticipant}/reset-access','resetAccess')->middleware('permission:representatives.edit')->name('reset-access');

        /*
        |--------------------------------------------------------------------------
        | Profile
        |--------------------------------------------------------------------------
        */
        Route::get('/{eventParticipant}/profile', 'profile')->middleware('permission:representatives.view')->name('profile');

        /*
        |--------------------------------------------------------------------------
        | Delete
        |--------------------------------------------------------------------------
        */
        Route::delete('/{eventParticipant}','destroy')->middleware('permission:representatives.delete')->name('destroy');
    });