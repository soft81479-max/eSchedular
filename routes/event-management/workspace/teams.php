<?php

use App\Http\Controllers\EventManagement\Workspace\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('teams')
     ->name('teams.')
     ->controller(TeamController::class)
     ->group(function () {

          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', 'index')->name('index');
          });
     });