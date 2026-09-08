<?php

use App\Http\Controllers\Representative\SponsorController;
use Illuminate\Support\Facades\Route;

Route::prefix('events/{event}/sponsors')
    ->name('sponsors.')
    ->controller(SponsorController::class)
    ->group(function () {

        Route::get('/', 'index')->name('index');

    });