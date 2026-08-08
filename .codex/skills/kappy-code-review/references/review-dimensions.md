# Review Dimensions

This file is a **router**, not a rulebook. Kappy's actual conventions live in dedicated skills and specs; restating them here would duplicate content that drifts. Each pass below names the source of truth to consult and the heuristics that turn a divergence into a finding. Read the cited source every run — do not memorize its rules across sessions.

The review scans the diff in passes. Each pass yields zero or more draft findings, mapped to display labels via [severity-category-mapping.md](severity-category-mapping.md).

## 1. Architecture & layering — `laravel-best-practices/rules/architecture.md`

- **Thin controllers.** Controllers validate input, authorize, delegate to a service/action, and shape the Inertia response. Multi-step query building, aggregation, or orchestration inlined in a controller action → `🛠️ Refactor suggestion | 🟠 Medium`.
- **Business logic concentrated in services/actions**, not in jobs, models, or controllers.
- **Jobs delegate.** A job whose `handle()` builds queries or calls API clients directly instead of calling a service → medium.
- **Models stay relationships + casts + scopes.** Domain/business logic on a Model subclass → medium.
- **Enums over loose strings** for domain states → medium when missing.

## 2. Security & authorization — `laravel-best-practices/rules/security.md`

- Auth bypass, missing/incorrect authorization gate, mass-assignment of sensitive columns, secrets committed to code → `⚠️ Potential issue | 🔴 High`.
- Webhook/SCM signature verification weakened or skipped (relevant to the GitHub App work) → high.
- Permission check present but untested → medium.

## 3. LGPD / PII scan (Kappy is a Brazilian product; org-level guardrail)

- **PII in payloads to the front-end without need-basis** (`email`, `cpf`, phone, `address`, `birth_date`, `rg`, family/financial data joined with identifying data). Over-fetch (present but never rendered) or rendered without justified use → `⚠️ Potential issue | 🟠 Medium`.
- **PII in logs.** `Log::info($model->toArray())`, dumping a request with PII, or a leftover `dd($user)` → `⚠️ Potential issue | 🔴 High`.
- **Missing PII-guard test** — a new endpoint handling user data with no test asserting the response excludes fields it shouldn't expose → medium.
- The reviewer itself must never echo real PII — anonymize examples.

## 4. Tests-with-PR (hard rule) — `pest-testing` + `laravel-best-practices/rules/testing.md`

Every new behavior/wiring/validation/route surface ships with co-located Pest tests in the **same PR**. "Tests in a follow-up" is a blocker.

- New controller method without a Feature test → `⚠️ Potential issue | 🔴 High`.
- New service/action method with non-trivial logic without a Unit/Feature test → high.
- New job without a test asserting dispatch + side effects (`Bus::fake()` / `Queue::fake()`) → high.
- New route/permission without a 403/200 test → high.
- New validation rule without an assertion → medium.

## 5. Database & performance — `laravel-best-practices/rules/db-performance.md` + `eloquent.md`

- N+1 on a request-path/list endpoint, blocking I/O on the request path, ingestion job without overlap protection → `⚠️ Potential issue | 🔴 High`.
- Eager-load gap on a list, redundant query in a loop → `🛠️ Refactor suggestion | 🟠 Medium`.
- Unindexed column used in `WHERE`/`ORDER BY`/`JOIN` on a table likely to grow → medium; on a small table → low.

## 6. Validation & error handling — `laravel-best-practices/rules/validation.md` + `error-handling.md`

- New write endpoint without a Form Request / validation → medium (high if it accepts sensitive data).
- Swallowed exceptions, broad `catch` with no handling, missing failure path → medium.

## 7. Frontend (only when `resources/js/**` is touched) — activate the frontend skills

- `inertia-react-development` — app-level pages live in `resources/js/pages`, components in `resources/js/components`, layouts in `resources/js/layouts`; per-module UI in `app-modules/<module>/resources/js`. Net-new page placed outside these → `🛠️ Refactor suggestion | 🔴 High`. Inertia v3 form/visit patterns (`useForm`, `<Form>`, `router`), deferred-prop empty states.
- `wayfinder-development` — hardcoded URL string where a typed `@/actions` / `@/routes` function exists → medium.
- `tailwindcss-development` — ad-hoc inline styles or arbitrary values where a Tailwind v4 utility fits → low.
- A new component/composable without a co-located test, when the area is tested elsewhere → medium.

## Stop conditions

- A finding the user explicitly says is intentional/out of scope → drop, do not re-flag.
- A finding that depends on code outside the diff → demote to nitpick if at all, never blocking.
- More than ~25 inline findings → keep the most impactful 25 and note the overflow in `_summary.md`.
