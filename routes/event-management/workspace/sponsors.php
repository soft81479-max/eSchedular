<?php

use App\Http\Controllers\EventManagement\Workspace\SponsorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('sponsors')
     ->name('sponsors.')
     ->controller(SponsorController::class)
     ->group(function () {

          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', 'index')->name('index');
          });

     });