<?php

use App\Http\Controllers\EventManagement\Workspace\SessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('sessions')
     ->name('sessions.')
     ->group(function () {
          /*
          |--------------------------------------------------------------------------
          | Session Workspace
          |--------------------------------------------------------------------------
          */
          Route::controller(SessionController::class)->group(function () {
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
                    Route::get('/{session}/edit', 'edit')->name('edit');
                    Route::put('/{session}', 'update')->name('update');
               });

               /*
               |--------------------------------------------------------------------------
               | Delete
               |--------------------------------------------------------------------------
               */
               Route::middleware('permission:events.delete')->group(function () {
                    Route::delete('/{session}', 'destroy')->name('destroy');
               });
          });
     });