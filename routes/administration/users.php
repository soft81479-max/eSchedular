<?php

use App\Http\Controllers\Administration\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('users')
    ->name('users.')
    ->controller(UserController::class)
    ->group(function () {
        // View Users
        Route::middleware('permission:users.view')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/datatable', 'datatable')->name('datatable');
            });

        // Create Users
        Route::middleware('permission:users.manage')
            ->group(function () {
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
            });

        // Edit Users
        Route::middleware('permission:users.manage')
            ->group(function () {
                Route::get('/{user}/edit', 'edit')->name('edit');
                Route::put('/{user}', 'update')->name('update');

                Route::patch('/{user}/status', 'toggleStatus')->name('status');

                Route::get('/{user}/reset-password','resetPasswordForm')
                    ->name('reset-password.form');
                Route::put('/{user}/reset-password','resetPassword')
                    ->name('reset-password');

                Route::post('/{user}/send-access-email', 'sendAccessEmail')
                    ->name('send-access-email');

                Route::get('/{user}/permissions', 'permissions')
                    ->name('permissions');

                Route::post('/{user}/permissions', 'updatePermissions')
                    ->name('permissions.update');

                Route::post('/{user}/force-logout', 'forceLogout')
                    ->name('force-logout');

                Route::post('/{user}/unlock', 'unlock')
                    ->name('unlock');
            });

        // View User
        Route::middleware('permission:users.view')
            ->group(function () {
                Route::get('/{user}', 'show')->name('show');
            });

        // Delete Users
        Route::middleware('permission:users.manage')
            ->group(function () {
                Route::delete('/{user}', 'destroy')->name('destroy');
            });
    });