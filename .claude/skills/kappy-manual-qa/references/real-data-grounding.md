# Real-data grounding & PII policy

Every `**Expected:**` must be **falsifiable**: it states a concrete id, count, or value that a wrong, empty, or mis-scoped result would fail. Abstract expectations ("the repos should show", "rows are created") pass on broken output and are forbidden.

## The query-or-seed rule (hybrid)

Kappy's dev DB is often sparse or empty, so ground each Expected in this order:

1. **Query first.** If the data the step needs already exists in the dev DB, read the real id/count and bake it in. Use Boost `database-query` or a tinker `--execute` echo.
2. **Else seed and bake.** If the rows don't exist, the step itself seeds a **deterministic** fixture with factories, then bakes that concrete id/count into the Expected. The seed is part of the step (label it `[MUTABLE: …]`) so the artifact is self-contained and reproducible.
3. **Never** emit a placeholder. No `<id>`, `{repo}`, `SOME_ID`, "pick one of your records". The only literal braces allowed are framework route patterns echoed by `route:list` (e.g. `repositories/{repository}`).

## Prefer ids pinned in the repo's committed fixtures

When the PR ships recorded fixtures or factory defaults, prefer the ids they already pin — they are stable across machines and are the closest thing to "real" data the repo guarantees. Open the committed fixture/factory and copy the exact values into the Expected.

Bake **enough related values plus a count** that the Expected actually proves the behavior, not just non-emptiness. Compare:

```text
[thin — barely falsifiable]
**Expected:** an installation and some repositories exist.

[rich — multiple pinned values + count, proves the behavior]
**Expected (from the committed fixture installation.created.json):** installations=1
(github_installation_id=12345678, target_type=User, repositories_selection=selected);
repositories=2 — github_repo_id 100000001 (full_name "testuser/repo-one", private=false)
and 100000002 (private=true).

[rich — state machine, exact transition values from the pull_request.* fixtures]
**Expected:** pull_requests=1 (no duplicate on the composite key repository_id+github_pr_number);
github_pr_number=42, author_login="contributor", head_sha advances 11223344… → 99887766…,
state open → merged and STAYS merged after the trailing edit.
```

When a value is computed (a sum, a count, a derived state), state the concrete number **and** how to re-derive it, so a reviewer can re-check:

```text
**Expected:** unprocessed deliveries = 0 — re-derive with
WebhookEvent::whereNull('processed_at')->count().
```

If you list several fixtures a step runs against, give each one a row with its real ids so coverage is explicit:

```text
| fixture | real ids baked in |
| --- | --- |
| installation.created | installation 12345678 → repos 100000001, 100000002 |
| pull_request.opened  | repo 100000001, PR #42, head 11223344… |
```

## PII policy (hard)

- **Never** write personal data into the artifact: no personal email, full name, CPF/RG, phone, address, birth date, access token, or any diff/source content. The artifact is committed to the repo.
- Use **opaque identifiers and counts** instead: `github_account_id`, numeric row ids, `count=N`. A GitHub login/handle is borderline — prefer the numeric `github_account_id` over a username when either works.
- When a step must reference a user, derive it by a non-PII key (factory-created id, `github_account_id`) — not by email.
- If a tinker step would print PII while exploring, **mask it** (e.g. `preg_replace('/(?<=.).(?=.*@)/','*',$email)`) and keep the masked form out of the committed Expected. If a value is too sensitive to commit, re-ground the Expected on a non-PII id/count instead — never on a value you have to hide.

## Local-only, dev-safe

- All grounding reads/writes target the **local** dev DB and `http://localhost` (the `APP_URL`), never a production host.
- `[MUTABLE]` seeds are throwaway; the artifact's Cleanup section documents how to reset (`migrate:fresh`).
