<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MyEvents\MatchmakingController;

Route::middleware([
     'event.access',
     'permission:my-events.view',
])->group(function () {

     /*
     |--------------------------------------------------------------------------
     | Matchmaking
     |--------------------------------------------------------------------------
     */
     Route::prefix('{event}/matchmaking')
          ->name('matchmaking.')
          ->group(function () {

               /*
               |--------------------------------------------------------------------------
               | Workspace
               |--------------------------------------------------------------------------
               */
               Route::get('/', [MatchmakingController::class, 'index'])->name('index');

               /*
               |--------------------------------------------------------------------------
               | Datatable
               |--------------------------------------------------------------------------
               */
               Route::post('/datatable', [MatchmakingController::class, 'datatable'])->name('datatable');

               /*
               |--------------------------------------------------------------------------
               | Representative Profile
               |--------------------------------------------------------------------------
               */
               Route::get('/{participant}', [MatchmakingController::class, 'profile'])->name('profile');

               /*
               |--------------------------------------------------------------------------
               | Matchmaking
               |--------------------------------------------------------------------------
               */
               Route::get('/{participant}/request',[MatchmakingController::class, 'create'])->name('create');
               Route::post('/request',[MatchmakingController::class, 'store'])->name('store');

               /*
               |--------------------------------------------------------------------------
               | Actions
               |--------------------------------------------------------------------------
               */
               Route::post('/{meetingRequest}/accept',[MatchmakingController::class, 'accept'])->name('accept');
               Route::post('/{meetingRequest}/reject',[MatchmakingController::class, 'reject'])->name('reject');
               Route::post('/{meetingRequest}/cancel',[MatchmakingController::class, 'cancel'])->name('cancel');

               /*
               |--------------------------------------------------------------------------
               | Profile / Details
               |--------------------------------------------------------------------------
               */
               Route::get('/{meetingRequest}',[MatchmakingController::class, 'show'])->name('show');
          });
});