<?php

namespace Modules\Review\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Review\Contracts\Reviewer;
use Modules\Review\Reviewer\LaravelAiReviewer;

class ReviewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Reviewer::class, LaravelAiReviewer::class);
    }

    public function boot(): void {}
}
