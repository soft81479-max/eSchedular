<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MyEvents\OrganizationsController;

Route::middleware(['event.access','permission:my-events.view',])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Meetings
    |--------------------------------------------------------------------------
    */
    Route::get('/{event}/organizations',[OrganizationsController::class, 'index'])->name('organizations.index');
});