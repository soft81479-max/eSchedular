<?php

use App\Http\Controllers\EventManagement\Workspace\TrackController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('tracks')
     ->name('tracks.')
     ->group(function () {
          /*
          |--------------------------------------------------------------------------
          | Track Workspace
          |--------------------------------------------------------------------------
          */
          Route::controller(TrackController::class)->group(function () {
               /*
               |--------------------------------------------------------------------------
               | View
               |--------------------------------------------------------------------------
               */
               Route::middleware('permission:events.view')->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/datatable', 'datatable')->name('datatable');
               });

               /*
               |--------------------------------------------------------------------------
               | Create
               |--------------------------------------------------------------------------
               */
               Route::middleware('permission:events.create')->group(function () {
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
               });

               /*
               |--------------------------------------------------------------------------
               | Update
               |--------------------------------------------------------------------------
               */
               Route::middleware('permission:events.edit')->group(function () {
                    Route::get('/{track}/edit', 'edit')->name('edit');
                    Route::put('/{track}', 'update')->name('update');
               });

               /*
               |--------------------------------------------------------------------------
               | Delete
               |--------------------------------------------------------------------------
               */
               Route::middleware('permission:events.delete')->group(function () {
                    Route::delete('/{track}', 'destroy')->name('destroy');
               });
          });
     });