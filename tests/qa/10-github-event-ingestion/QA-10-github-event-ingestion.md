# QA — GitHub event ingestion + install UI (PR #10)

> Ingests GitHub `installation` / `installation_repositories` / `pull_request` webhooks into `Installation` / `Repository` / `PullRequest` rows (via queued `ProcessGithubWebhook` → handler Actions), and surfaces the post-install callback page plus an own-account-scoped repositories index with a `review_enabled` toggle. This PR also fixed PR-state derivation (now read from the PR payload, not the webhook action) and added job-level routing coverage.
> Run these steps in order. All commands run from the repo root against your **local** dev app (`http://localhost:8000`).
>
> **important:** do not run the `[PRODUCTION-AFFECTING]` step yourself unless you intend to — it registers/installs the real GitHub App and reaches live GitHub. Steps 1–10 are local and safe.
>
> **Label legend:** `[READ-ONLY]` — reads only (no DB writes, no external side-effects). `[MUTABLE: <what it writes>]` — creates/updates/deletes rows in the local app DB (or triggers a job that does), with NO external side-effect. `[PRODUCTION-AFFECTING: <what it touches>]` — installs/registers the live GitHub App or causes real GitHub to deliver webhooks; **manual execution only**. Reading is never production-affecting — only state modification is.

---

## Prerequisites

1. App running locally and a DB you can write throwaway rows to:
   ```bash
   composer run dev      # serves http://localhost:8000 (+ vite, queue, pail)
   ```
2. Set a webhook secret. **This value is one you choose — GitHub does not issue it; the receiver only verifies each delivery's HMAC signature against it.** It is currently **unset**, so the receiver fails closed and 401s every delivery. For the local replay any value works, as long as it matches the `SECRET` in the step-4 helper — add to `.env`:
   ```
   GITHUB_APP_WEBHOOK_SECRET=local-qa-secret
   ```
   (The live test in step 11 uses its own strong secret, set identically in `.env` **and** the GitHub App.)
3. Make webhook processing synchronous so a POST is fully handled before it returns (avoids running a separate worker for the `webhooks` queue). QA-only — add to `.env`:
   ```
   QUEUE_CONNECTION=sync
   ```
   (Alternatively keep `QUEUE_CONNECTION=database` and run `php artisan queue:work --queue=webhooks` in another terminal.)
4. Apply the env changes:
   ```bash
   php artisan config:clear
   ```

> The replay steps below use the **recorded fixtures** in `app-modules/github-app/tests/fixtures/` (real-shaped GitHub payloads): installation id `12345678`, account `github_account_id=11111111`, repos `100000001` (public) + `100000002` (private), PR `#42`.

---

## 1. Verify config + routes are wired `[READ-ONLY]`

```bash
php artisan tinker --execute 'echo config("services.github-app.webhook_secret") !== null && config("services.github-app.webhook_secret") !== "" ? "secret: set" : "secret: MISSING";'
php artisan route:list --path=webhooks
php artisan route:list --path=repositories
php artisan route:list --path=install
```

**Expected:**
- `secret: set`.
- `POST webhooks/github` → `webhooks.github` (no `web`/session middleware, only `throttle:120,1`).
- `GET repositories` → `repositories.index` and `PATCH repositories/{repository}` → `repositories.update`, both under `web, auth, verified`.
- `GET install/callback` → `install.callback`, under `web, auth, verified`.

---

## 2. Run the automated suite for this PR `[READ-ONLY: isolated in-memory test DB — no writes to your dev DB]`

```bash
php artisan test --compact --filter='HandleInstallation|HandlePullRequestEvent|ProcessGithubWebhookRouting|RepositoryController|InstallCallback'
```

**Expected:** all green — including `a non-closed action (edited) on a merged pull request preserves the Merged state` (the F1 fix) and the three `ProcessGithubWebhookRouting` tests (the F2 coverage). Full suite (`php artisan test --compact`) is **138 tests, 134 passed, 4 skipped**.

---

## 3. Seed a resolvable account `[MUTABLE: inserts one row into accounts]`

The installation handler links to an `Account` by `github_account_id`; without a match it parks (logs + no rows). Create the matching account:

```bash
php artisan tinker --execute 'Modules\Identity\Models\Account::factory()->create(["github_account_id" => 11111111]); echo "accounts with gh id 11111111: ".Modules\Identity\Models\Account::where("github_account_id", 11111111)->count();'
```

**Expected:** `accounts with gh id 11111111: 1`.

---

## 4. Define a signed-replay helper `[READ-ONLY]`

Paste this into the terminal you'll run steps 5–9 from. It signs each fixture's exact bytes with the secret (HMAC-SHA256) like GitHub does:

```bash
SECRET=local-qa-secret
post() {  # $1 = X-GitHub-Event   $2 = fixture name (without .json)   $3 = optional fixed delivery id
  local body sig delivery
  body=$(cat "app-modules/github-app/tests/fixtures/$2.json")
  sig="sha256=$(printf '%s' "$body" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $NF}')"
  delivery="${3:-$(uuidgen)}"
  curl -sS -X POST http://localhost:8000/webhooks/github \
    -H 'Content-Type: application/json' \
    -H "X-GitHub-Event: $1" \
    -H "X-GitHub-Delivery: $delivery" \
    -H "X-Hub-Signature-256: $sig" \
    --data-binary "$body" -w '\n-> HTTP %{http_code}\n'
}
```

**Expected:** no output (function defined). `printf '%s'` (no trailing newline) keeps the signed bytes identical to what curl sends with `--data-binary`.

---

## 5. Replay `installation.created` → installation + repos `[MUTABLE: inserts installations, repositories, webhook_events]`

```bash
post installation installation.created
php artisan tinker --execute 'use Modules\GitHubApp\Models\{Installation,Repository}; $i = Installation::firstWhere("github_installation_id", 12345678); echo "installation: ".($i?->id ? "yes" : "no").", target=".$i?->target_type->value.", selection=".$i?->repositories_selection->value.", repos=".Repository::where("installation_id", $i?->id)->count();'
```

**Expected:** the POST returns `-> HTTP 202`. Then: `installation: yes, target=User, selection=selected, repos=2` (repos `100000001` public + `100000002` private).

---

## 6. Replay `pull_request.opened` → PR row, state open `[MUTABLE: inserts pull_requests + webhook_events]`

```bash
post pull_request pull_request.opened
php artisan tinker --execute 'use Modules\GitHubApp\Models\PullRequest; $p = PullRequest::firstWhere("github_pr_number", 42); echo "pr: ".($p?->id ? "yes" : "no").", state=".$p?->state->value.", author=".$p?->author_login.", head=".$p?->head_sha;'
```

**Expected:** `-> HTTP 202`, then `pr: yes, state=open, author=contributor, head=11223344112233441122334411223344aabbccdd`.

---

## 7. Replay `pull_request.closed_merged` then `pull_request.edited` → state stays Merged (the F1 fix) `[MUTABLE: updates the pull_requests row]`

```bash
post pull_request pull_request.closed_merged
php artisan tinker --execute 'echo "after merge: ".Modules\GitHubApp\Models\PullRequest::firstWhere("github_pr_number", 42)->state->value;'

post pull_request pull_request.edited
php artisan tinker --execute 'use Modules\GitHubApp\Models\PullRequest; echo "after edit: ".PullRequest::firstWhere("github_pr_number", 42)->state->value.", count=".PullRequest::count();'
```

**Expected:** `after merge: merged`, then **`after edit: merged, count=1`**. This is the bug fix — before it, the `edited` action fell through to `default => Open` and reverted the merged PR to `open`. `count=1` confirms no duplicate (upsert on `repository_id + github_pr_number`).

---

## 8. Idempotency + signature rejection `[MUTABLE: attempts a duplicate; verifies it is a no-op]`

```bash
# same delivery id twice → second is an idempotent no-op (unique github_delivery_id)
post pull_request pull_request.synchronize qa-dup-001
post pull_request pull_request.synchronize qa-dup-001
php artisan tinker --execute 'echo "webhook_events for qa-dup-001: ".Modules\GitHubApp\Models\WebhookEvent::where("github_delivery_id", "qa-dup-001")->count();'

# tampered signature → rejected, nothing persisted
curl -sS -X POST http://localhost:8000/webhooks/github \
  -H 'Content-Type: application/json' -H 'X-GitHub-Event: pull_request' \
  -H "X-GitHub-Delivery: $(uuidgen)" -H 'X-Hub-Signature-256: sha256=deadbeef' \
  --data-binary @app-modules/github-app/tests/fixtures/pull_request.opened.json -w '\n-> HTTP %{http_code}\n'
```

**Expected:** both `synchronize` POSTs return `202`, but `webhook_events for qa-dup-001: 1` (one row, no re-processing). The tampered request returns `-> HTTP 401`.

---

## 9. `processed_at` is stamped on every handled delivery (the F2 path) `[READ-ONLY]`

```bash
php artisan tinker --execute 'use Modules\GitHubApp\Models\WebhookEvent; echo "total=".WebhookEvent::count().", unprocessed=".WebhookEvent::whereNull("processed_at")->count();'
```

**Expected:** `unprocessed=0` — `ProcessGithubWebhook::handle()` stamped `processed_at` for every delivery it routed (installation, pull_request, synchronize), which the job-level test (`ProcessGithubWebhookRoutingTest`) now also guards.

---

## 10. UI: install callback + repositories index `[MUTABLE: seeds a user/account/repo; browser steps are READ-ONLY except the toggle]`

```bash
php artisan tinker --execute 'use App\Models\User; use Modules\Identity\Models\{Account,Membership}; use Modules\GitHubApp\Models\{Installation,Repository}; $u = User::factory()->create(["email" => "qa@example.com", "password" => bcrypt("password")]); $a = Account::factory()->create(); Membership::factory()->for($u)->for($a)->owner()->create(); $ins = Installation::factory()->for($a)->create(); Repository::factory()->for($ins)->create(["full_name" => "qa/repo-one", "private" => false, "review_enabled" => false]); echo "login: qa@example.com / password";'
```

Then in a browser (logged in as `qa@example.com`):
- `http://localhost:8000/install/callback` — shows the "GitHub App Installed" confirmation card with a working "View your repositories" link.
- `http://localhost:8000/repositories` — lists **only** `qa/repo-one` with a `Public` badge and a **Review enabled** checkbox (unchecked). Tick it; it persists across reload. Verify: `php artisan tinker --execute 'echo Modules\GitHubApp\Models\Repository::firstWhere("full_name", "qa/repo-one")->review_enabled ? "enabled" : "disabled";'` → `enabled`.

**Expected:** guests hitting either URL are redirected to login; the index shows exactly 1 repo (the seeded user's), never repos from other accounts; the toggle round-trips and persists.

---

## 11. Live GitHub end-to-end smoke test `[PRODUCTION-AFFECTING: registers/installs the real GitHub App; real GitHub delivers webhooks]`

> Manual only — do **not** run autonomously. This is the Phase 2 → Phase 3 boundary check; do it once before building the review pipeline. Use a throwaway repo. Unlike steps 1–10, this needs a real GitHub App, a public tunnel, and a real GitHub login.

### 11a. Prepare the secret + queue
The webhook secret is a value **you choose** (GitHub does not issue it) and the **same** value must live in `.env` and in the GitHub App. Generate it, set it, and make processing inline:

```bash
openssl rand -hex 32        # copy the output → put it in .env AND the App's Webhook secret (11d)
# .env:  GITHUB_APP_WEBHOOK_SECRET=<that value>   and   QUEUE_CONNECTION=sync
php artisan config:clear
```

> `GITHUB_APP_ID` / `GITHUB_APP_PRIVATE_KEY` are **NOT needed** for ingestion — they're only for the Phase-3 `ScmDriver` token exchange. Skip them here.

### 11b. Sign into Kappy via GitHub so your account exists `[MUTABLE: provisions your user + account]`
The install only resolves if a local `Account` has a `github_account_id` equal to the GitHub account you install under. That account is created **only on the GitHub *new-user* login path** — an email/password signup creates a User with **no** Account (you'll see "No accounts yet"), and a GitHub login that *links* to an existing same-email password user also creates no account.

1. Start the app: `composer run dev`.
2. `http://localhost:8000/login` → **Sign in with GitHub** → authorize. (If you already authorized the OAuth app before, GitHub skips the consent screen and signs you in silently — that's normal remembered-consent, not a bug.)
3. Verify the account exists and matches your GitHub id:

```bash
php artisan tinker --execute 'use Modules\Identity\Models\Account; use App\Models\User; $a=Account::first(); $u=User::whereNotNull("github_id")->first(); echo "account github_account_id=".$a?->github_account_id.", your github_id=".$u?->github_id.", match=".(($a && $u && $a->github_account_id==$u->github_id)?"YES":"NO");'
```

**Expected:** `match=YES`. Troubleshooting:
- **"No accounts yet" on the dashboard** → first hard-refresh `/dashboard` (the page can be stale from before the account existed).
- Still empty → you have a leftover non-GitHub user. Delete it and sign in again to force the fresh new-user path: `php artisan tinker --execute 'App\Models\User::whereNull("github_id")->delete();'`

### 11c. Start the tunnel `[READ-ONLY]`

```bash
./scripts/tunnel.sh 8000 -p /webhooks/github      # separate terminal — leave it running
```

Copy the green-banner URL (`https://<random>.ngrok-free.app/webhooks/github`). A free-tier URL **changes on every restart** → re-paste it into the App's Webhook URL each time.

### 11d. Register the GitHub App
**Settings → Developer settings → GitHub Apps → New GitHub App:**

| Field | Value |
|---|---|
| GitHub App name / Homepage URL | anything (e.g. `http://localhost:8000`) |
| **Callback URL** | **blank** — Kappy login uses a separate OAuth app, not this GitHub App |
| Request user authorization during install | unchecked |
| Setup URL (optional) | blank, or `https://<tunnel>/install/callback` to test the callback page via the real post-install redirect |
| **Webhook → Active** | ✓ |
| **Webhook URL** | the `scripts/tunnel.sh` URL (`…/webhooks/github`) |
| **Webhook secret** | the exact value from 11a |
| **SSL verification** | **Enabled** (ngrok/cloudflared serve a valid cert — never disable) |
| Permissions → Repository → **Pull requests** | **Read-only** (Metadata: Read-only is added automatically) |
| Subscribe to events | **Pull request** only — the `installation` / `installation_repositories` events are delivered to a GitHub App automatically (no checkbox) |
| Where can this be installed | Only on this account |

Create the App. It immediately sends a **`ping`** → confirm a **202** in **App → Advanced → Recent Deliveries**, then:

```bash
php artisan tinker --execute 'echo "webhook_events=".Modules\GitHubApp\Models\WebhookEvent::count();'   # → 1 (ping received + processed)
```

> A `401` in Recent Deliveries means the secret in `.env` ≠ the App's Webhook secret. Fix it, `php artisan config:clear`, and **Redeliver** from that tab.

### 11e. Install the App + drive a PR — ⚠️ order matters for the F1 check
1. App page → **Install App** → **your personal account** (not an organization — an org's id would not match your `github_account_id` and would be parked) → a test repo.
2. On that repo, in **this exact order**: **open a PR → push a commit (synchronize) → merge → THEN edit the title.**

> **The title edit MUST come *after* the merge.** The F1 fix is specifically about a non-`closed` action (`edited`/`labeled`/…) arriving on an **already-merged** PR. Editing *before* merging does not test it — the PR is still open, so both the old and fixed code yield `open`. (The merge itself arrives as action `closed` with `merged=true`.)

### 11f. Verify — current state AND the full audit trail
The `PullRequest` row holds only the *latest* state (it's upserted in place); **`webhook_events` is the audit log** that proves each delivery arrived, in order, and was processed. (GitHub's **Advanced → Recent Deliveries** tab is the same history, with payloads + a Redeliver button.)

```bash
php artisan tinker --execute '
use Modules\GitHubApp\Models\{Installation,Repository,PullRequest,WebhookEvent};
echo "installations=".Installation::count().", repositories=".Repository::count().", pull_requests=".PullRequest::count().", unprocessed=".WebhookEvent::whereNull("processed_at")->count().PHP_EOL;
foreach (WebhookEvent::orderBy("id")->get() as $e) { echo $e->event." / ".($e->action ?: "—")." | processed=".($e->processed_at?"yes":"NO").PHP_EOL; }
$p=PullRequest::latest("id")->first(); echo "PR state=".($p?->state->value ?? "none")." (expect merged)";'
```

**Expected:**
- `installations ≥ 1`, `repositories ≥ 1`, `unprocessed=0`.
- The audit trail shows, in order: `ping`, `installation / created`, `pull_request / opened`, `pull_request / synchronize`, `pull_request / closed` (the merge), `pull_request / edited` (the post-merge edit).
- **`PR state=merged`** — and it **stays `merged`** after that trailing `edited`. That is the live F1 proof (the old action-based logic would have reverted it to `open`).
- `http://localhost:8000/repositories` lists the installed repo with the Review toggle.
- **Privacy:** no diff/source/token content in the DB or in `php artisan pail` — only routing/audit info (LGPD invariant).

---

## 12. Static checks `[READ-ONLY: no DB writes, no API calls]`

```bash
vendor/bin/pint --test
php artisan test --compact
npm run lint:check && npm run format:check && npm run types:check
```

**Expected:** Pint reports no style issues; full suite green; lint/format/type checks all pass.

---

## Cleanup (optional)

The QA rows above live in your local dev DB. To reset (destructive — wipes local data):

```bash
php artisan migrate:fresh
```

Also revert the QA-only `.env` changes (`QUEUE_CONNECTION` back to `database`) when done.

---

## Known limitations

- `review_enabled` is a stored/displayed flag only this phase — it does **not** gate ingestion (every PR on an installed repo is still ingested). It is the surface Phase 3 will read.
- `PullRequest.linked_issue_ref` is intentionally not written this phase (Phase 5).
- An install whose GitHub account has no matching local `Account` is parked with a `Log::info` audit (not provisioned) — by design, pending the identity provisioning action.
- No review runs in Phase 2 (no LLM, no `Review`/`Finding`); the `ScmDriver` write methods are contract-only.
