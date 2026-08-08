<?php

namespace Modules\Review\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Review\Contracts\ReviewDispatcher;
use Modules\Review\Contracts\Reviewer;
use Modules\Review\Reviewer\LaravelAiReviewer;
use Modules\Review\Services\EloquentReviewDispatcher;

class ReviewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Reviewer::class, LaravelAiReviewer::class);
        $this->app->bind(ReviewDispatcher::class, EloquentReviewDispatcher::class);
    }

    public function boot(): void {}
}
