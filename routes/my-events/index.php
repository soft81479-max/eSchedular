<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MyEvents\MyEventsController;

Route::middleware('permission:my-events.view')->group(function () {

     /*
     |--------------------------------------------------------------------------
     | My Events
     |--------------------------------------------------------------------------
     */
     Route::get(
          '/',
          [MyEventsController::class, 'index']
     )->name('index');

});