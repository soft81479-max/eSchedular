<?php

use App\Http\Controllers\EventManagement\Workspace\ProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('projects')
     ->name('projects.')
     ->controller(ProjectController::class)
     ->group(function () {

          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', 'index')->name('index');
          });
     });