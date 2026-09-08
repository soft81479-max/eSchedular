<?php

use App\Http\Controllers\Configurations\NetworkingModeController;
use Illuminate\Support\Facades\Route;

Route::prefix('networking-modes')
    ->name('networking-modes.')
    ->controller(NetworkingModeController::class)
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | View
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:networking_modes.view')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/datatable', 'datatable')->name('datatable');
            });

        /*
        |--------------------------------------------------------------------------
        | Create
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:networking_modes.create')
            ->group(function () {
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
            });

        /*
        |--------------------------------------------------------------------------
        | Edit
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:networking_modes.edit')
            ->group(function () {
                Route::get('/{networkingMode}/edit', 'edit')->name('edit');
                Route::put('/{networkingMode}', 'update')->name('update');
            });

        /*
        |--------------------------------------------------------------------------
        | Delete
        |--------------------------------------------------------------------------
        */
        Route::delete('/{networkingMode}', 'destroy')
            ->middleware('permission:networking_modes.delete')
            ->name('destroy');
    });