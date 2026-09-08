<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\EventManagement\Workspace\ScheduleController;
use App\Http\Controllers\EventManagement\Workspace\TimeSlotController;

Route::prefix('schedules')
     ->name('schedules.')
     ->group(function () {
          /*
          |--------------------------------------------------------------------------
          | Schedule Workspace
          |--------------------------------------------------------------------------
          */
          Route::controller(ScheduleController::class)->group(function () {
               /*
               |--------------------------------------------------------------------------
               | View
               |--------------------------------------------------------------------------
               */
               Route::middleware('permission:schedules.view')->group(function () {
                    Route::get('/', 'index')->name('index');
               });

               /*
               |--------------------------------------------------------------------------
               | Create
               |--------------------------------------------------------------------------
               */
               Route::middleware('permission:schedules.create')->group(function () {
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
               });

               /*
               |--------------------------------------------------------------------------
               | Edit
               |--------------------------------------------------------------------------
               */
               Route::middleware('permission:schedules.edit')->group(function () {
                    Route::get('/{schedule}/edit', 'edit')->name('edit');
                    Route::put('/{schedule}', 'update')->name('update');
               });

               /*
               |--------------------------------------------------------------------------
               | Delete
               |--------------------------------------------------------------------------
               */
               Route::middleware('permission:schedules.delete')->group(function () {
                    Route::delete('/{schedule}', 'destroy')->name('destroy');
               });
          });

          /*
          |--------------------------------------------------------------------------
          | Time Slots
          |--------------------------------------------------------------------------
          */
          Route::prefix('{schedule}/slots')
               ->name('slots.')
               ->controller(TimeSlotController::class)
               ->group(function () {
                    /*
                    |--------------------------------------------------------------------------
                    | Generate
                    |--------------------------------------------------------------------------
                    */
                    Route::middleware('permission:schedules.create')->group(function () {
                         Route::get('/generate', 'createGenerate')->name('generate.create');
                         Route::post('/generate', 'storeGenerate')->name('generate.store');
                    });

                    /*
                    |--------------------------------------------------------------------------
                    | Regenerate
                    |--------------------------------------------------------------------------
                    */
                    Route::middleware('permission:schedules.edit')->group(function () {
                         Route::get('/regenerate', 'editGenerate')->name('generate.edit');
                         Route::put('/regenerate', 'updateGenerate')->name('generate.update');
                    });
               });
     });