<?php

use App\Http\Controllers\EventManagement\Workspace\PitchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('pitches')
     ->name('pitches.')
     ->controller(PitchController::class)
     ->group(function () {

          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', 'index')->name('index');
          });
     });