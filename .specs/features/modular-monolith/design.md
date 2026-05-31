# Design: Modular Monolith Foundation + Example Module

> For spec `.specs/features/modular-monolith/spec.md`. Scope: Large.

## Architecture overview

Two decompositions on different axes, joined at the Inertia boundary:

```
┌─────────────────────────── kappy ───────────────────────────┐
│                                                              │
│  app/                      ← SHARED CORE (backend)           │
│   ├─ Actions/Fortify, Concerns, Http/{Controllers,          │
│   │   Middleware,Requests}, Models/User, Providers           │
│                                                              │
│  app-modules/              ← DOMAIN MODULES (vertical slices)│
│   └─ catalog/                                                │
│       ├─ composer.json     (path pkg, auto-discovered SP)    │
│       ├─ src/                                                │
│       │   ├─ Providers/CatalogServiceProvider.php            │
│       │   ├─ Models/Product.php                              │
│       │   └─ Http/Controllers/ProductController.php          │
│       ├─ routes/catalog-routes.php                           │
│       ├─ database/{migrations,factories,seeders}/            │
│       ├─ resources/js/pages/index.tsx   ← co-located UI      │
│       └─ tests/Feature/ProductTest.php                       │
│                                                              │
│  resources/js/             ← GLOBAL SHARED LAYER (frontend)  │
│   ├─ app.tsx               (manual Module::Page resolver)    │
│   ├─ components/ui, hooks, lib, layouts, types               │
│   └─ pages/                (root/core pages: dashboard,…)    │
│                                                              │
└──────────────────────────────────────────────────────────────┘

Inertia boundary:  Catalog\ProductController
                      → Inertia::render('catalog::index', $props)
                      → resolver maps 'catalog::index'
                      → app-modules/catalog/resources/js/pages/index.tsx
```

**Import direction (enforced):** module pages → `@/` shared layer (down only).
Modules never import each other's frontend; shared never imports a module.

## Component 1 — `internachi/modular` engine

- **Install:** `composer require internachi/modular`, then
  `php artisan vendor:publish --tag=modular-config`.
- **Configure** `config/app-modules.php`: `modules_namespace => 'Modules'`,
  `modules_directory => 'app-modules'`, `tests_base => 'Tests\TestCase'`.
- **Scaffold:** `php artisan make:module catalog --no-interaction` then
  `composer update modules/catalog`.
- **What it wires automatically** (via the package's `ModularServiceProvider`, no per-module
  boilerplate): routes (`app-modules/*/routes/*-routes.php`), migrations
  (`database/migrations`), factories (`Database\Factories` namespace), views, Blade
  components. The generated module `CatalogServiceProvider` stays nearly empty.
- **Root `composer.json` mutation** (done by `make:module`):
  ```jsonc
  "repositories": [ { "type": "path", "url": "app-modules/*", "options": { "symlink": true } } ],
  "require": { "modules/catalog": "*" }
  ```
- **Module `composer.json`** PSR-4: `Modules\Catalog\` → `src/`, plus `Tests\`,
  `Database\Factories\`, `Database\Seeders\`; provider auto-discovered via
  `extra.laravel.providers`.

## Component 2 — Example module `catalog`

Generated with `--module=catalog` flags on standard makers:

- `php artisan make:model Product --module=catalog -mf`
  → `src/Models/Product.php`, `database/migrations/*_create_products_table.php`,
  `database/factories/ProductFactory.php`.
- `php artisan make:controller ProductController --module=catalog`
  → `src/Http/Controllers/ProductController.php`.
- `routes/catalog-routes.php`:
  ```php
  use Illuminate\Support\Facades\Route;
  use Modules\Catalog\Http\Controllers\ProductController;

  Route::middleware(['web'])->group(function (): void {
      Route::get('/catalog', [ProductController::class, 'index'])->name('catalog.index');
  });
  ```
- `ProductController::index()` returns
  `Inertia::render('catalog::index', ['products' => Product::query()->latest()->get()])`.
- `Product` model: typed, `HasFactory`, `$fillable = ['name', 'price']` (price stored in cents).

## Component 3 — Inertia page resolution (Option B)

`@inertiajs/vite` only auto-resolves `resources/js/pages/**`. We replace it with an
explicit `resolve` in `createInertiaApp` (plugin detects `resolve` and skips injection;
SSR wrap is unaffected). Pattern (avosalmon / igeek precedent):

```tsx
// resources/js/app.tsx
import { createInertiaApp } from '@inertiajs/react';

const rootPages = import.meta.glob('./pages/**/*.tsx');
const modulePages = import.meta.glob('../../app-modules/*/resources/js/pages/**/*.tsx');

createInertiaApp({
    // ...title, layout, withApp, progress unchanged...
    resolve: (name) => {
        if (name.includes('::')) {
            const [module, page] = name.split('::');          // 'catalog::index'
            const path = `../../app-modules/${module}/resources/js/pages/${page}.tsx`;
            const loader = modulePages[path];
            if (!loader) throw new Error(`Inertia module page not found: ${path}`);
            return loader();
        }
        const path = `./pages/${name}.tsx`;                    // 'dashboard', 'auth/login'
        const loader = rootPages[path];
        if (!loader) throw new Error(`Inertia page not found: ${path}`);
        return loader();
    },
});
```

Notes:
- Glob args MUST be string literals (Vite static-analyzes them); globs are relative to `app.tsx`.
- `layout` switch keeps working for root names; module pages set their own layout via
  `Page.layout` or import `@/layouts/*` directly.
- Module token = the kebab module directory name (`catalog`); page file = lowercase
  (`index.tsx`) per repo convention.

## Component 4 — Server-side test discovery

`config/inertia.php` `pages.paths` (test-only FileViewFinder) gains module dirs so
`assertInertia()->component('catalog::index')` resolves. Because the component name uses
`::`, map it to a path; simplest robust approach — add module page roots and let the finder
resolve nested names, or register a small resolver. Concrete approach in tasks:

```php
'paths' => [
    resource_path('js/pages'),
    ...glob(base_path('app-modules/*/resources/js/pages')) ?: [],
],
```

(For `::`-named components, the test asserts the component string and—if `ensure_pages_exist`
trips on the `::`—we either set `ensure_pages_exist` per-test or assert with `shouldExist:false`.
Tasks include a spike to confirm the exact behavior before locking the approach.)

## Component 5 — Pest discovery for modules

- `php artisan modules:sync` appends to `phpunit.xml`:
  ```xml
  <testsuite name="Modules">
      <directory suffix="Test.php">./app-modules/*/tests</directory>
  </testsuite>
  ```
- `tests/Pest.php` extends bindings to module tests:
  ```php
  uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class)
      ->in(__DIR__.'/../app-modules');
  ```

## Component 6 — Boundary enforcement (eslint-plugin-boundaries)

`npm i -D eslint-plugin-boundaries`. In `eslint.config.js` (flat) declare element types from
paths and an allow-matrix:

- `shared` → `resources/js/**` (excluding nothing special).
- `module` → `app-modules/*/resources/js/**`, captured by module name.

Rules:
- `module` may import `shared` and **its own** `module` files; may NOT import a *different*
  `module`.
- `shared` may NOT import any `module`.

`npm run lint` must pass with these rules active.

## Component 7 — Tailwind v4 source scanning

Add to `resources/css/app.css`: `@source "../../app-modules";` so module `.tsx` classes are
detected (v4 ignores paths outside the CSS file's tree unless declared).

## Risks & mitigations

| Risk | Mitigation |
|---|---|
| `assertInertia` chokes on `::` component names | Spike (T-09) confirms behavior; fall back to slash names or `shouldExist:false` |
| Replacing auto-resolver breaks existing pages/SSR | Keep root-page branch identical; verify `npm run build:ssr` + existing tests |
| Tailwind purges module classes | `@source` directive (T-11) + visual check |
| `composer update` symlink issues | Document `make:module` → `composer update modules/<x>` two-step in README |
| Future module pages forget convention | README + boundaries lint + the resolver throws a clear error |

## Reuse

- Existing `@/components/ui/*`, `@/layouts/app-layout`, `@/hooks/*` — module page reuses them.
- Existing `Tests\TestCase` + Fortify helpers — module tests extend the same base.
- Wayfinder typed routes — module routes get typed functions on `wayfinder:generate`.
