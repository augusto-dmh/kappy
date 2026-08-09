---
name: kappy-ship-cycle
description: 'End-to-end orchestrator for one Kappy PR: pick or receive the cycle goal, run a tlc-spec-driven cycle auto-selecting recommended options, publish the PR with kappy-finalize, run pr-review in a fresh-context subagent (local findings only — never posts to GitHub), triage with kappy-review-triage, apply accepted fixes, and merge after a single user approval. Use when asked to "ship the next PR", "run the ship cycle", "roda o ship cycle", or to resume a partially shipped cycle. Not for ad-hoc edits, standalone reviews (pr-review / kappy-code-review), triage-only runs (kappy-review-triage), or publishing-only work (kappy-finalize).'
license: CC-BY-4.0
metadata:
  author: Kappy contributors
  version: 1.1.0
---

# Kappy Ship Cycle — Orchestration Protocol

Runs one cycle from "what are we shipping?" to merged, replacing three manual sessions (tlc build → code review → triage/fix/merge) with one orchestrated pipeline. This skill owns only the glue; the work itself is delegated to `tlc-spec-driven`, `kappy-finalize`, `pr-review`, and `kappy-review-triage` unchanged.

**Autonomy contract:** the pipeline runs without user prompts except at exactly two gates — the triage disposition table (Stage 4, inherited from `kappy-review-triage`) and merge approval (Stage 7) — plus the escalation rule in Stage 1. Everything else proceeds on the recommended option, logged for audit.

**Review delivery (binding, tally-aligned):** Stage 3 uses `pr-review` and writes only under `pr-review-{N}/`. **Never** post review findings, summaries, or disposition follow-ups to GitHub (`gh pr comment`, `gh pr review`, `gh api …/comments` create/reply). Local files + `.specs/.../review-triage.md` are the durable record.

**Continuation contract (binding):** never end a turn "standing by", "awaiting your call", or asking "want me to proceed?" between stages — finish the stage, update the heartbeat, and start the next stage in the same turn. Anything that needs the user's eyes (e.g. a browser check) is deferred to the Stage 7 report, not raised as a mid-cycle pause. The only allowed stops are: the two gates above, a failed verification gate, and a hard blocker only the user can clear (state which one when stopping).

**Heartbeat (`.specs/.ship-status`):** at every stage transition, overwrite `.specs/.ship-status` with a single line: `<cycle> | Stage <N> <name> | <branch or PR #> | <timestamp> | <one-line status>`. This is how the user (or a parallel session) answers "what is going on?" without archaeology. Also update it when parking on an outage or limit. Delete the file at Stage 8.

**Resume contract:** on any session start or post-interruption message (a bare "continue" suffices — do not ask what to do), read `.specs/.ship-status`; if it shows a mid-stage cycle, run Stage Detection and resume immediately in that same turn.

## Run modes (optional skill argument)

- `once` (default) — stop at both gates.
- `auto` — this invocation is the user's standing approval for both gates: apply the recommended triage dispositions without presenting the table, and when the Stage 7 report is clean (gates green, threads closed) merge without asking. Still stop for a failed gate, an escalation-rule decision, or a dirty report.

The cycle goal may be passed as the remaining argument (e.g. `ship-cycle once "webhook retry backoff"`). The mode holds for the whole invocation; do not re-ask it mid-run.

## Stage Detection (always run first)

The pipeline is resumable. Check `.specs/.ship-status` first — if it shows an in-flight cycle (possibly from another session), resume that cycle rather than starting a new one; two concurrent cycles on one repo is always a mistake. Then determine the current stage:

| Observation | Resume at |
|---|---|
| Clean `main`, no in-flight cycle | Stage 0 |
| Cycle branch exists, tlc Execute incomplete (`.specs/features/<cycle>/`) | Stage 1 (tlc resume) |
| Gates green on branch, no open PR | Stage 2 |
| PR open, no `pr-review-{N}/` (or empty of findings/`_summary.md`) | Stage 3 |
| `pr-review-{N}/` present, no `.specs/features/<cycle>/review-triage.md` | Stage 4 |
| `review-triage.md` exists, accepted fixes not yet pushed | Stage 5 |
| Fixes pushed; triage complete (no GitHub thread work — local-only reviews) | Stage 7 (skip Stage 6) |
| Legacy: GitHub review threads still open from an old posting path | Stage 6 only if such threads exist; otherwise Stage 7 |

Announce the detected stage and the cycle/PR it applies to before proceeding.

## Stage 0 — Preflight

1. Require a clean working tree. If dirty, stop and report — never stash or discard.
2. `git checkout main && git pull`.
3. Determine the cycle: the skill argument if given; otherwise `.specs/project/ROADMAP.md` if one exists (first row not started); otherwise ask the user (AskUserQuestion) for the cycle goal — this is the one permitted Stage 0 prompt. Read the Handoff/Decisions sections of `.specs/project/STATE.md` for standing constraints.
4. State the chosen cycle and the intended slice in one short paragraph, then continue — no approval needed.

## Stage 1 — Plan & Build (tlc-spec-driven)

Invoke `tlc-spec-driven` for the cycle (Specify → Design → Tasks → Execute per its auto-sizing).

**Auto-decision rule** (replaces the human answering Discuss questions): at every decision point, formulate the options — each with why-recommend AND why-not — pick the recommended one, and record option set, choice, and rationale in the cycle's **local** spec artifacts and as a dated decision row in **local** `.specs/project/STATE.md` (never commit those files in the product PR). The decision must be auditable later in the working tree without the conversation. A decision that meets the bar of an architecture decision (module boundary, dependency direction, external service) additionally gets an ADR via `create-adr` in `.specs/adr/` — and only then may an ADR file be committed, in an ADR-focused PR when the user asks.

**Escalation rule** — ask the user (AskUserQuestion) instead of auto-deciding only when:
- the decision changes product direction or scope beyond the cycle,
- it adds or locks in a dependency (CLAUDE.md forbids changing dependencies without approval), or
- no option is defensible as recommended.

**Kappy-specific traps every worker brief must carry** (from `.specs/project/STATE.md`):
- After composer-affecting work, `composer install --dry-run` must be a no-op; never let an agent blanket-`git restore` tracked files (this once silently reverted the modular setup).
- `make:module` probes must be cleaned up — the final gate asserts `ls app-modules/` contains only intended modules.
- The `module::page` convention lives in BOTH `resources/js/app.tsx` and `resources/views/app.blade.php`; changes to one must keep the other in sync.
- New behavior without co-located Pest tests in the same branch is a review blocker, never a follow-up.

**Gate (green before any stage advances):** `php artisan test --compact` (runs pint via `composer test` where applicable), `vendor/bin/pint --dirty --format agent`, `npm run lint`, `npm run types:check`, `npm run build`. Scope intermediate task commits to the affected module's tests; run the full gate at phase boundaries and before every push. A failed gate stops the pipeline with the report — do not continue to Stage 2.

## Stage 2 — Publish (kappy-finalize)

Invoke `kappy-finalize` for branch, Conventional Commit, push, and the ready-for-review PR (its PR-body conventions apply — behavior-focused prose, no internal IDs).

**`.specs` is local-only (binding):** never stage, commit, or push `.specs/features/**`, `.specs/project/STATE.md` updates, `.specs/.ship-status`, or triage/validation/handoff docs in a product PR. Those files exist for the agent on disk only (gitignored / left unstaged). Capture the PR number for all later stages.

## Stage 3 — Review (fresh context, author ≠ reviewer)

Spawn ONE subagent via the Agent tool (`general-purpose`, fresh context) with a prompt containing only: the repo, the PR number, and the instruction to invoke the project-local `pr-review` skill for that PR and follow it exactly.

**Hard rule:** the brief must say findings stay under `pr-review-{N}/` and **must not** be posted to GitHub. Do **not** pass a "standing confirmation to post" or invoke `kappy-code-review`'s posting step.

**Do not** pass implementation context, spec content, or this session's reasoning into the subagent — the reviewer's independence is the point of the fresh context. Wait for it to finish; its deliverable is `pr-review-{N}/_summary.md` (+ finding files), returned as a compact path summary in chat.

## Stage 4 — Triage (kappy-review-triage)

Invoke `kappy-review-triage` for the PR against **local** `pr-review-{N}/` findings. It evaluates each finding with one sub-agent per finding, then presents the disposition table — in `once` mode this is a real gate (the user approves/edits dispositions); in `auto` mode apply the recommended dispositions and log them. Findings that misread the code, duplicate an accepted decision (ADR / STATE.md row), or trade against recorded scope decisions are `[INVÁLIDO]` with the reason.

Persist the triage to `.specs/features/<cycle>/review-triage.md` **locally only** (gitignored — never `git add`) before touching code: one row per finding — source file, file:line, verdict, disposition, rationale. **Do not** run `post_dispositions.py` / post follow-ups to GitHub (normal path: no threads).

## Stage 5 — Fix

Apply every accepted-fix finding. Group into atomic Conventional Commits per `kappy-finalize` rules (plain-language messages, no internal IDs, no AI attribution). Re-run the full Stage 1 gate before pushing. Push to the PR branch, then have `kappy-finalize` re-sync the PR body if the add-up changed the PR's scope (its add-up rule).

## Stage 6 — Close the Loop (legacy GitHub threads only)

**Default: skip.** Local `pr-review` leaves nothing on the PR. Advance to Stage 7 after Stage 5 when triage is persisted and fixes (if any) are pushed.

Only if unresolved GitHub review threads still exist from an older posting path: post the follow-ups `kappy-review-triage` prepared (`[RESOLVED]` / `[ADIADO]` / `[INVÁLIDO]`) and resolve threads per that skill. Prefer deleting erroneous agent-posted comments when the API allows, rather than leaving a public trail.

## Stage 7 — Merge Gate (the one remaining user prompt)

Present a compact ship report: cycle, PR number, gate results, triage counts (real/false, fixed/deferred/invalid), fix commits, local review path (`pr-review-{N}/`), and anything deferred to the user's eyes (e.g. a browser check). In `once` mode, ask the user (AskUserQuestion): merge now or hold. In `auto` mode with a clean report, merge without asking.

On approval: `gh pr merge {N} --merge` (merge commit, matching this repo's history), then `git checkout main && git pull` and delete the local feature branch.

## Stage 8 — Wrap

Update `.specs/project/STATE.md` **locally only** (never commit it from a ship cycle / product PR) if anything new surfaced for the next session. Delete `.specs/.ship-status`. End the wrap report with: (a) the cycle closed + PR merged; (b) the suggested next cycle in one line, if one is apparent from local STATE.md or an existing roadmap. Do not start the next cycle automatically — the next run of this skill picks it up.

## Delegation resilience (Stages 1 and 3)

**Idle protocol (bounded):** an idle notification from a worker/reviewer WITHOUT a completion summary is a stall, not completion. Check observable progress first (Stage 3: `pr-review-{N}/_summary.md` + finding file counts on disk — never GitHub comment counts; Stage 1: the worker's reported commits/task state). If below expectation, send exactly ONE nudge naming what is missing. If a second idle arrives with no new progress, stop the agent and re-dispatch a fresh one with the same brief. No wakeup loops whose only purpose is re-nudging.

**Limit-death degradation:** a failed delegation citing a session/usage limit → re-dispatch once. If that also fails, execute that stage's remaining work inline in this session, in the same turn, and record the deviation (e.g. "author = reviewer this cycle") in `.specs/project/STATE.md`. If this session is itself rate-limited, write the heartbeat with exact resume instructions before stopping.

**Outages:** on 2+ consecutive provider 5xx or `gh` connection errors, check the status page once. If a real outage is confirmed: write the heartbeat, schedule ONE long wakeup, and park with a one-line "waiting on <provider>, resuming ~HH:MM" message. Never blocking poll loops, never blind retries.

## Worker briefs — state the goal, not the steps

A delegated worker's brief gives it **what must be true when it finishes**, and leaves *how* to the worker. Always give (context, not prescription):

- The **seams** — signatures and `file:line` refs from an Explore survey, not file bodies.
- The **binding decisions** — the ADRs and STATE.md rows the work must conform to.
- The **invariants that must hold**, named as invariants, each requiring a Pest sensor; do not dictate the test's shape.
- **Environment facts** — the gate commands above, services that must run, the composer dry-run guard.
- The **non-negotiable contract** — tests derive from acceptance criteria, gate green before done, one atomic commit per task, no attribution, no internal IDs.
- The **known traps** listed in Stage 1 — knowledge a worker cannot derive from the seams.

Don't give: an ordered list of edits, an enumerated list of tests, or a solution to transcribe.

## Hygiene (applies to every stage)

- No AI/tooling attribution anywhere public (commits, PR, comments).
- **Never commit `.specs/` planning artifacts in a product PR** — `.specs/features/**` (except the historical `modular-monolith` tree already on main), `.specs/.ship-status`, `review-triage.md`, `validation.md`, `handoff*.md`, and `STATE.md` edits stay local. Do not `git add` them. ADRs under `.specs/adr/` only when the user explicitly asked for an ADR PR.
- No internal IDs (task/FR/cycle/gate) in commits, PR bodies, or PR comments — they live only under local `.specs/`.
- Multiline `gh` bodies go through `--body-file`/`-F body=@file`, never `-f body=@file`.
- Never post review findings or summaries to GitHub (`gh pr comment`, `gh pr review`, create/reply on `…/pulls/…/comments`). Use local `pr-review-{N}/` only. `gh pr review` bodies cannot be fully deleted — do not create them.
- Wait on CI with `gh pr checks <N> --watch` as a background task — never `sleep N && gh …`.
- Bash cwd can reset between calls: use absolute paths, run git from the repo root, Read any existing file before Edit/Write. Pass these rules into every worker/reviewer brief.
- Subagents return compact final text — never report files; the orchestrator must be able to Read what comes back.
