<?php

namespace Modules\Identity\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Identity\Models\Account;
use Modules\Identity\Policies\AccountPolicy;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(Account::class, AccountPolicy::class);
    }
}
