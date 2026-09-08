<?php

use App\Http\Controllers\EventManagement\Workspace\CandidateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('candidates')
     ->name('candidates.')
     ->controller(CandidateController::class)
     ->group(function () {

          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', 'index')->name('index');
          });
     });