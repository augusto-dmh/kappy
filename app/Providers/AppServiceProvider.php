<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\FileViewFinder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerModuleAwareInertiaViewFinder();
    }

    /**
     * Rebind Inertia's page view-finder so `module::page` component names resolve
     * to co-located module pages (app-modules/<module>/resources/js/pages/<page>.tsx).
     *
     * Inertia's `ensure_pages_exist` testing check resolves `app('inertia.view-finder')`,
     * and a FileViewFinder treats the `::` in a name as a namespace hint. We register a
     * hint namespace per module so assertions like `->component('catalog::index')` verify
     * the real co-located file while `inertia.pages.paths` keeps serving root pages.
     */
    protected function registerModuleAwareInertiaViewFinder(): void
    {
        $this->app->singleton('inertia.view-finder', function ($app): FileViewFinder {
            $finder = new FileViewFinder(
                $app['files'],
                $app['config']->get('inertia.pages.paths'),
                $app['config']->get('inertia.pages.extensions'),
            );

            foreach (glob(base_path('app-modules/*/resources/js/pages')) ?: [] as $modulePagePath) {
                $finder->addNamespace(basename(dirname($modulePagePath, 3)), $modulePagePath);
            }

            return $finder;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
