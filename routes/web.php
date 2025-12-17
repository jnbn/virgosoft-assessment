<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard - Orders & Wallet
    Route::get('dashboard', [\App\Http\Controllers\OrderController::class, 'index'])->name('dashboard');
    Route::get('orders', [\App\Http\Controllers\OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/create', [\App\Http\Controllers\OrderController::class, 'create'])->name('orders.create');
});

require __DIR__.'/settings.php';
