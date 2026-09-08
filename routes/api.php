<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LocationController;

Route::prefix('locations')->group(function () {
    Route::get('/countries', [LocationController::class, 'getCountries']);
    Route::get('/states/{country_id}', [LocationController::class, 'getStates']);
    Route::get('/cities/{state_id}', [LocationController::class, 'getCities']);
});