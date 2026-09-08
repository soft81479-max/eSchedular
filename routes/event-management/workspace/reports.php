<?php

use App\Http\Controllers\EventManagement\Workspace\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('reports')
     ->name('reports.')
     ->controller(ReportController::class)
     ->group(function () {

          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', 'index')->name('index');
          });

     });