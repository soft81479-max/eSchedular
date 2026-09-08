<?php

use App\Http\Controllers\EventManagement\Workspace\StartupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('startups')
     ->name('startups.')
     ->controller(StartupController::class)
     ->group(function () {

          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', 'index')->name('index');
          });
     });