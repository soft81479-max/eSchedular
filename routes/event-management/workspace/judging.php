<?php

use App\Http\Controllers\EventManagement\Workspace\JudgingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('judging')
     ->name('judging.')
     ->controller(JudgingController::class)
     ->group(function () {

          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', 'index')->name('index');
          });
     });