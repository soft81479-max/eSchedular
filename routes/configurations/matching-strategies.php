<?php

use App\Http\Controllers\Configurations\MatchingStrategyController;
use Illuminate\Support\Facades\Route;

Route::prefix('matching-strategies')
    ->name('matching-strategies.')
    ->controller(MatchingStrategyController::class)
    ->group(function () {

        Route::middleware('permission:matching_strategies.view')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/datatable', 'datatable')->name('datatable');
            });

        Route::middleware('permission:matching_strategies.create')
            ->group(function () {
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
            });

        Route::middleware('permission:matching_strategies.edit')
            ->group(function () {
                Route::get('/{matchingStrategy}/edit', 'edit')->name('edit');
                Route::put('/{matchingStrategy}', 'update')->name('update');
            });

        Route::delete('/{matchingStrategy}', 'destroy')
            ->middleware('permission:matching_strategies.delete')
            ->name('destroy');
    });