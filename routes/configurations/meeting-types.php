<?php

use App\Http\Controllers\Configurations\MeetingTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('meeting-types')
    ->name('meeting-types.')
    ->controller(MeetingTypeController::class)
    ->group(function () {

        Route::middleware('permission:meeting_types.view')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/datatable', 'datatable')->name('datatable');
            });

        Route::middleware('permission:meeting_types.create')
            ->group(function () {
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
            });

        Route::middleware('permission:meeting_types.edit')
            ->group(function () {
                Route::get('/{meetingType}/edit', 'edit')->name('edit');
                Route::put('/{meetingType}', 'update')->name('update');
            });

        Route::delete('/{meetingType}', 'destroy')
            ->middleware('permission:meeting_types.delete')
            ->name('destroy');
    });