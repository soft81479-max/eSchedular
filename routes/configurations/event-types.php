<?php

use App\Http\Controllers\Configurations\EventTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('event-types')
    ->name('event-types.')
    ->controller(EventTypeController::class)
    ->group(function () {

        Route::middleware('permission:event_types.view')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/datatable', 'datatable')->name('datatable');
            });

        Route::middleware('permission:event_types.create')
            ->group(function () {
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
            });

        Route::middleware('permission:event_types.edit')
            ->group(function () {
                Route::get('/{eventType}/edit', 'edit')->name('edit');
                Route::put('/{eventType}', 'update')->name('update');
            });

        Route::delete('/{eventType}', 'destroy')
            ->middleware('permission:event_types.delete')
            ->name('destroy');
    });