<?php

use App\Http\Controllers\Organizations\OrganizationController;
use App\Http\Controllers\Organizations\OrganizationUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('organizations')
    ->name('organizations.')
    ->middleware('permission:organizations.view')
    ->controller(OrganizationController::class)
    ->group(function () {
     /*
     |--------------------------------------------------------------------------
     | View
     |--------------------------------------------------------------------------
     */
     Route::middleware('permission:organizations.view')
          ->group(function () {
               Route::get('/', 'index')->name('index');
               Route::post('/datatable', 'datatable')->name('datatable');
          });

     /*
     |--------------------------------------------------------------------------
     | Export
     |--------------------------------------------------------------------------
     */
     Route::get('/export', 'export')
          ->middleware('permission:organizations.export')
          ->name('export');

     /*
     |--------------------------------------------------------------------------
     | Create
     |--------------------------------------------------------------------------
     */
     Route::middleware('permission:organizations.create')
          ->group(function () {
               Route::get('/create', 'create')->name('create');
               Route::post('/', 'store')->name('store');
          });

     /*
     |--------------------------------------------------------------------------
     | Edit
     |--------------------------------------------------------------------------
     */
     Route::middleware('permission:organizations.edit')
          ->group(function () {
               Route::get('/{organization}/edit', 'edit')->name('edit');
               Route::put('/{organization}', 'update')->name('update');
               Route::post('/{organization}/activate', 'activate')->name('activate');
               Route::post('/{organization}/deactivate', 'deactivate')->name('deactivate');
          });

     /*
     |--------------------------------------------------------------------------
     | Approval
     |--------------------------------------------------------------------------
     */
     Route::middleware('permission:organizations.approve')
          ->group(function () {
               Route::post('/{organization}/verify', 'verify')->name('verify');
               Route::post('/{organization}/unverify', 'unverify')->name('unverify');
          });
     /*
     |--------------------------------------------------------------------------
     | Delete
     |--------------------------------------------------------------------------
     */
     Route::middleware('permission:organizations.delete')
          ->group(function () {
               Route::delete('/{organization}', 'destroy')->name('destroy');
          });
     /*
     |--------------------------------------------------------------------------
     | Show
     |--------------------------------------------------------------------------
     */
     Route::middleware('permission:organizations.view')
          ->group(function () {
               Route::get('/{organization}', 'show')->name('show');
          });


     /*
     |--------------------------------------------------------------------------
     | Organization Users
     |--------------------------------------------------------------------------
     */
     Route::prefix('{organization}/users')
          ->name('users.')
          ->controller(OrganizationUserController::class)
          ->group(function () {
               Route::middleware('permission:organizations_users.view')->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/datatable', 'datatable')->name('datatable');
                    //Route::get('/{organizationUser}', 'show')->name('show');
               });

               Route::middleware('permission:organizations_users.create')->group(function () {
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
               });

               Route::middleware('permission:organizations_users.edit')->group(function () {
                    Route::get('/{organizationUser}/edit', 'edit')->name('edit');
                    Route::put('/{organizationUser}', 'update')->name('update');

                    Route::post('/{organizationUser}/activate', 'activate')->name('activate');
                    Route::post('/{organizationUser}/deactivate', 'deactivate')->name('deactivate');
                    Route::post('/{organizationUser}/make-admin', 'makeAdmin')->name('make-admin');
               });

               Route::middleware('permission:organizations_users.delete')->group(function () {
                    Route::delete('/{organizationUser}', 'destroy')->name('destroy');
               });
               Route::middleware('permission:organizations_users.view')->group(function () {
                    Route::get('/{organizationUser}', 'show')->name('show');
               });
          });
});