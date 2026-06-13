<?php

namespace Modules\GitHubApp\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\GitHubApp\Contracts\ScmDriver;
use Modules\GitHubApp\Scm\GitHubScmDriver;

class GitHubAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ScmDriver::class, GitHubScmDriver::class);
    }

    public function boot(): void {}
}
