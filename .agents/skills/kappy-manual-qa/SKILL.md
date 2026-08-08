---
name: kappy-manual-qa
description: Generates a runnable manual-QA artifact for a just-implemented Kappy PR. Reads the open PR's number, branch, and diff; writes a tests/qa/PRNUMBER-BRANCH/QA-PRNUMBER-BRANCH.md whose steps are labeled READ-ONLY, MUTABLE, or PRODUCTION-AFFECTING and whose Expected values are grounded in real database ids (queried, or seeded-and-baked) and never contain PII; runs the safe local checks; then on success offers to commit the file to the PR or delete it. Auto-chains after kappy-finalize opens a PR, and also triggers on 'gera o QA manual desse PR', 'generate the manual QA for this PR', 'create the QA artifact for PR N', 'manual QA do Kappy'. Do NOT use for PR code review (pr-review), implementing a PR (pr-execute), planning features (tlc-spec-driven), or committing the feature change itself (kappy-finalize).
license: CC-BY-4.0
metadata:
  author: Kappy contributors
  version: 1.0.0
---

# Kappy Manual QA

Generates a grounded, runnable manual-QA artifact for a Kappy PR that already exists on GitHub, drives the safe local checks, and then either commits the artifact to the PR or deletes it on your call. It sits after `kappy-finalize` opens a PR and is independent of `pr-review`: that skill writes local review findings, this one produces a QA checklist a human can actually run.

## Operating principles

- **The PR must already be open.** The artifact is keyed by PR number, so this skill never runs before the PR exists. It resolves the number from the `kappy-finalize` handoff or from `gh pr view`.
- **Grounded, falsifiable Expected values.** Every `**Expected:**` cites a concrete id/count — queried from the dev DB, or seeded by the step and baked in. Never `<id>`, "some rows", or other unfalsifiable phrasing. See [references/real-data-grounding.md](references/real-data-grounding.md).
- **Never write PII.** No personal email, name, CPF, token, or diff/source content in the artifact — use opaque ids (`github_account_id`, row counts) instead.
- **Production-affecting steps are manual-only.** Anything that registers/installs a real GitHub App or makes a real external service deliver/receive data is `[PRODUCTION-AFFECTING]` and is never auto-run. See [references/label-policy.md](references/label-policy.md).
- **The artifact is review-quality, not a stub.** It matches the structure of the committed artifacts under `tests/qa/` — blockquote intro + label legend, numbered steps each ending in `**Expected:**`, prerequisites, audit-trail verification where state/events are involved, ordering caveats for state machines, static checks, cleanup, known limitations.
- **Commit stages only the QA file.** Never stage pre-existing dirty files.

## When to use

- Auto-chained: `kappy-finalize` just opened/updated a PR and hands off with the PR number.
- Manually: "gera o QA manual desse PR", "generate the manual QA for this PR", "create the QA artifact for PR N", "manual QA do Kappy".
- Re-run before merge when the branch changed and the artifact would be stale.

## When NOT to use

- PR code review → `pr-review`.
- Implementing the PR or its fixes → `pr-execute`.
- Planning an unimplemented feature → `tlc-spec-driven`.
- Branching/committing/opening the PR for the feature change → `kappy-finalize`.
- A PR that does not exist yet (no number) → open it first via `kappy-finalize`.

## Workflow

### Step 0 — Resolve context

1. Get the PR number. From the `kappy-finalize` handoff if present; otherwise `gh pr view <n> --json number,headRefName,headRefOid,baseRefName` (or `gh pr list --head $(git branch --show-current)`). If no open PR exists, stop and route the user to `kappy-finalize`.
2. Get the diff scope and intent:
   ```bash
   gh pr diff <n> --name-only
   gh pr diff <n>
   ```
   (Pre-PR fallback: `git diff --merge-base main...HEAD`.) Read any matching `.specs/features/<feature>/spec.md` + `tasks.md` for the feature summary and out-of-scope list.
3. Activate the relevant domain skills for the surfaces the diff touches so the steps use Kappy idioms: `laravel-best-practices`, `pest-testing`, and for `resources/js/**` also `inertia-react-development`, `wayfinder-development`, `tailwindcss-development`.

### Step 1 — Derive the artifact path

Strip the branch type prefix to a slug (`feat/github-event-ingestion` → `github-event-ingestion`), then build the **PR-number-and-name** path:

```
tests/qa/<pr_number>-<slug>/QA-<pr_number>-<slug>.md
```

Example: PR 10 on `feat/github-event-ingestion` → `tests/qa/10-github-event-ingestion/QA-10-github-event-ingestion.md`. If the directory already exists (re-run), overwrite the file.

### Step 2 — Map the diff to QA dimensions

For each changed file, classify it and pick its step recipe from [references/step-catalog.md](references/step-catalog.md) — routes, controllers (Inertia vs server-to-server JSON), queued jobs, Actions/event-handlers, models/migrations, FE Inertia pages, and **external integrations** (webhooks / OAuth / SCM). Read that file now; it maps each code type to the step(s) and `**Expected:**` shape to emit. Skip pure scaffolding/config with no behavior.

### Step 3 — Generate the artifact

Assemble from [assets/qa-template.md](assets/qa-template.md) (the canonical skeleton — intro, label legend, Prerequisites, Static checks, Cleanup, Known limitations):

1. Fill the blockquote intro with a behavior summary from the diff/spec.
2. Emit the mapped steps in dependency order, each `## N. Title [LABEL]` + command + `**Expected:**`. Apply [references/label-policy.md](references/label-policy.md) to label each step, and [references/real-data-grounding.md](references/real-data-grounding.md) to make every Expected value concrete (query the dev DB; if the needed rows are absent, the step seeds a deterministic fixture and bakes that id).
3. Add an **audit-trail verification** step whenever the diff touches queued jobs or event ingestion (query the relevant log/event table chronologically — the retrospective record).
4. Add a **bold ordering caveat** to any step that drives a state machine (e.g. "do B only after A, because …") so the run order that actually exercises the change is unambiguous.
5. Add a single `[PRODUCTION-AFFECTING]` live step **only** when an external integration is touched, referencing the repo-level `scripts/tunnel.sh` for any tunnel need — never inline a raw `ngrok` command.
6. Fill Known limitations from the spec's out-of-scope table.

Write the file to the Step 1 path.

### Step 4 — Run the QA

1. Run the `[READ-ONLY]` and `[MUTABLE]` steps yourself, in order, comparing actual output to each `**Expected:**`. Report pass/fail per checkpoint.
2. For `[PRODUCTION-AFFECTING]` and browser-UI steps, present the exact instructions and wait for the user to run them and report results — never auto-run them.
3. The QA **succeeds** when every automatable checkpoint matches and the user confirms the manual ones. If a checkpoint fails, report it with the actual vs expected and stop for a fix (route real bugs to `pr-execute`); do not commit a failing artifact.

### Step 5 — Commit or delete

On success, ask the user to choose:

- **Commit to the PR** — stage **only** the QA file, then a scoped Conventional Commit and push to the PR branch:
  ```bash
  git add tests/qa/<pr_number>-<slug>/QA-<pr_number>-<slug>.md
  git commit -m "docs(qa): add manual QA for PR #<pr_number> (<slug>)"
  git push
  ```
  Follow `kappy-finalize`'s hygiene: no AI/authorship trailers; never stage unrelated dirty files.
- **Delete** — remove the generated file (and its `tests/qa/<pr_number>-<slug>/` directory if now empty) so no artifact is committed.

Do not pick for the user; wait for the choice.

## Hard rules

- The artifact path/name **must** be `tests/qa/<pr_number>-<slug>/QA-<pr_number>-<slug>.md` — both PR number and branch slug.
- **No PII** anywhere in the artifact.
- Every `**Expected:**` is falsifiable (real or seeded ids/counts) — never a placeholder.
- `[PRODUCTION-AFFECTING]` steps are manual-only; never auto-run.
- Never commit a failing QA; the commit stages only the QA file.
- This skill never edits source code — real bugs found during the run go to `pr-execute`.

## Examples

### Auto-chain after finalize

`kappy-finalize` opens PR #12 on `feat/billing-webhooks` and hands off. Resolve number 12 + diff → generate `tests/qa/12-billing-webhooks/QA-12-billing-webhooks.md` (webhook receiver → signature/idempotency steps + audit-trail; Stripe integration → one `[PRODUCTION-AFFECTING]` live step via `scripts/tunnel.sh`) → run the local steps → on success ask commit-or-delete.

### Manual invocation

User: "gera o QA manual do PR 10". Resolve PR 10 (`feat/github-event-ingestion`) → `tests/qa/10-github-event-ingestion/QA-10-github-event-ingestion.md` → run steps 1–N locally → present the live step → commit on confirmation.

## Troubleshooting

### No open PR / no number

Stop and route to `kappy-finalize` to open the PR first — the artifact name requires the number.

### The dev DB has no rows to ground an Expected value

Do not emit an abstract Expected. Have the step seed a deterministic fixture (factory) and bake that concrete id/count — see [references/real-data-grounding.md](references/real-data-grounding.md).

### A checkpoint fails during the run

Report actual vs expected; do not commit. If it's a real defect, hand off to `pr-execute`; if it's a stale artifact (branch changed), regenerate (Step 3).

### The diff has no testable surface (docs/config only)

Generate a minimal artifact (Prerequisites + automated-suite run + static checks) or tell the user a QA artifact adds no value here and offer to skip.
