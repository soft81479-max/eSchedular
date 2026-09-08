<?php

use App\Http\Controllers\EventManagement\Workspace\MentorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('mentors')
     ->name('mentors.')
     ->controller(MentorController::class)
     ->group(function () {

          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', 'index')->name('index');
          });
    });