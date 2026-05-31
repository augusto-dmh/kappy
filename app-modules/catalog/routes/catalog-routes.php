<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\ProductController;

Route::middleware(['web'])->group(function (): void {
    Route::get('/catalog', [ProductController::class, 'index'])->name('catalog.index');
});
