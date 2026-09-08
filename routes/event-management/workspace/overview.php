<?php

use App\Http\Controllers\EventManagement\Workspace\OverviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access'])
     ->prefix('overview')
     ->name('overview.')
     ->controller(OverviewController::class)
     ->group(function () {
          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', 'index')->name('index');
          });

          Route::middleware('permission:events.edit')->group(function () {
               /*
               |--------------------------------------------------------------------------
               | Event Configuration
               |--------------------------------------------------------------------------
               */
               Route::get('/configuration/edit', 'editConfiguration')->name('configuration.edit');
               Route::put('/configuration', 'updateConfiguration')->name('configuration.update');

               /*
               |--------------------------------------------------------------------------
               | Participant Ecosystem
               |--------------------------------------------------------------------------
               */
               Route::get('/participant-ecosystem/edit', 'editParticipantEcosystem')->name('participant-ecosystem.edit');
               Route::put('/participant-ecosystem', 'updateParticipantEcosystem')->name('participant-ecosystem.update');

               /*
               |--------------------------------------------------------------------------
               | Matching Rules
               |--------------------------------------------------------------------------
               */
               Route::get('/matching-rules/edit', 'editMatchingRules')->name('matching-rules.edit');
               Route::put('/matching-rules', 'updateMatchingRules')->name('matching-rules.update');
          });
     });