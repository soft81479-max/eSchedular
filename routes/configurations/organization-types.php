<?php

use App\Http\Controllers\Configurations\OrganizationTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('organization-types')
    ->name('organization-types.')
    ->controller(OrganizationTypeController::class)
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | View
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:organization_types.view')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/datatable', 'datatable')->name('datatable');
            });

        /*
        |--------------------------------------------------------------------------
        | Create
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:organization_types.create')
            ->group(function () {
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
            });

        /*
        |--------------------------------------------------------------------------
        | Edit
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:organization_types.edit')
            ->group(function () {
                Route::get('/{organizationType}/edit', 'edit')->name('edit');
                Route::put('/{organizationType}', 'update')->name('update');
            });

        /*
        |--------------------------------------------------------------------------
        | Delete
        |--------------------------------------------------------------------------
        */
        Route::delete('/{organizationType}', 'destroy')
            ->middleware('permission:organization_types.delete')
            ->name('destroy');
    });