<?php

use App\Http\Controllers\EventManagement\Workspace\AvailabilityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('organizations/{eventOrganization}/representatives/{eventParticipant}/availability')
     ->name('organizations.representatives.availability.')
     ->controller(AvailabilityController::class)
     ->group(function () {
          Route::middleware('permission:events.view')->group(function () {
               /*
               |--------------------------------------------------------------------------
               | Index
               |--------------------------------------------------------------------------
               */
               Route::get('/', 'index')->name('index');
          });

          /*
          |--------------------------------------------------------------------------
          | Edit
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:representatives.edit')->group(function () {
               /*
               |--------------------------------------------------------------------------
               | Toggle Availability
               |--------------------------------------------------------------------------
               */
               Route::post('/toggle', 'toggle')->name('toggle');
          });
     });