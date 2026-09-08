<?php

use App\Http\Controllers\EventManagement\Workspace\ParticipantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('participants')
     ->name('participants.')
     ->controller(ParticipantController::class)
     ->group(function () {
          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', 'index')->name('index');
          });
     });