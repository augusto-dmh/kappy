<?php

use Illuminate\Support\Facades\Route;
use Modules\GitHubApp\Http\Controllers\GithubWebhookController;

/*
 * Server-to-server webhook receiver. Declared OUTSIDE the Inertia `web` group
 * so it carries no session/CSRF/Inertia middleware (GitHub is not a browser).
 * A lenient per-IP throttle caps abuse on this public, signature-only endpoint
 * without dropping GitHub's legitimate (and re-delivered) bursts.
 */
Route::post('/webhooks/github', GithubWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.github');
