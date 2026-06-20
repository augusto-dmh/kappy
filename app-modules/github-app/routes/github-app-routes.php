<?php

use Illuminate\Support\Facades\Route;
use Modules\GitHubApp\Http\Controllers\GithubWebhookController;
use Modules\GitHubApp\Http\Controllers\InstallCallbackController;
use Modules\GitHubApp\Http\Controllers\RepositoryController;

/*
 * Server-to-server webhook receiver. Declared OUTSIDE the Inertia `web` group
 * so it carries no session/CSRF/Inertia middleware (GitHub is not a browser).
 * A lenient per-IP throttle caps abuse on this public, signature-only endpoint
 * without dropping GitHub's legitimate (and re-delivered) bursts.
 */
Route::post('/webhooks/github', GithubWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.github');

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('/install/callback', InstallCallbackController::class)
        ->name('install.callback');

    Route::get('/repositories', [RepositoryController::class, 'index'])
        ->name('repositories.index');
});
