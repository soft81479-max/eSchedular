<?php

use App\Http\Controllers\EventManagement\Workspace\FranchiseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('franchises')
     ->name('franchises.')
     ->controller(FranchiseController::class)
     ->group(function () {

          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', 'index')->name('index');
          });

     });