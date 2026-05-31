# STATE — Memory

> Persistent decisions, blockers, and lessons across sessions.

## Decisions

- **2026-05-30 — Modular engine: `internachi/modular`.** Chosen over hand-rolled (course-exact) and `nwidart/laravel-modules`. Same philosophy as the Laracasts "Modular Laravel" course (convention-following, native Composer `path` repos + Laravel package auto-discovery) but with `make:module` tooling, `--module=` generator flags, and Laravel 13 / PHP 8.3 / Pest 4 support. Modules live in `app-modules/`, namespace `Modules\`.
- **2026-05-30 — Shared core stays in `app/`.** Existing Fortify auth + Settings (Profile/Security) controllers remain in `app/` as the application core. Only NEW domain features become modules. Lowest risk, fastest path to a working modular setup.
- **2026-05-30 — Scope: infrastructure + one end-to-end example module.** Set up the modular foundation AND scaffold one example module (`catalog`) wired through the full stack (route, controller, model, migration, Inertia React page, Pest test) to prove it works.
- **2026-05-30 — Frontend architecture: Option B (domain-first + global shared layer).** NOT full Feature-Sliced Design. Rationale: FSD is layer-first/global and assumes it owns routing; it fights the modular monolith's domain-first axis, and has no authoritative Inertia precedent. Instead: co-locate each module's React pages inside the module, keep one global `shared` layer (`resources/js/{components,hooks,lib,layouts}`), and borrow FSD's best ideas — single shared foundation, unidirectional imports enforced by `eslint-plugin-boundaries`, public-API discipline.
- **2026-05-30 — Inertia page resolution: manual `Module::Page` resolver.** The `@inertiajs/vite` plugin auto-injects a resolver that globs ONLY `resources/js/pages/**` and has no multi-directory option. To co-locate module pages, `app.tsx` must define an explicit `resolve` (which the plugin detects and leaves untouched) globbing both `./pages/**` and `../../app-modules/*/resources/js/pages/**`. Proven pattern (avosalmon Laracon India 2025; igeek 2024).

## Blockers

- None.

## Lessons / Gotchas

- **2026-05-30 — `make:module` needs `--accept-default-namespace`, not just `--no-interaction`.** With only `--no-interaction`, internachi/modular's namespace confirm prompt defaults to "cancel" and the module is NOT created. Unattended scaffolding command: `php artisan make:module <name> --accept-default-namespace`, THEN `composer update modules/<name>`.
- **2026-05-30 — T-09 SPIKE result: adding module dirs to `pages.paths` does NOT make `::` assertions pass.** Inertia's `ensure_pages_exist` resolves `app('inertia.view-finder')`; a `FileViewFinder` treats `::` as a namespace-hint (`findNamespacedView`) and throws `No hint path defined for [catalog]`, bypassing `pages.paths` entirely. **Chosen fix (global, keeps `ensure_pages_exist=true`):** rebind `inertia.view-finder` as a singleton in `AppServiceProvider::register()` that `addNamespace(<module>, app-modules/<module>/resources/js/pages)` per module. Then `->component('catalog::index')` resolves the real co-located file (verified: probe passed, 10 assertions, no `withoutVite()`). Documented fallbacks if ever needed: `->component('name', false)` (skip exists-check) or per-test `config(['inertia.testing.ensure_pages_exist' => false])`.
- **2026-05-30 — RUNTIME blocker found & fixed: the starter kit's `resources/views/app.blade.php` hardcoded a per-page Vite entry `"resources/js/pages/{$page['component']}.tsx"`.** For a `::` name this becomes the nonexistent manifest key `resources/js/pages/catalog::index.tsx` → `ViteException` → **HTTP 500 on `/catalog` at runtime** (not just tests). Fix (zero-regression for root pages, preserves modulepreload): compute the entry — `::` names → `app-modules/<module>/resources/js/pages/<page>.tsx`, else unchanged. The `module::page` convention now lives in BOTH `app.tsx` (client resolver) and `app.blade.php` (preload entry); keep them in sync — note this in the conventions doc.
- `config/inertia.php` `pages.paths` is **test-only** (FileViewFinder for `assertInertia`/`ensure_pages_exist`); it does NOT drive client bundler resolution. Module page dirs are added here for FR-8 intent, but the functional `::` resolution comes from the `AppServiceProvider` namespace rebind above.
- **2026-05-30 — composer.json/lock integrity gotcha (IMPORTANT).** During execution, `composer.json` + `composer.lock` were found reverted to git HEAD — missing `internachi/modular`, the `app-modules/*` path repository, and `modules/catalog` — even though `vendor/` + `installed.json` had them installed. Net effect: `composer install` would have *removed* the modular setup (non-reproducible clone). Likely cause: a sub-agent's cleanup `git restore`/`checkout` of composer.json. **Fix applied:** `composer config repositories.app-modules '{"type":"path","url":"app-modules/*","options":{"symlink":true}}'` then `composer require "internachi/modular:^3.0" "modules/catalog:*"`. Verified reproducible: `composer install --dry-run` → "Nothing to install, update or remove". **Guard for future sessions: after composer-affecting work, always run `composer install --dry-run` and confirm it's a no-op; never let an agent blanket-`git restore` tracked files.** (The `*` constraint on `modules/catalog` triggers a benign `composer validate` warning — it's internachi/modular's `make:module` convention and matches FR-3.)
- **2026-05-30 — Stray module cleanup.** A leftover `app-modules/test-module/` (from a scaffolding probe) was removed along with its `vendor/modules/test-module` symlink and installed.json entry. Lesson: when an agent runs `make:module` to *test* the command, it must delete the throwaway module; the final gate must `ls app-modules/` and assert only intended modules exist.
- `@inertiajs/vite` still wraps SSR correctly even with a manual `resolve` — SSR is unaffected.
- Tailwind v4 content scanning must reach `app-modules/**` (add `@source` in `app.css`) or module-only utility classes get purged.
- Module React pages can import the shared layer via the existing `@/` alias regardless of physical location.

## Preferences

- Lightweight tasks (validation, state updates, session handoff) can be run with a faster/cheaper model.

## Deferred ideas

- Optional later: adopt `steiger` if the frontend ever migrates toward fuller FSD.
- Optional later: extract a shared frontend package if cross-module UI grows large.
- Optional later: arch tests (Pest `arch()`) to assert module boundary rules on the PHP side.
