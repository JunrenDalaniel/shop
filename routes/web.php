<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
Route::get('/products', [ProductController::class, 'index'])
    ->name('products.index');