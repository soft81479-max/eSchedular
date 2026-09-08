<?php

use App\Http\Controllers\Administration\PermissionController;
use Illuminate\Support\Facades\Route;

Route::prefix('permissions')
    ->name('permissions.')
    ->controller(PermissionController::class)
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | View Permissions
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:permissions.view')
            ->group(function () {

                Route::get('/', 'index')
                    ->name('index');

                Route::post('/datatable', 'datatable')
                    ->name('datatable');
            });

        /*
        |--------------------------------------------------------------------------
        | Manage Permissions
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:permissions.manage')
            ->group(function () {

                Route::get('/create', 'create')
                    ->name('create');

                Route::post('/', 'store')
                    ->name('store');

                Route::get('/{permission}/edit', 'edit')
                    ->name('edit');

                Route::put('/{permission}', 'update')
                    ->name('update');
            });
            
            /*
          |--------------------------------------------------------------------------
          | View Permissions
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:permissions.view')
               ->group(function () {
                    Route::get('/{permission}', 'show')
                         ->name('show');
               });
    });