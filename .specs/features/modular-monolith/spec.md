# Feature: Modular Monolith Foundation + Example Module

> Status: **Specified** · Scope: **Large** · Created 2026-05-30

## Summary

Convert the fresh Laravel 13 + React 19 + Inertia 3 app (`kappy`) into a modular
monolith using **`internachi/modular`** (modules in `app-modules/`, namespace `Modules\`),
following the philosophy of the Laracasts "Modular Laravel" course. Existing Fortify auth
and Settings code stays in `app/` as shared core. Wire the full stack — backend modules,
co-located Inertia React pages (Option B: domain-first + global shared layer), Pest test
discovery, boundary enforcement — and prove it with one end-to-end example module (`catalog`).

## Goals

- A repeatable, documented way to create a new domain module that owns its PHP, routes,
  migrations, factories, tests, and React pages.
- Frontend domain co-location with a single global `shared` layer and **enforced
  unidirectional imports** (modules → shared, never module → module).
- Zero regressions: the existing auth/settings/dashboard suite stays green.

## Non-Goals

- Migrating existing auth/settings into modules (explicitly kept in `app/`).
- Adopting full Feature-Sliced Design or the `steiger` linter.
- Runtime enable/disable of modules (not supported by `internachi/modular`; not needed).
- Building real product/business features beyond the demonstrative `catalog` module.

## Requirements

### Backend / module engine

- **FR-1** — `internachi/modular` is installed and configured; `config/app-modules.php`
  is published with namespace `Modules` and directory `app-modules`.
- **FR-2** — `php artisan make:module catalog` produces a module under `app-modules/catalog`
  whose service provider is auto-discovered (via the module `composer.json`
  `extra.laravel.providers`), with no manual entry in `bootstrap/providers.php`.
- **FR-3** — Root `composer.json` gains exactly one `path` repository (`app-modules/*`,
  symlinked) and a `modules/catalog: *` require entry; `composer update` symlinks the module
  and its `Modules\Catalog\` PSR-4 autoloading resolves.
- **FR-4** — The `catalog` module owns a `Product` model + migration + factory, a `web`
  route, and a controller that returns an Inertia response. Migrations run as part of the
  normal `php artisan migrate`.

### Frontend / Inertia (Option B)

- **FR-5** — `app.tsx` defines a **manual `resolve`** that resolves both root pages
  (`resources/js/pages/**`) and module pages (`app-modules/*/resources/js/pages/**`) using a
  `module::page` namespace convention; the `@inertiajs/vite` auto-injection is bypassed.
- **FR-6** — A controller in `catalog` renders `Inertia::render('catalog::index', ...)`
  and the page component at `app-modules/catalog/resources/js/pages/index.tsx` is displayed.
- **FR-7** — Module React pages can import the global shared layer via the `@/` alias
  (e.g. `@/components/ui/*`, `@/layouts/*`). The existing root-page resolution
  (`dashboard`, `auth/*`, `settings/*`) keeps working unchanged.
- **FR-8** — `config/inertia.php` `pages.paths` includes module page directories so
  `assertInertia()->component('catalog::index')` / `ensure_pages_exist` pass in tests.
- **FR-9** — Tailwind v4 scans `app-modules/**` so module-only utility classes are not purged.

### Testing & quality

- **FR-10** — `phpunit.xml` gains a `Modules` test suite discovering `app-modules/*/tests`
  (written by `php artisan modules:sync`), and `tests/Pest.php` binds the base TestCase +
  `RefreshDatabase` for module tests.
- **FR-11** — The `catalog` module ships a Pest feature test that hits its route, asserts
  the Inertia component + props, and passes. The full suite stays green.
- **FR-12** — `eslint-plugin-boundaries` enforces: a module may not import another module;
  modules may import `shared` (`resources/js/**`), never the reverse. Lint passes.

### Documentation

- **FR-13** — A conventions doc (`app-modules/README.md` or `docs/MODULES.md`) records:
  how to scaffold a module, the page-resolution `module::page` convention, the
  shared-vs-module boundary rules, and the per-module directory layout.

## Acceptance Criteria

- `php artisan make:module <x>` → `composer update modules/<x>` yields a working module.
- `php artisan migrate`, `php artisan test --compact`, `npm run build`, `npm run lint`,
  `npm run types:check` all pass.
- Visiting the `catalog` route renders the co-located React page using shared components.
- A new module's pages resolve with no further `app.tsx` edits (only the glob convention).

## Open Questions / Gray Areas (resolved)

- Module engine → `internachi/modular`. (STATE decision)
- Existing auth/settings → stay in `app/`. (STATE decision)
- Frontend architecture → Option B, domain-first + shared. (STATE decision)
- Example module name → `catalog` (parallels course's Product domain; renameable).
- Page-name convention → `module::page`, module token = kebab module dir, page file = lowercase
  to match existing repo convention (`index.tsx`, not `Index.tsx`).
