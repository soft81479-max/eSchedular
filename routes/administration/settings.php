<?php

use App\Http\Controllers\Administration\SettingController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings')
     ->name('settings.')
     ->controller(SettingController::class)
     ->group(function () {

          /*
          |--------------------------------------------------------------------------
          | View
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:settings.view')
               ->group(function () {

                    Route::get('/', 'index')
                         ->name('index');

                    Route::post('/datatable', 'datatable')
                         ->name('datatable');
               });

          /*
          |--------------------------------------------------------------------------
          | Manage
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:settings.manage')
               ->group(function () {

                    Route::get('/create', 'create')
                         ->name('create');

                    Route::post('/', 'store')
                         ->name('store');

                    Route::get('/{setting}/edit', 'edit')
                         ->name('edit');

                    Route::put('/{setting}', 'update')
                         ->name('update');
               });
          /*
          |--------------------------------------------------------------------------
          | Show
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:settings.view')
               ->group(function () {
                    Route::get('/{setting}', 'show')
                         ->name('show');
               });
     });