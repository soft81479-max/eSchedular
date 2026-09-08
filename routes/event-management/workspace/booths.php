<?php

use App\Http\Controllers\EventManagement\Workspace\BoothController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('booths')
     ->name('booths.')
     ->controller(BoothController::class)
     ->group(function () {
          /*
          |--------------------------------------------------------------------------
          | View
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:organizations.view')->group(function () {
               Route::get('/', 'index')->name('index');
               Route::post('/datatable', 'datatable')->name('datatable');
          });          

          /*
          |--------------------------------------------------------------------------
          | Create
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:booths.create')->group(function () {
               Route::get('/generate','createGenerateForm')->name('createGenerateForm');
               Route::post('/generate','storeGenerate')->name('storeGenerate.store');
          });

          /*
          |--------------------------------------------------------------------------
          | Edit
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:booths.edit')->group(function () {
               Route::get('/{booth}/edit', 'edit')->name('edit');
               Route::put('/{booth}', 'update')->name('update');

               Route::get('/{booth}/assign','assign')->name('assign');
               Route::put('/{booth}/assign','assignUpdate')->name('assign.update');
          });

          /*
          |--------------------------------------------------------------------------
          | Release
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:booths.delete')->group(function () {
               Route::delete('/{booth}/release', 'release')->name('release');
          });
     });