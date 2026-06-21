---
name: kappy-review-triage
description: Evaluates the review findings already posted on a Kappy PR — one sub-agent per finding checks, against the current code and git history, whether the finding is real, whether it is worth acting on, and why — then after your manual review posts a follow-up on each thread in the project's resolution pattern — [RESOLVED] (fixed, citing the auto-detected commit) and resolve the thread, [ADIADO] (real but deferred) kept open, [INVÁLIDO] (not a real issue) and resolve; a real finding that is not fixed yet is flagged for pr-execute and gets no comment. Triggers on 'avalia os findings do PR N', 'triage and resolve the review comments', 'resolve os comentários do code review', 'evaluate the PR findings'. Runs after kappy-code-review or pr-review have posted findings. Do NOT use to post the initial findings (kappy-code-review or pr-review), implement fixes (pr-execute), generate a QA artifact (kappy-manual-qa), or commit/open the PR (kappy-finalize).
license: CC-BY-4.0
metadata:
  author: Kappy contributors
  version: 1.0.0
---

# Kappy Review Triage

Evaluates the review findings already on a Kappy PR — one sub-agent per finding, grounded in the current code and git history — then, after your manual review, closes the loop on each thread using the project's resolution pattern. It runs *after* `kappy-code-review` / `pr-review` have posted findings; it does not post findings itself and never edits source.

## Operating principles

- **One sub-agent per finding.** Each finding is evaluated independently and adversarially: is it real against the *current* code? is it worth acting on, and why? was it already fixed, and by which commit? See [references/evaluation-rubric.md](references/evaluation-rubric.md).
- **Manual-review gate before any GitHub write.** The skill presents the full disposition table and waits for your approval/edits. It posts and resolves only on explicit confirmation.
- **Four dispositions, one follow-up per thread**, in the project pattern (pt-BR). See [references/disposition-policy.md](references/disposition-policy.md):
  - `[RESOLVED]` — real and fixed → cite the commit, resolve the thread.
  - `[ADIADO]` — real but not worth acting on now → keep the thread open.
  - `[INVÁLIDO]` — not a real issue → resolve the thread.
  - real, worth acting, but **not yet fixed** → **flag for `pr-execute`, post no comment, leave the thread untouched** (keeps `[RESOLVED]` truthful).
- **Auto-detect the fix commit, never guess.** Derive the `[RESOLVED]` SHA from the anchor's git history after the review date; if several commits could be it, surface the candidates at the gate instead of guessing.
- **Never edit source.** A real-but-unfixed finding is routed to `pr-execute`; this skill only evaluates and dispositions.

## When to use

- After `kappy-code-review` / `pr-review` posted findings (and any fixes were committed): "avalia os findings do PR 10", "triage and resolve the review comments", "resolve os comentários do code review".

## When NOT to use

- Posting the initial review findings → `kappy-code-review` or `pr-review`.
- Implementing a fix for a finding → `pr-execute`.
- Generating a QA artifact → `kappy-manual-qa`.
- Committing/opening the PR → `kappy-finalize`.

## Workflow

### Step 0 — Load the open findings

Resolve the PR number (ask if not given). Fetch the **unresolved** review threads that carry the `<!-- kappy-review:TYPE -->` marker, with each thread's node id, root comment id + body, path, and line:

```bash
gh api graphql -f query='
{ repository(owner:"<owner>", name:"<repo>"){ pullRequest(number:<n>){ reviewThreads(first:50){ nodes{
  id isResolved isOutdated path line
  comments(first:1){ nodes{ databaseId createdAt body } } } } } } }'
```

Keep only threads where `isResolved == false` and the root comment body starts with `<!-- kappy-review:`. Skip everything else (already handled or not a review finding).

### Step 1 — Evaluate each finding (one sub-agent per finding)

Fan out one sub-agent per finding (Workflow tool, or parallel Task calls). Give each the finding (path, line, marker type, body) and the instructions in [references/evaluation-rubric.md](references/evaluation-rubric.md). Each returns a structured verdict: `real` (yes/partial/no), `worth` (must-fix/should-fix/optional/skip), `why`, `fixed` (bool), `fix_commit` (sha or null, **auto-detected from git history**, or a short candidate list if ambiguous), and a proposed `disposition`.

### Step 2 — Assign dispositions

Map each verdict with [references/disposition-policy.md](references/disposition-policy.md):

| Verdict | Disposition |
| --- | --- |
| real & fixed (commit found) | `RESOLVED` (+ resolve) |
| real & worth & not fixed | `FLAG` → route to `pr-execute`, no comment |
| real & not worth (optional/skip) | `ADIADO` (+ keep open) |
| not real | `INVÁLIDO` (+ resolve) |

If `fix_commit` is ambiguous for a `RESOLVED`, mark it "needs SHA confirmation" for the gate.

### Step 3 — Manual-review gate (no GitHub writes yet)

Present a table: finding (path:line + short title) · disposition · why · commit (for RESOLVED) or route (for FLAG). **Wait for the user to review, edit, and approve.** The user may override any disposition. Nothing is posted until they confirm.

### Step 4 — Post on confirmation

Build a plan JSON of the approved dispositions and run the poster:

```bash
.claude/skills/kappy-review-triage/scripts/post_dispositions.py <pr-number> <plan.json>
```

It posts each `[RESOLVED]`/`[ADIADO]`/`[INVÁLIDO]` follow-up (pt-BR, exact format) and resolves the RESOLVED/INVÁLIDO threads; `ADIADO` stays open; `FLAG` items are not posted. Run it with `--dry-run` first to print exactly what will be posted. Then, for any `FLAG` items, hand them to `pr-execute` as the fix work.

The plan JSON shape (the poster validates it):

```json
{ "repo": "owner/name",
  "items": [
    { "comment_id": 123, "tag": "RESOLVED", "body": "[RESOLVED] Resolvido em e38ab3a — …", "resolve": true },
    { "comment_id": 124, "tag": "ADIADO",   "body": "[ADIADO] Mantido aberto de propósito. …", "resolve": false },
    { "comment_id": 125, "tag": "INVALIDO", "body": "[INVÁLIDO] Não procede — …", "resolve": true } ] }
```

## Hard rules

- **Never post or resolve before the Step-3 confirmation.**
- **Never mark `[RESOLVED]`** without a concrete fix commit (auto-detected or user-confirmed). When unsure of the SHA, surface candidates — do not guess.
- **One follow-up comment per thread**, pt-BR, using the exact `[RESOLVED]`/`[ADIADO]`/`[INVÁLIDO]` formats in `disposition-policy.md`.
- **Never edit source code.** Real-but-unfixed findings go to `pr-execute`; they get no thread comment here.
- **Only touch `kappy-review` threads** that are still unresolved.

## Examples

### Triage a reviewed PR with some fixes already landed

"avalia os findings do PR 10." → load the 8 unresolved `kappy-review` threads → one sub-agent each → two are fixed (commits auto-detected) → `RESOLVED`; six are optional/skip → `ADIADO`. Present the table; on approval, post the eight follow-ups and resolve the two. Matches the pattern of the last merged PR.

### A finding that's real but not yet fixed

A sub-agent finds a real, worth-fixing bug with no addressing commit → disposition `FLAG`. The skill posts no comment on that thread and lists it for `pr-execute`. After the fix lands, re-run and it becomes `RESOLVED`.

## Troubleshooting

### No `kappy-review` threads found

The PR has no posted findings (or they're already resolved). Confirm the PR number; if findings were never posted, run `kappy-code-review` / `pr-review` first.

### Ambiguous fix commit

Several commits touched the finding's file after the review. The sub-agent returns the candidates; resolve it at the Step-3 gate by picking the SHA, then post.

### The poster reports a 401/permission error

Run `gh auth status`. Resolving threads needs the GraphQL `resolveReviewThread` scope that `gh` provides when authenticated as the repo owner/collaborator.
