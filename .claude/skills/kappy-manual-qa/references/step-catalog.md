# Step catalog — code type → QA step recipe

Map each changed file to a code type below and emit the listed step(s). Each recipe gives the command shape and the `**Expected:**` shape. Ground every value per [real-data-grounding.md](real-data-grounding.md) and label per [label-policy.md](label-policy.md). Kappy is a modular monolith (`app-modules/<module>/src`, namespace `Modules\<Module>\`); tinker uses `--execute '...'` (single-quoted outer, double-quoted PHP strings inside).

## Table of contents
1. Routes
2. Controllers — Inertia (browser) pages
3. Controllers — server-to-server (webhook/JSON)
4. Queued jobs
5. Actions / event-handlers
6. Models & migrations
7. Frontend Inertia pages
8. Authorization (Policies / Gates)
9. External integrations (webhooks / OAuth / SCM)
10. Cross-cutting steps every artifact gets

---

## 1. Routes
Recipe `[READ-ONLY]`: `php artisan route:list --path=<segment>`.
**Expected:** the exact route name(s), HTTP verb(s), controller, and middleware stack (call out when a route is intentionally outside the `web` group, or under `auth`/`verified`).

## 2. Controllers — Inertia (browser) pages
Recipe `[MUTABLE: seeds the rows the page reads]` + browser check: seed the minimum graph with factories, then assert the Inertia component + props server-side and/or visit the page.
**Expected:** the page renders the named component; props contain the seeded ids/counts; guests are redirected to `login`; scoping holds (a user sees only their own rows). Prefer an `assertInertia` one-liner for the prop shape and a browser visit for the visual.

## 3. Controllers — server-to-server (webhook/JSON)
Recipe `[MUTABLE]` via signed `curl` (these endpoints verify a signature and sit outside the `web` group). Generate a signed-replay helper that HMACs the exact bytes:
```bash
SECRET=<the .env secret>
post() { # $1=event $2=fixture-name $3=optional-delivery-id
  local body sig; body=$(cat "<module>/tests/fixtures/$2.json")
  sig="sha256=$(printf '%s' "$body" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $NF}')"
  curl -sS -X POST <APP_URL>/<route> -H 'Content-Type: application/json' \
    -H "X-<Provider>-Event: $1" -H "X-<Provider>-Delivery: ${3:-$(uuidgen)}" \
    -H "X-Hub-Signature-256: $sig" --data-binary "$body" -w '\n-> HTTP %{http_code}\n'; }
```
**Expected:** valid signature → the documented success status (e.g. `202`) + the persisted row; **bad signature → 401, nothing persisted**; **duplicate delivery id → success but no second row / no re-processing** (idempotency). Always include the negative (bad-signature) and idempotency checks for these endpoints.

## 4. Queued jobs
Recipe `[MUTABLE]`: drive the job through the container so injected handlers resolve.
```bash
php artisan tinker --execute '<Job>::dispatchSync(<args>); /* then assert resulting rows + any processed/marker column */'
```
**Expected:** the job routed to the right handler (resulting domain rows exist) AND any bookkeeping column it writes (e.g. `processed_at`) is set. Cover the `default`/unknown branch too. Note in Prerequisites that a real run needs `QUEUE_CONNECTION=sync` or a `--queue=<name>` worker.

## 5. Actions / event-handlers
Recipe `[MUTABLE]`: exercise each branch with recorded fixtures; assert the persisted effect and idempotency on re-delivery.
**Expected:** each branch's row effect; re-running the same input yields no duplicate (upsert on the natural key). **State machines:** add a bold ordering caveat — drive the transition that actually exercises the change (e.g. reach the terminal state, THEN deliver the late/again event) and state why order matters.

## 6. Models & migrations
Usually **not** a standalone step — exercised by the controller/job/action tests. Only add a `[READ-ONLY]` `database-schema`/`migrate:status` check when the PR adds a constraint/index whose presence is the point.
**Expected:** the column/unique/index exists; enum columns cast to the enum.

## 7. Frontend Inertia pages
Recipe `[MUTABLE: seeds + logs in a user]` then browser `[READ-ONLY except documented interactions]`. App-level pages live in `resources/js/pages`; per-module under `app-modules/<module>/resources/js`. Use Wayfinder typed routes.
**Expected:** the page lists only the authed user's data; visibility/badges/empty-states render; interactive controls (toggles/forms) round-trip and persist (verify the persisted value with a follow-up tinker read); guests are redirected.

## 8. Authorization (Policies / Gates)
Recipe `[MUTABLE]`: seed an owned resource and a foreign one; act as the user against both.
**Expected:** allowed on owned (persists / 2xx), **denied on foreign (403)**. Name the policy/ability and the membership/role that grants it.

## 9. External integrations (webhooks / OAuth / SCM)
Emit exactly one `[PRODUCTION-AFFECTING]` live step. For tunnels, reference the repo-level `scripts/tunnel.sh` (e.g. `./scripts/tunnel.sh 8000 -p /<route>`) — never inline a raw `ngrok`/`cloudflared` command. Include the external-app field table (webhook URL, secret matching `.env`, SSL verification, permissions, subscribed events, callback/setup URLs as applicable), the first-login/account-provisioning prerequisite when the resolution depends on it, and a verification block reading the **audit/event table** plus the provider's delivery history.
**Expected:** the install/connect ingests rows; the end-to-end action produces the right state; **a privacy line: no secret/diff/source/PII written to DB or logs**. This step is manual-only.

## 10. Cross-cutting steps every artifact gets
- **Automated suite** `[READ-ONLY]` filtered to the PR's tests (+ the full-suite count).
- **Audit-trail verification** `[READ-ONLY]` whenever jobs/events are touched: list the event/log table chronologically (it is the retrospective proof that each delivery/action happened and was processed) and point at the provider's delivery history as the external mirror.
- **Static checks** `[READ-ONLY]`: `vendor/bin/pint --test`, full test run, `npm run lint:check && format:check && types:check` (add `build` only for FE changes).
- **Cleanup** + **Known limitations** (from the spec out-of-scope).
