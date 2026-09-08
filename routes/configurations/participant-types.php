<?php

use App\Http\Controllers\Configurations\ParticipantTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('participant-types')
    ->name('participant-types.')
    ->controller(ParticipantTypeController::class)
    ->group(function () {
        /*
        |--------------------------------------------------------------------------
        | View
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:participant_types.view')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/datatable', 'datatable')->name('datatable');
            });

        /*
        |--------------------------------------------------------------------------
        | Create
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:participant_types.create')
            ->group(function () {
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
            });

        /*
        |--------------------------------------------------------------------------
        | Edit
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:participant_types.edit')
            ->group(function () {
                Route::get('/{participantType}/edit', 'edit')->name('edit');
                Route::put('/{participantType}', 'update')->name('update');
            });

        /*
        |--------------------------------------------------------------------------
        | Delete
        |--------------------------------------------------------------------------
        */
        Route::delete('/{participantType}', 'destroy')
            ->middleware('permission:participant_types.delete')
            ->name('destroy');
    });