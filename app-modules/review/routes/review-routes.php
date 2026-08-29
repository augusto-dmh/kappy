<?php

use Illuminate\Support\Facades\Route;
use Modules\Review\Http\Controllers\ReviewController;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('/reviews', [ReviewController::class, 'index'])
        ->name('reviews.index');

    Route::get('/reviews/{review}', [ReviewController::class, 'show'])
        ->name('reviews.show');
});
