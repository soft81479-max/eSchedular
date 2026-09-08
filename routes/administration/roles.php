<?php

use App\Http\Controllers\Administration\RoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('roles')
    ->name('roles.')
    ->controller(RoleController::class)
    ->group(function () {
        /*
        |--------------------------------------------------------------------------
        | View Roles
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:roles.view')
            ->group(function () {

                Route::get('/', 'index')
                    ->name('index');

                Route::post('/datatable', 'datatable')
                    ->name('datatable');
            });

        /*
        |--------------------------------------------------------------------------
        | Manage Roles
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:roles.manage')
            ->group(function () {

                Route::get('/create', 'create')
                    ->name('create');

                Route::post('/', 'store')
                    ->name('store');

                Route::get('/{role}/edit', 'edit')
                    ->name('edit');

                Route::put('/{role}', 'update')
                    ->name('update');

                Route::get('/{role}/permissions', 'permissions')
                    ->name('permissions');

                Route::post('/{role}/permissions', 'updatePermissions')
                    ->name('permissions.update');

                Route::get('/{role}/menus', 'menus')
                    ->name('menus');

                Route::put('/{role}/menus', 'updateMenus')
                    ->name('menus.update');
            });
        /*
        |--------------------------------------------------------------------------
        | View Roles
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:roles.view')
            ->group(function () {
                Route::get('/{role}', 'show')
                    ->name('show');
            });
    });