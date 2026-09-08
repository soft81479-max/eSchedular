<?php

use App\Http\Controllers\EventManagement\Workspace\InstitutionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('institutions')
     ->name('institutions.')
     ->controller(InstitutionController::class)
     ->group(function () {

          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', 'index')->name('index');
          });
     });