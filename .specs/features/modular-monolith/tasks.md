# Tasks: Modular Monolith Foundation + Example Module

> For `.specs/features/modular-monolith/{spec,design}.md`. Execute top-to-bottom; `[P]` = parallelizable.
> Gate after each backend task: `php artisan test --compact` stays green. Run `vendor/bin/pint --dirty --format agent` after PHP edits.

---

## Phase 1 — Engine setup

### T-01 — Install & configure `internachi/modular`
- **What:** Add the package and publish config.
- **Where:** root `composer.json`, `config/app-modules.php`.
- **Do:** `composer require internachi/modular`; `php artisan vendor:publish --tag=modular-config`;
  set `modules_namespace => 'Modules'`, `modules_directory => 'app-modules'`, `tests_base => 'Tests\TestCase'`.
- **Done when:** `php artisan make:module --help` works; config present.
- **Verify:** `php artisan about` runs; `php artisan test --compact` green.
- Satisfies: FR-1.

### T-02 — Scaffold the `catalog` module
- **Depends on:** T-01.
- **Do:** `php artisan make:module catalog --no-interaction`; then `composer update modules/catalog`.
- **Done when:** `app-modules/catalog/` exists with `composer.json`, `src/Providers/CatalogServiceProvider.php`,
  `routes/catalog-routes.php`, `database/{migrations,factories,seeders}/`, `tests/`; root `composer.json`
  has the `app-modules/*` path repo + `modules/catalog: *`.
- **Verify:** `php artisan about` lists the auto-discovered provider; `composer dump-autoload` resolves
  `Modules\Catalog\`; `php artisan test --compact` green.
- Satisfies: FR-2, FR-3.

---

## Phase 2 — Backend domain (catalog)

### T-03 — Product model + migration + factory
- **Depends on:** T-02.
- **Do:** `php artisan make:model Product --module=catalog -mf`. Migration: `products` table
  (`id, name string, price unsignedInteger (cents), timestamps`). Model: typed, `HasFactory`,
  `$fillable`. Factory: `name`, `price`.
- **Done when:** `php artisan migrate` creates `products`; `Product::factory()->create()` works in tinker/test.
- **Verify:** `php artisan migrate:fresh` succeeds; gate green.
- **Reuses:** `Tests\TestCase`. Satisfies: FR-4.

### T-04 — Controller + route
- **Depends on:** T-03.
- **Do:** `php artisan make:controller ProductController --module=catalog`. Implement `index()`
  returning `Inertia::render('catalog::index', ['products' => Product::query()->latest()->get()])`.
  Add the `GET /catalog` route in `routes/catalog-routes.php` (name `catalog.index`, `web` middleware).
- **Done when:** `php artisan route:list --path=catalog` shows the route.
- **Verify:** route resolves (will fail page-exists until Phase 3 — acceptable; assert JSON/redirect
  or use `withoutInertiaPageExists` in interim). Gate green.
- Satisfies: FR-4, FR-6.

---

## Phase 3 — Frontend wiring (Option B)

### T-05 — Manual `Module::page` resolver in `app.tsx`
- **Depends on:** none (can start in parallel with Phase 2) `[P]`.
- **Do:** Replace reliance on the auto-injected resolver with an explicit `resolve` (see design
  Component 3): dual `import.meta.glob` for `./pages/**/*.tsx` and
  `../../app-modules/*/resources/js/pages/**/*.tsx`; `::` → module path, else root path; throw on miss.
  Keep `title`, `layout`, `withApp`, `progress` unchanged.
- **Done when:** existing pages (`dashboard`, `auth/login`, `settings/*`) still load.
- **Verify:** `npm run dev`/`npm run build` succeed; manually load an existing page.
- Satisfies: FR-5, FR-7.

### T-06 — Co-located `catalog` index page
- **Depends on:** T-05, T-04.
- **Do:** Create `app-modules/catalog/resources/js/pages/index.tsx` — a React page importing the
  shared layer via `@/` (e.g. `@/layouts/app-layout`, `@/components/ui/*`) and rendering the
  `products` prop. Add a TS type for the prop.
- **Done when:** visiting `/catalog` renders the page with shared components.
- **Verify:** `npm run build` + `npm run types:check` pass; visual check at `/catalog`.
- Satisfies: FR-6, FR-7.

### T-07 — Tailwind v4 source scanning `[P]`
- **Depends on:** none.
- **Do:** Add `@source "../../app-modules";` to `resources/css/app.css`.
- **Done when:** a utility class used only in a module page renders (not purged).
- **Verify:** `npm run build`; inspect compiled CSS or visual check.
- Satisfies: FR-9.

---

## Phase 4 — Test discovery & coverage

### T-08 — Pest/PHPUnit module discovery
- **Depends on:** T-02.
- **Do:** `php artisan modules:sync` (adds `Modules` testsuite to `phpunit.xml`). Add to `tests/Pest.php`:
  `uses(Tests\TestCase::class, RefreshDatabase::class)->in(__DIR__.'/../app-modules');`
- **Done when:** `php artisan test --compact` discovers `app-modules/*/tests`.
- **Verify:** add a trivial passing module test, confirm it runs, then remove.
- Satisfies: FR-10.

### T-09 — Spike: `assertInertia` + `::` component names
- **Depends on:** T-04.
- **Do:** Confirm whether `assertInertia()->component('catalog::index')` with
  `ensure_pages_exist=true` resolves a `::` name via FileViewFinder. Decide final approach:
  (a) add module page roots to `config/inertia.php` `pages.paths` and use `::`; or
  (b) assert with `shouldExist: false`; or (c) per-test disable `ensure_pages_exist`.
- **Done when:** chosen approach documented inline + in STATE lessons.
- **Verify:** the decision yields a passing assertion in T-10.
- Satisfies: FR-8 (informs it).

### T-10 — `config/inertia.php` module page paths
- **Depends on:** T-09.
- **Do:** Per T-09 outcome, add module page dirs:
  `...glob(base_path('app-modules/*/resources/js/pages')) ?: []` to `pages.paths`.
- **Done when:** test component assertion for `catalog::index` passes.
- **Verify:** T-11 test green.
- Satisfies: FR-8.

### T-11 — Catalog Pest feature test
- **Depends on:** T-06, T-08, T-10.
- **Do:** `php artisan make:test ProductTest --pest` then move/create under
  `app-modules/catalog/tests/Feature/ProductTest.php` (namespace `Modules\Catalog\Tests\Feature`).
  Test: seed products via factory, `get('/catalog')`, assert 200 + Inertia component `catalog::index`
  + `products` prop count.
- **Done when:** test passes; full suite green.
- **Verify:** `php artisan test --compact`.
- Satisfies: FR-11.

---

## Phase 5 — Boundaries & docs

### T-12 — `eslint-plugin-boundaries` rules
- **Depends on:** T-06.
- **Do:** `npm i -D eslint-plugin-boundaries`; in `eslint.config.js` declare element types
  (`shared` = `resources/js/**`, `module` = `app-modules/*/resources/js/**` captured by name) and an
  allow-matrix: module → shared + own module; deny module → other module; deny shared → module.
- **Done when:** an intentional cross-module import errors; a `@/` import from a module passes.
- **Verify:** `npm run lint` passes on clean tree; `npm run types:check` passes.
- Satisfies: FR-12.

### T-13 — Conventions doc
- **Depends on:** T-01..T-12 substantially done.
- **Do:** Write `app-modules/README.md` (or `docs/MODULES.md`): scaffold steps
  (`make:module` → `composer update modules/<x>`), the `module::page` resolution convention,
  module directory layout, shared-vs-module boundary rules, how to add a page/route/test.
- **Done when:** a new dev can create a module by following it.
- **Verify:** dry-run the steps mentally against a hypothetical `orders` module.
- Satisfies: FR-13.

### T-14 — Full verification gate
- **Depends on:** all.
- **Do:** Run the project gate: `php artisan test --compact`, `npm run lint`,
  `npm run format:check`, `npm run types:check`, `npm run build` (and `build:ssr`),
  `vendor/bin/pint --test --format agent` (report only).
- **Done when:** all pass; existing 13 tests + new catalog test green.
- **Verify:** capture command output as evidence.
- Satisfies: Acceptance Criteria.

---

## Dependency graph

```
T-01 → T-02 → T-03 → T-04 ┐
            └→ T-08       ├→ T-09 → T-10 ┐
T-05 [P] ────────→ T-06 ──┘              ├→ T-11 → T-14
T-07 [P]                  └→ T-12 ────────┘
                              T-13 (after most) ┘
```

## Traceability

| Req | Tasks |
|---|---|
| FR-1 | T-01 |
| FR-2 | T-02 |
| FR-3 | T-02 |
| FR-4 | T-03, T-04 |
| FR-5 | T-05 |
| FR-6 | T-04, T-06 |
| FR-7 | T-05, T-06 |
| FR-8 | T-09, T-10 |
| FR-9 | T-07 |
| FR-10 | T-08 |
| FR-11 | T-11 |
| FR-12 | T-12 |
| FR-13 | T-13 |
