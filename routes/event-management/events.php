<?php

use App\Http\Controllers\EventManagement\EventController;

Route::middleware(['auth','event.access'])
     ->prefix('events')
     ->name('events.')
     ->group(function () {
          /*
          |--------------------------------------------------------------------------
          | Events CRUD
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', [EventController::class, 'index'])->name('index');
               Route::post('/datatable', [EventController::class, 'datatable'])->name('datatable');
          });

          Route::middleware('permission:events.create')->group(function () {
               Route::get('/create', [EventController::class, 'create'])->name('create');
               Route::post('/', [EventController::class, 'store'])->name('store');
          });

          Route::middleware('permission:events.edit')->group(function () {
               Route::get('/{event}/edit', [EventController::class, 'edit'])->name('edit');
               Route::put('/{event}', [EventController::class, 'update'])->name('update');
               
               Route::post('/{event}/publish', [EventController::class, 'publish'])->name('publish');
               Route::post('/{event}/close', [EventController::class, 'close'])->name('close');
          });

          /*
          |--------------------------------------------------------------------------
          | Archive
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:events.archive')->post('/{event}/archive', [EventController::class, 'archive'])->name('archive');

          /*
          |--------------------------------------------------------------------------
          | Delete
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:events.delete')->delete('/{event}', [EventController::class, 'destroy'])->name('destroy');

          /*
          |--------------------------------------------------------------------------
          | Event Wizard
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:events.edit')->prefix('wizard')->name('wizard.')->group(function () {
               /*
               |--------------------------------------------------------------------------
               | Event Type Template
               |--------------------------------------------------------------------------
               */
               Route::get('event-types/{eventType}/template', [EventController::class, 'template'])->name('template');

               /*
               |--------------------------------------------------------------------------
               | Participant Types
               |--------------------------------------------------------------------------
               */
               Route::get('participant-types/create', [EventController::class, 'createParticipantType'])->name('participant-types.create');
               Route::post('participant-types', [EventController::class, 'storeParticipantType'])->name('participant-types.store');

               Route::get('participant-types/{participantType}/edit', [EventController::class, 'editParticipantType'])->name('participant-types.edit');
               Route::put('participant-types/{participantType}', [EventController::class, 'updateParticipantType'])->name('participant-types.update');

               Route::delete('participant-types/{participantType}', [EventController::class, 'destroyParticipantType'])->name('participant-types.destroy');

               /*
               |--------------------------------------------------------------------------
               | Match Rules
               |--------------------------------------------------------------------------
               */
               Route::get('match-rules/{matchRule}/edit', [EventController::class, 'editMatchRule'])->name('match-rules.edit');
               Route::put('match-rules/{matchRule}', [EventController::class, 'updateMatchRule'])->name('match-rules.update');
          });
});
