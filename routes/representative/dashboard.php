<?php

use App\Http\Controllers\Representative\DashboardController;
use Illuminate\Support\Facades\Route;

Route::controller(DashboardController::class)->group(function () {

    Route::get(
        '/events/{event}',
        'index'
    )->name('dashboard');

});