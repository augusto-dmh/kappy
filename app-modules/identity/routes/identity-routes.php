<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\GithubLoginController;

Route::middleware(['web'])->group(function (): void {
    Route::get('/auth/github/redirect', [GithubLoginController::class, 'redirect'])
        ->name('auth.github.redirect');

    Route::get('/auth/github/callback', [GithubLoginController::class, 'callback'])
        ->name('auth.github.callback');
});
