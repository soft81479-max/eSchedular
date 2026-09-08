<?php

use App\Http\Controllers\EventManagement\Workspace\SpeakerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('speakers')
     ->name('speakers.')
     ->controller(SpeakerController::class)
     ->group(function () {
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
               Route::post('/organization-participants','organizationParticipants')->name('organization-participants');
          });

          /*
          |--------------------------------------------------------------------------
          | Update
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:events.edit')->group(function () {
               Route::get('/{speaker}/edit', 'edit')->name('edit');
               Route::put('/{speaker}', 'update')->name('update');
          });

          /*
          |--------------------------------------------------------------------------
          | Delete
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:events.delete')->group(function () {
               Route::delete('/{speaker}', 'destroy')->name('destroy');
          });
     });