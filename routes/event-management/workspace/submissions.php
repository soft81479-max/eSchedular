<?php

use App\Http\Controllers\EventManagement\Workspace\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('submissions')
     ->name('submissions.')
     ->controller(SubmissionController::class)
     ->group(function () {

          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', 'index')->name('index');
          });
     });