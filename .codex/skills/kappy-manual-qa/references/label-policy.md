# Label policy — classifying every QA step

Every step carries exactly one label. The label tells the runner (human or this skill) what the step is safe to do automatically. The distinction is **state modification**, not data sensitivity: reading real data is never production-affecting.

## The three labels

### `[READ-ONLY]`
Reads only — no DB writes, no external side-effects. Safe to auto-run.
Examples: `route:list`, `config:show`, a tinker `echo` of a config/count, the automated test suite (isolated test DB), `pint --test`, lint/type checks, querying the audit/event table.

### `[MUTABLE: <what it writes>]`
Creates/updates/deletes rows in the **local** app DB, or triggers a job/handler that does — with **no** external side-effect. Safe to auto-run locally. State exactly what it writes in the label.
Examples: seeding factories, a signed local `curl` to a webhook route, `Job::dispatchSync(...)`, toggling a record through a controller, an idempotency re-delivery check.

### `[PRODUCTION-AFFECTING: <what it touches>]`
Registers/installs a real app, or makes a **real external service** deliver or receive data (GitHub App install + real webhook deliveries, a real OAuth authorization round-trip against the provider, any real SCM read/write). **Manual-only — never auto-run.** Name the external system it touches.
Examples: registering/installing the GitHub App and merging a real PR; a live OAuth login that hits the provider; any `scripts/tunnel.sh`-backed live delivery.

## Classification rules

- If a command only reads → `[READ-ONLY]`, even if it reads real/sensitive data.
- If it writes to the local DB or runs local code that does, with no outbound call to a shared/external system → `[MUTABLE]`.
- If it causes a real external system to act, or registers/installs against one → `[PRODUCTION-AFFECTING]`, no matter how small.
- A local signed `curl` to your own app's webhook route is `[MUTABLE]` (you generated the request locally). A real GitHub delivery to a tunnel is `[PRODUCTION-AFFECTING]` (GitHub sent it).
- When unsure between MUTABLE and PRODUCTION-AFFECTING, choose **PRODUCTION-AFFECTING** (fail safe — it just means manual-only).

## Runner contract

- This skill auto-runs `[READ-ONLY]` and `[MUTABLE]` steps during the run phase and compares output to each `**Expected:**`.
- It **never** auto-runs `[PRODUCTION-AFFECTING]` steps — it presents them and waits for the user.
- The artifact's intro blockquote restates this contract and carries the full label legend so a human reader has the same guarantee.
