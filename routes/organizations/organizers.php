<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Organizations\OrganizerController;
use App\Http\Controllers\Organizations\OrganizerUserController;

Route::prefix('organizers')
    ->name('organizers.')
    ->group(function () {

        Route::controller(OrganizerController::class)
            ->group(function () {
                /*
                |--------------------------------------------------------------------------
                | View
                |--------------------------------------------------------------------------
                */
                Route::middleware('permission:organizers.view')
                    ->group(function () {
                        Route::get('/', 'index')->name('index');
                        Route::post('/datatable', 'datatable')->name('datatable');
                    });

                /*
                |--------------------------------------------------------------------------
                | Create
                |--------------------------------------------------------------------------
                */
                Route::middleware('permission:organizers.create')
                    ->group(function () {
                        Route::get('/create', 'create')->name('create');
                        Route::post('/', 'store')->name('store');
                    });

                /*
                |--------------------------------------------------------------------------
                | Edit
                |--------------------------------------------------------------------------
                */
                Route::middleware('permission:organizers.edit')
                    ->group(function () {
                        Route::get('/{organizer}/edit', 'edit')->name('edit');
                        Route::put('/{organizer}', 'update')->name('update');
                    });

                /*
                |--------------------------------------------------------------------------
                | Delete
                |--------------------------------------------------------------------------
                */
                Route::middleware('permission:organizers.delete')
                    ->group(function () {
                        Route::delete('/{organizer}', 'destroy')->name('destroy');
                    });

                /*
                |--------------------------------------------------------------------------
                | Verification
                |--------------------------------------------------------------------------
                */
                Route::middleware('permission:organizers.approve')
                    ->group(function () {
                        Route::post('/{organizer}/verify', 'verify')->name('verify');
                        Route::post('/{organizer}/unverify', 'unverify')->name('unverify');
                        Route::post('/{organizer}/activate', 'activate')->name('activate');
                        Route::post('/{organizer}/deactivate', 'deactivate')->name('deactivate');
                        Route::post('/{organizer}/suspend', 'suspend')->name('suspend');
                    });

                /*
                |--------------------------------------------------------------------------
                | Export
                |--------------------------------------------------------------------------
                */
                Route::middleware('permission:organizers.export')
                    ->group(function () {
                        Route::get('/export', 'export')->name('export');
                    });

                /*
                |--------------------------------------------------------------------------
                | Shpw
                |--------------------------------------------------------------------------
                */
                Route::middleware('permission:organizers.view')
                    ->group(function () {
                        Route::get('/{organizer}', 'show')->name('show');
                    });
            });

        Route::prefix('{organizer}/users')
            ->name('users.')
            ->controller(OrganizerUserController::class)
            ->middleware('permission:organizers_users.manage')
            ->group(function () {
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');

                Route::get('/{organizerUser}/edit', 'edit')->name('edit');
                Route::put('/{organizerUser}', 'update')->name('update');

                Route::delete('/{organizerUser}', 'destroy')->name('destroy');

                Route::post('/{organizerUser}/activate', 'activate')->name('activate');
                Route::post('/{organizerUser}/deactivate', 'deactivate')->name('deactivate');

                Route::post('/{organizerUser}/make-primary', 'makePrimary')->name('make-primary');
            });
    });