# QA — {{FEATURE_TITLE}} (PR #{{PR_NUMBER}})

> {{ONE_PARAGRAPH_BEHAVIOR_SUMMARY — what this PR changed, in observable terms, derived from the diff/spec.}}
> Run these steps in order. All commands run from the repo root against your **local** dev app ({{APP_URL}}).
>
> **important:** do not run the `[PRODUCTION-AFFECTING]` steps yourself unless you intend to — they reach a real external service / register or install a real app. The `[READ-ONLY]` and `[MUTABLE]` steps are local and safe.
>
> **Label legend:** `[READ-ONLY]` — reads only (no DB writes, no external side-effects). `[MUTABLE: <what it writes>]` — creates/updates/deletes rows in the local app DB (or triggers a job that does), with NO external side-effect. `[PRODUCTION-AFFECTING: <what it touches>]` — registers/installs a real app or makes a real external service deliver/receive data; **manual execution only**. Reading is never production-affecting — only state modification is.

---

## Prerequisites

<!-- List ONLY what this PR's steps actually need. Common items:
1. App running locally:  composer run dev   (serves {{APP_URL}})
2. Any config/env the feature reads (state the exact key). If a secret is involved, note it is self-chosen and, for a live test, identical in .env and the external service.
3. If queued work is involved: QUEUE_CONNECTION=sync for inline processing, OR a `php artisan queue:work --queue=<queue>` worker.
4. Apply env changes:  php artisan config:clear
Delete any item the PR does not need. -->

---

## 1. Verify config + routes are wired `[READ-ONLY]`

<!-- Confirm new routes/config resolve. Use php artisan route:list --path=<...> and a config:show / tinker echo. Bake the exact expected route names + middleware. -->

**Expected:** {{exact route names, middleware, and config values}}.

---

## 2. Run the automated suite for this PR `[READ-ONLY: isolated test DB — no writes to your dev DB]`

```bash
php artisan test --compact --filter='{{TEST_FILTER}}'
```

**Expected:** all green — name the key tests this PR added. Full suite (`php artisan test --compact`) is {{N}} tests passing.

---

<!-- ====================================================================
FEATURE-SPECIFIC STEPS (3..N) — generated from references/step-catalog.md.
Each step MUST:
  - have a heading `## N. Title [LABEL]` (label per references/label-policy.md)
  - be a runnable command block (artisan/curl/tinker; tinker uses --execute '...' single-quoted, double-quoted PHP strings inside)
  - end with a concrete **Expected:** grounded per references/real-data-grounding.md (real or seeded ids/counts; never placeholders; never PII)
  - add a bold ordering caveat when it drives a state machine
Include an audit-trail verification step when queued jobs / event ingestion are touched.
Include exactly one [PRODUCTION-AFFECTING] live step when an external integration is touched, referencing scripts/tunnel.sh for any tunnel.
==================================================================== -->

---

## {{LAST-1}}. Static checks `[READ-ONLY: no DB writes, no API calls]`

```bash
vendor/bin/pint --test
php artisan test --compact
npm run lint:check && npm run format:check && npm run types:check
```

**Expected:** Pint reports no style issues; full suite green; lint/format/type checks pass. (Include `npm run build` only when the PR changes frontend assets.)

---

## Cleanup (optional)

The `[MUTABLE]` steps wrote throwaway rows to your local dev DB. To reset (destructive — wipes local data):

```bash
php artisan migrate:fresh
```

Revert any QA-only `.env` changes (for example `QUEUE_CONNECTION` back to its prior value) when done.

---

## Known limitations

<!-- From the spec's out-of-scope table + anything deferred. Delete the section if none. -->
