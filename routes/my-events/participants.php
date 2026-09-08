<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MyEvents\ParticipantController;

Route::middleware(['event.access','permission:my-events.view',])->group(function () {
     /*
     |--------------------------------------------------------------------------
     | Participants
     |--------------------------------------------------------------------------
     */
    Route::get('/{event}/participants',[ParticipantController::class, 'index'])->name('participants.index');
});