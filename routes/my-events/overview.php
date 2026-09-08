<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MyEvents\OverviewController;

Route::middleware([
     'event.access',
     'permission:my-events.view',
])->group(function () {

     /*
     |--------------------------------------------------------------------------
     | Overview
     |--------------------------------------------------------------------------
     */
     Route::get(
          '/{event}',
          [OverviewController::class, 'index']
     )->name('overview.index');

});