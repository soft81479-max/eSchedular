<?php

use App\Http\Controllers\EventManagement\Workspace\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('products')
     ->name('products.')
     ->controller(ProductController::class)
     ->group(function () {

          Route::middleware('permission:events.view')->group(function () {
               Route::get('/', 'index')->name('index');
          });
     });