# STACK — kappy

> Brownfield snapshot relevant to the modular-monolith conversion. Captured 2026-05-30.

## Backend

- **PHP** 8.3 · **Laravel** 13.7 (Laravel 11+ bootstrap style: `bootstrap/app.php` + `bootstrap/providers.php`).
- **Auth:** `laravel/fortify` ^1.37 — registration, password reset, email verification, 2FA. Auth UI rendered via Inertia from `FortifyServiceProvider`. Custom Fortify actions in `app/Actions/Fortify/`.
- **Routing/typed FE:** `laravel/wayfinder` ^0.1 (typed route/controller functions for the frontend).
- **Inertia:** `inertiajs/inertia-laravel` ^3 (server) + `@inertiajs/react` ^3 (client).
- **Autoload (root `composer.json`):** `App\` → `app/`, `Database\Factories\`, `Database\Seeders\`, dev `Tests\` → `tests/`. No `repositories`, no module autoloading yet.
- **Providers (`bootstrap/providers.php`):** `AppServiceProvider`, `FortifyServiceProvider`.

## Frontend

- **React** 19.2 + **Inertia** 3 (React adapter) · **TypeScript** 5.7 · **Vite** 8.
- **Vite plugins:** `laravel-vite-plugin`, `@inertiajs/vite` (`inertia()`), `@vitejs/plugin-react` (React Compiler babel plugin), `@tailwindcss/vite`, `@laravel/vite-plugin-wayfinder`.
- **Page resolution:** `app.tsx` calls `createInertiaApp` with **no `resolve`** → `@inertiajs/vite` auto-injects a resolver globbing `./pages/**` + `./Pages/**` only. **No multi-directory option exists** (verified from plugin source v3.0.3).
- **Pages dir:** `resources/js/pages` (lowercase/kebab file naming: `dashboard.tsx`, `auth/login.tsx`, `settings/*`).
- **Shared UI (the de-facto `shared` layer):** `resources/js/components/ui/` (Radix-based kit), `resources/js/hooks/`, `resources/js/lib/`, `resources/js/layouts/`, `resources/js/types/`.
- **Alias:** `@/` → `resources/js/` (lets any file import the shared layer regardless of physical location).
- **Styling:** Tailwind CSS v4 via `@tailwindcss/vite`.
- **Lint:** ESLint v9 (flat config `eslint.config.js`) + Prettier 3.

## Testing

- **Pest** 4.7 (+ `pest-plugin-laravel`). `phpunit.xml` has two suites: `Unit` (`tests/Unit`), `Feature` (`tests/Feature`).
- `tests/Pest.php`: `pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature')`.
- DB: SQLite `:memory:` in tests. `tests/TestCase.php` adds `skipUnlessFortifyHas()`.
- 13 feature tests (auth lifecycle, settings, dashboard) + placeholders. All green at start.

## Conventions to honor

- Use `php artisan make:*` (with `--no-interaction`) for new files.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes.
- Curly braces always; constructor property promotion; explicit return types/typehints; PHPDoc over inline comments.
- Every change must be covered by a Pest test.
