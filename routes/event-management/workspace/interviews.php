<?php

use App\Http\Controllers\EventManagement\Workspace\InterviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('interviews')
     ->name('interviews.')
     ->controller(InterviewController::class)
     ->group(function () {

          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', 'index')->name('index');
          });
     });