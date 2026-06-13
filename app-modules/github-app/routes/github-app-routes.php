<?php

use Illuminate\Support\Facades\Route;
use Modules\GitHubApp\Http\Controllers\GithubWebhookController;

/*
 * Server-to-server webhook receiver. Declared OUTSIDE the Inertia `web` group
 * so it carries no session/CSRF/Inertia middleware (GitHub is not a browser).
 */
Route::post('/webhooks/github', GithubWebhookController::class)
    ->name('webhooks.github');
