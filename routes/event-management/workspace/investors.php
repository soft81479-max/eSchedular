<?php

use App\Http\Controllers\EventManagement\Workspace\InvestorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('investors')
     ->name('investors.')
     ->controller(InvestorController::class)
     ->group(function () {

          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', 'index')->name('index');
          });
     });