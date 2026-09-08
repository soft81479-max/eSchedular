<?php

use App\Http\Controllers\Administration\MenuController;
use Illuminate\Support\Facades\Route;

Route::prefix('menus')
     ->name('menus.')
     ->controller(MenuController::class)
     ->group(function () {
          /*
          |--------------------------------------------------------------------------
          | View Menus
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:menus.view')
               ->group(function () {

                    Route::get('/', 'index')
                         ->name('index');

                    Route::post('/datatable', 'datatable')
                         ->name('datatable');
               });

          /*
          |--------------------------------------------------------------------------
          | Manage Menus
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:menus.manage')
               ->group(function () {

                    Route::get('/create', 'create')
                         ->name('create');

                    Route::post('/', 'store')
                         ->name('store');

                    Route::get('/{menu}/edit', 'edit')
                         ->name('edit');

                    Route::put('/{menu}', 'update')
                         ->name('update');
               });

          /*
          |--------------------------------------------------------------------------
          | View Menus
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:menus.view')
               ->group(function () {
                    Route::get('/{menu}', 'show')
                         ->name('show');
               });
     });