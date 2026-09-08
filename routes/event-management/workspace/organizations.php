<?php

use App\Http\Controllers\EventManagement\Workspace\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('organizations')
     ->name('organizations.')
     ->controller(OrganizationController::class)
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
          Route::middleware('permission:organizations.create')->group(function () {
               Route::get('/create', 'create')->name('create');
               Route::post('/', 'store')->name('store');
          });

          /*
          |--------------------------------------------------------------------------
          | Edit
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:organizations.edit')->group(function () {
               Route::get('/{eventOrganization}/edit', 'edit')->name('edit');
               Route::put('/{eventOrganization}', 'update')->name('update');
          });

          /*
          |--------------------------------------------------------------------------
          | Delete
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:organizations.delete')->group(function () {
               Route::delete('/{eventOrganization}', 'destroy')->name('destroy');
          });
     
          /*
          |--------------------------------------------------------------------------
          | Profile
          |--------------------------------------------------------------------------
          */
          Route::middleware('permission:organizations.view')->group(function () {
               Route::get('/{eventOrganization}/profile', 'profile')->name('profile');
          });
     });