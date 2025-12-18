<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

// API routes need 'web' middleware for session-based authentication
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('profile', [ProfileController::class, 'show'])->name('api.profile');
    Route::get('orders', [OrderController::class, 'index'])->name('api.orders.index');
    Route::post('orders', [OrderController::class, 'store'])->name('api.orders.store');
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('api.orders.cancel');
});

