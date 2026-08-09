---
name: kappy-review-triage
description: >-
  Evaluates findings from a local pr-review-{N}/ directory for a Kappy PR — one
  sub-agent per finding checks, against the current code and git history, whether
  the finding is real, whether it is worth acting on, and why — then after your
  manual review writes dispositions to .specs (and optional follow-ups only if
  matching GitHub threads already exist). Triggers on 'avalia os findings do PR N',
  'triage and resolve the review comments', 'resolve os comentários do code review',
  'evaluate the PR findings'. Runs after pr-review. Do NOT use to produce the
  initial findings (pr-review), implement fixes (pr-execute), generate a QA
  artifact (kappy-manual-qa), or commit/open the PR (kappy-finalize).
license: CC-BY-4.0
metadata:
  author: Kappy contributors
  version: 1.1.0
---

# Kappy Review Triage

Evaluates findings produced by `pr-review` under `pr-review-{N}/` — one sub-agent per finding, grounded in the current code and git history — then, after your manual review, records dispositions. It does not produce findings itself and never edits application source.

**Primary input:** local files under `pr-review-{N}/` (`<!-- ANCHOR: path:line -->` + `<!-- pr-review:TYPE -->`).  
**GitHub threads:** optional legacy only — if unresolved PR review threads already exist, follow-ups may be posted there after confirmation; otherwise nothing is posted to GitHub.

## Operating principles

- **One sub-agent per finding.** Each finding is evaluated independently and adversarially: is it real against the *current* code? is it worth acting on, and why? was it already fixed, and by which commit? See [references/evaluation-rubric.md](references/evaluation-rubric.md).
- **Manual-review gate before any durable write.** Present the full disposition table and wait for approval/edits. Persist / post only on explicit confirmation.
- **Four dispositions**, in the project pattern (pt-BR). See [references/disposition-policy.md](references/disposition-policy.md):
  - `[RESOLVED]` — real and fixed → cite the commit.
  - `[ADIADO]` — real but not worth acting on now.
  - `[INVÁLIDO]` — not a real issue.
  - real, worth acting, but **not yet fixed** → **flag for fix work**, no `[RESOLVED]` claim.
- **Auto-detect the fix commit, never guess.** Derive the `[RESOLVED]` SHA from the anchor's git history after the review date; if several commits could be it, surface the candidates at the gate instead of guessing.
- **Never edit source.** A real-but-unfixed finding is routed to implementation; this skill only evaluates and dispositions.

## When to use

- After `pr-review` wrote `pr-review-{N}/` (and any fixes were committed): "avalia os findings do PR 10", "triage and resolve the review comments", "evaluate the PR findings".

## When NOT to use

- Producing the initial review findings → `pr-review`.
- Implementing a fix for a finding → hand off to implementation / `pr-execute`.
- Generating a QA artifact → `kappy-manual-qa`.
- Committing/opening the PR → `kappy-finalize`.

## Workflow

### Step 0 — Load findings (local first)

Resolve the PR number (ask if not given).

1. **Local (required path):** if `pr-review-{N}/` exists at the repo root, load every finding file under `security/`, `tests/`, `architecture/`, `regression/`, `performance/` (exclude `_highlight.md`). Parse `<!-- ANCHOR: path:line -->`, `<!-- pr-review:TYPE -->`, and the body. Requirements summary is context only — not a disposition row unless it contains ❌/🟡 items the user wants triaged.
2. **GitHub (optional):** if local dir is missing, or the user asks to close existing threads, fetch unresolved review threads. Keep threads whose root body contains `<!-- pr-review:` (or legacy `<!-- kappy-review:`). Map them by `{path, line}` onto local findings when both exist.

```bash
gh api graphql -f query='
{ repository(owner:"<owner>", name:"<repo>"){ pullRequest(number:<n>){ reviewThreads(first:50){ nodes{
  id isResolved isOutdated path line
  comments(first:1){ nodes{ databaseId createdAt body } } } } } } }'
```

If neither local findings nor matching threads exist → stop and tell the user to run `pr-review` first.

### Step 1 — Evaluate each finding (one sub-agent per finding)

Fan out one sub-agent per finding (parallel Task calls). Give each the finding (path, line, marker type, body, source file path under `pr-review-{N}/`) and the instructions in [references/evaluation-rubric.md](references/evaluation-rubric.md). Each returns: `real` (yes/partial/no), `worth` (must-fix/should-fix/optional/skip), `why`, `fixed` (bool), `fix_commit` (sha or null, **auto-detected from git history**, or a short candidate list if ambiguous), and a proposed `disposition`.

### Step 2 — Assign dispositions

Map each verdict with [references/disposition-policy.md](references/disposition-policy.md):

| Verdict | Disposition |
| --- | --- |
| real & fixed (commit found) | `RESOLVED` |
| real & worth & not fixed | `FLAG` → route to fix work |
| real & not worth (optional/skip) | `ADIADO` |
| not real | `INVÁLIDO` |

If `fix_commit` is ambiguous for a `RESOLVED`, mark it "needs SHA confirmation" for the gate.

### Step 3 — Manual-review gate

Present a table: finding (path:line + short title) · disposition · why · commit (for RESOLVED) or route (for FLAG). **Wait for the user to review, edit, and approve.** Nothing is persisted until they confirm.

### Step 4 — Persist on confirmation

1. **Always** write `.specs/features/<cycle>/review-triage.md` **locally only** (create the feature dir if the cycle is known; otherwise `pr-review-{N}/review-triage.md`) with one row per finding: source file, `file:line`, verdict, disposition, rationale. **Never `git add` triage or other `.specs/features/**` files.**
2. **GitHub follow-ups: do not post.** Local `pr-review` leaves no threads. Skip `post_dispositions.py` entirely. Only if the user explicitly asks to clean up **legacy** threads from an old posting path may follow-ups be posted — never as part of the default triage flow.
3. Hand `FLAG` items to implementation as fix work.

## Hard rules

- **Never persist before the Step-3 confirmation.**
- **Never mark `[RESOLVED]`** without a concrete fix commit (auto-detected or user-confirmed).
- **Never post triage follow-ups to GitHub** on the default path (tally-aligned local reviews).
- **Never edit source code.**
- Prefer local `pr-review-{N}/` as the source of truth for what to triage.

## Examples

### Triage after a local pr-review

"avalia os findings do PR 12." → load `pr-review-12/**` → one sub-agent each → dispositions approved → write `review-triage.md`. Nothing posted to GitHub.

### A finding that's real but not yet fixed

Disposition `FLAG` → listed for fix work; no `[RESOLVED]` claim. After the fix lands, re-run triage.

## Troubleshooting

### No local findings

Confirm the PR number; run `pr-review` first so `pr-review-{N}/` exists.

### Ambiguous fix commit

Surface candidates at the Step-3 gate; pick the SHA before marking `RESOLVED`.
