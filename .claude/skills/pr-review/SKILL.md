---
name: pr-review
description: >-
  Reviews a Kappy GitHub pull request with six specialized subagents in parallel,
  then consolidates findings into a local ephemeral directory under pr-review-{PR}/
  (never posts to GitHub). Grounds every lens in this repo's modular layout
  (app-modules/, co-located Pest tests, .specs/, mirrored skills). Use when the
  user says "review PR 128", "review this PR", "code review this PR", "pr-review",
  "revisa esse PR", or "check this pull request". Do NOT use for creating PRs,
  posting review comments to GitHub, implementing fixes, or debugging failing CI.
license: CC-BY-4.0
disable-model-invocation: true
metadata:
  author: github.com/augusto-dmh
  version: '1.2.0'
  upstream: tech-leads-club/agent-skills packages/skills-catalog/skills/(quality)/pr-review
  adaptation: kappy-local-ephemeral
---

# PR Review — Kappy Orchestration Protocol (local / ephemeral)

Coordinates 6 specialized subagents (via the Task tool), then consolidates their findings into **one local review directory**. This skill owns no Laravel/React rules of its own — each subagent loads **this repo's** convention docs and domain skills and grades the diff against those. It never invents stack idioms and never duplicates skill text into findings.

**Binding adaptations for Kappy:**

1. Findings are written under `pr-review-{PR}/` at the repo root and are **never** posted to GitHub (`gh api …/comments`, `gh pr review`, `gh pr comment` for review bodies are forbidden).
2. Discovery is fixed to **this repository's organization** (see Repository Map). Subagents still load only paths that exist; mark missing paths `none`.
3. At the end of the run the orchestrator asks whether `pr-review-{PR}/` may be deleted.

## Repository Map (how Kappy is organized)

Pass this map into every subagent prompt so they navigate the right trees:

```
LAYOUT:
  Domain PHP + co-located FE/tests → app-modules/<module>/
    src/            Controllers, Models, Jobs, Services, Contracts, Enums, Scm, Providers, Dto, …
    routes/         module route files
    database/       migrations, factories, seeders
    tests/Feature   Pest Feature tests (preferred home for new module behavior)
    tests/Unit      Pest Unit tests
    resources/js/   co-located Inertia React pages/components for that module
    resources/prompts/  (when present) LLM prompts owned by the module
  Shared app core (NOT modules) → app/  (Fortify auth, Settings, …)
  Global FE shared layer → resources/js/{pages,components,hooks,lib,layouts}
  Specs → .specs/features/<feature>/{spec,design,tasks}.md
           .specs/project/STATE.md
           .specs/adr/  (when present)
  Skills (mirrored; prefer .agents/skills/ as canonical path in prompts) →
           .agents/skills/ | .claude/skills/ | .codex/skills/

INVARIANTS (from STATE.md / project practice — treat as review blockers when broken):
  - New behavior/wiring/validation/route surface without co-located Pest tests in the same PR → 🚨
  - After composer-affecting work, `composer install --dry-run` must be a no-op
  - `module::page` lives in BOTH resources/js/app.tsx AND resources/views/app.blade.php — keep in sync
  - `ls app-modules/` must contain only intended modules (no leftover make:module probes)
  - Modules do not import other modules' internals; FE modules → `@/` shared only (no module→module)

GATE COMMANDS (CI / local truth):
  php artisan test --compact
  vendor/bin/pint --dirty --format agent
  npm run lint && npm run types:check && npm run build
```

Known modules today (re-check with `ls app-modules/` each run): `catalog`, `github-app`, `identity`, `review`.

## Execution Contract (NON-NEGOTIABLE)

This skill is an **orchestration-only** protocol. Read these rules before anything else:

1. You are the **orchestrator**. You MUST NOT analyze the diff, read source files for findings, or write any review finding yourself. Your only jobs are gathering context (Step 1), preparing `{OUT_DIR}` (Step 1c), launching subagents (Step 2), spawning the consolidation subagent (Step 3), presenting the summary, and asking about cleanup (Step 4).
2. The six reviews MUST run as **six separate, general-purpose subagents** (no restricted toolset), launched **in parallel in a single batch**. Use whatever subagent / parallel-task mechanism your harness provides (Task tool).
3. Doing the review inline — even partially, even "to save time", even for a small diff — is a FAILURE of this skill. There are no exceptions for diff size.
4. Each subagent prompt MUST be self-contained: `{REPO}`, `{PR}`, `{SHA}`, `{OUT_DIR}`, the diff, the PR intent, existing comment locations (dedupe / local resolved notes only), the DISCOVERY MAP, and the Repository Map above. Subagents do not share your context.
5. **No GitHub writes.** Subagents and consolidation MUST NOT call any GitHub mutating endpoint. Read-only `gh` (`pr view`, `pr diff`, `api …/comments` GET) is allowed in Step 1 and for gap detection. Findings live only under `{OUT_DIR}`.

**Host prerequisite:** GitHub PRs via `gh` for **read** context.

Explicit invocation only (`disable-model-invocation: true`). Never auto-trigger during coding.

## Step 1: Initialize

### 1a. PR context

1. Resolve PR number from the user's request; ask if absent.
2. Identify repo: `gh repo view --json nameWithOwner -q .nameWithOwner` → `{REPO}`.
3. Fetch the diff: `gh pr diff {PR}`. Get the head SHA: `gh pr view {PR} --json headRefOid -q .headRefOid` → `{SHA}`.
4. Load existing inline comments (read-only): `gh api repos/{REPO}/pulls/{PR}/comments` — `{id, path, line}` for local dedupe; bodies for local `resolved/` notes only (never reply on GitHub).
5. Read PR intent: `gh pr view {PR} --json title,body,headRefName`.
6. Confirm working tree root is the Kappy repo (must contain `app-modules/` and `AGENTS.md`).

### 1b. Discovery map (Kappy-fixed; verify paths exist)

Probe once. Prefer evidence on disk over the defaults below. Mark anything missing as `none`.

**Default map for this repo (verify, then pass verbatim):**

```
TEST:          php artisan test --compact
               globs: tests/**/*.php, app-modules/*/tests/**/*.php
               split: Pest Feature → */tests/Feature ; Unit → */tests/Unit
               CI: .github/workflows/tests.yml (authoritative), .github/workflows/lint.yml
REQS:          tracker=<from branch/PR body or none>
               specs=.specs/features/<matching-feature>/{spec,design,tasks}.md ;
                     .specs/project/STATE.md ;
                     .specs/adr/** (if present)
CONVENTIONS:   AGENTS.md, CLAUDE.md, SKILLS.md, .specs/project/STATE.md
REVIEW_SKILLS: (only paths that exist — use .agents/skills/ as the path written into the map)
               .agents/skills/laravel-best-practices/SKILL.md
               .agents/skills/laravel-best-practices/rules/*.md   (security, db-performance, testing, …)
               .agents/skills/pest-testing/SKILL.md
               .agents/skills/modular-design-principles/SKILL.md
               .agents/skills/fortify-development/SKILL.md
               .agents/skills/inertia-react-development/SKILL.md
               .agents/skills/wayfinder-development/SKILL.md
               .agents/skills/tailwindcss-development/SKILL.md
MODULES:       <output of ls app-modules/>
DIFF_TOUCHES:  <modules and shared areas touched by the PR — e.g. review, app/, resources/js/>
```

Match the PR branch / title to a `.specs/features/<feature>/` directory when possible and put that path in `REQS.specs`.

Each subagent loads **only** what the map lists for its lens; when a category is `none`, fall back to the generic checklist in its section and say so.

### 1c. Local output directory (ephemeral)

```
{OUT_DIR} = <repo-root>/pr-review-{PR}/
```

Create:

```
pr-review-{PR}/
  INDEX.md
  security/
  requirements/
  tests/
  architecture/
  regression/
  performance/
  resolved/
  _summary.md          # consolidation only
```

Write `INDEX.md` immediately with repo, PR, SHA, branch, title, ISO timestamp, `GitHub posting: disabled`, the Discovery map, and `MODULES` / `DIFF_TOUCHES`.

Pass `{OUT_DIR}` as an **absolute** path. Remind every subagent: **write files only under `{OUT_DIR}`; never `gh` mutate; never edit application source.**

## Step 2: Launch subagents in parallel

Send **one message** with **six Task tool calls**, launched simultaneously. Each prompt carries: `{REPO}`, `{PR}`, `{SHA}`, `{OUT_DIR}`, the full diff (or a durable path to it under `{OUT_DIR}/_diff.patch` if too large — orchestrator may write that file), existing `{id,path,line}` set, PR intent, Discovery map, and Repository Map. After all six finish, run Step 3.

---

## Severity labels (all subagents)

- 🚨 Critical — bugs or logic errors that will cause failures
- 🔒 Security — vulnerabilities or data exposure
- ⚡ Performance — significant performance concerns
- ⚠️ Warning — code smells or maintainability issues
- 💡 Suggestion — optional improvements

---

## Universal Rules (every subagent + consolidation)

1. **Local write only.** Each finding → its own markdown file under `{OUT_DIR}/<type>/`. Filenames: `{NN}-{short-slug}.md`. Requirements → `{OUT_DIR}/requirements/summary.md` only.
2. **Anchor header (first line of every inline finding):** `<!-- ANCHOR: {path}:{line} -->` — `{line}` is the 1-based line in the head file on the RIGHT side (from hunk `+c`, counting added and context lines). Then the marker line, then the body.
3. **Comment allowlist:** findings only on diff lines starting with `+` (never `+++`).
4. **Skip duplicates:** skip if `{path,line}` within ±3 already has a GitHub comment or another local finding.
5. **Mark resolved (local only):** if an existing GitHub comment looks fixed, write `{OUT_DIR}/resolved/{COMMENT_ID}.md` with `[RESOLVED] …` + reason. **Do not** call the replies API.
6. **Confidence ≥80%** or stay silent.
7. **Positive highlight:** every subagent writes `{OUT_DIR}/<type>/_highlight.md` with at least one well-done aspect.
8. **Never** `--approve` / `--request-changes` / edit application source / post to GitHub.
9. **No attribution** (no AI/tool mentions in finding bodies).
10. **Marker:** after ANCHOR, start with `<!-- pr-review:{type} -->`. `{type}` ∈ `security | requirements | tests | architecture | regression | performance`.
11. **Tone:** specific, actionable, collegial; always explain WHY.
12. **Internal refs ground, don't cite in public prose.** `.specs/**` and skill rule paths inform judgment; translate into plain consequence prose in the finding body (no "see FR-3" / "per pest-testing skill" as the only rationale).
13. **Path honesty:** cite real Kappy paths (`app-modules/review/src/...`, not a fictional flat `app/Services/...`).

### Finding file shape

```markdown
<!-- ANCHOR: app-modules/review/src/Example.php:42 -->
<!-- pr-review:security -->
🔒 Security — [Short title]

[What the issue is and why it matters]

**Recommendation:** [Specific fix]
```

Requirements / `_highlight.md` / `_summary.md` / `INDEX.md` have no ANCHOR line.

---

## Subagent 1: Security — `<!-- pr-review:security -->`

Load `REVIEW_SKILLS` security-related sources first (`laravel-best-practices/rules/security.md`, Fortify skill if auth touched, any integration notes in STATE.md). Then sweep the diff for: hardcoded secrets; missing authn/authz on new endpoints/routes under `app-modules/*/routes` or `routes/`; PII/secrets in logs or LLM payloads (LGPD); missing webhook/callback signature validation (esp. `github-app`); injection via unsanitized input; broken access control on user/tenant-owned resources; sensitive fields in response/Inertia props; internal clients/keys crossing a module boundary.

**Kappy focus:** module public surface (routes, jobs, SCM drivers, AI reviewer I/O) and anything that logs or ships user/repo payloads.

**Second pass (mandatory):** re-read the full diff; for every untouched file/hunk state why it is clean or add a finding.

**Write under:** `{OUT_DIR}/security/`

---

## Subagent 2: Requirements & Definition of Done — `<!-- pr-review:requirements -->`

**One PR-level file only:** `{OUT_DIR}/requirements/summary.md`.

### Track A — Issue tracker

Extract ticket/issue refs from branch or PR body; fetch with available tools (`gh issue view`, etc.). Skip if none.

### Track B — In-repo specs (primary for Kappy)

Prefer `.specs/features/<feature>/{spec,design,tasks}.md` matched to the branch/PR. Also read Decision / Lessons rows in `.specs/project/STATE.md` and any ADR under `.specs/adr/` cited by the PR. Extract acceptance criteria, task checklist items, goals / non-goals.

| Tracks with content | Action |
|---|---|
| Both / A / B | Merge; note source per item |
| Neither | Write skip line and stop |

**Evidence-based scoring:** ✅ Implemented (`path:line`) / 🟡 Partial / ❌ Missing. No credit from PR prose alone. Behavior only exercised (not asserted) by a test ≠ verified.

**Second pass:** every merged criterion must be scored against the diff.

**Format:**
```markdown
<!-- pr-review:requirements -->
## 📋 Requirements Review
**Sources:** …
### ✅ Implemented
### 🟡 Partial
### ❌ Missing
### 🔲 Definition of Done
### 💬 Notes
```

---

## Subagent 3: Test Coverage — `<!-- pr-review:tests -->`

Load `pest-testing` and `laravel-best-practices` testing rules from the map. Co-located tests for module code live under `app-modules/<module>/tests/{Feature,Unit}` — that is the expected home, not a root-only `tests/` dump for new module behavior.

Flag: new/changed public behavior with **no corresponding Pest test** (🚨 for routes, jobs, controllers, SCM/webhook handlers, reviewer entry points); wrong level (unit vs Feature); quality gaps (no factory, hardcoded IDs, missing negative path, assert-free smoke).

**Hard rule:** "tests in a follow-up PR" is never acceptable for new behavior in this repo.

**Second pass:** every new/modified entry point must be justified as covered or N/A.

**Write under:** `{OUT_DIR}/tests/`

---

## Subagent 4: Architecture & Conventions — `<!-- pr-review:architecture -->`

### Phase 0 — Load project sources

Load every `CONVENTIONS` + architecture-related `REVIEW_SKILLS` path to EOF (modular-design-principles, laravel-best-practices/rules/architecture.md, STATE.md Decisions, Inertia/Wayfinder/Tailwind skills when FE touched). Do not substitute generic Laravel knowledge for missing docs.

### Phase 1 — Rule matrix

Extract every explicit rule (must/never, ✅/❌, checklist items) into a numbered list. That list is the evaluation matrix — add nothing the docs don't state.

### Phase 2 — Evaluate per changed file

PASS / VIOLATION / N/A per rule. For each VIOLATION, write an anchored finding citing rule number + source doc.

**Kappy structural checks to include when the docs state them (and as minimal fallback if docs are thin):** domain code under `app-modules/<module>/src`; no module→module PHP imports; FE module pages under `app-modules/<module>/resources/js` with shared imports via `@/` only; auth/settings staying in `app/` unless the PR intentionally moves them; `module::page` consistency if either resolver file is touched.

**Second pass:** every changed file must be matrix-evaluated or explicitly N/A.

**Write under:** `{OUT_DIR}/architecture/`

---

## Subagent 5: Regression & Hallucination Detection — `<!-- pr-review:regression -->`

Flag: unrelated deletions; imports/symbols that don't exist; wrong call arity/signature; TODO/FIXME/stubs on production paths; type lies; duplicated logic the codebase already owns; weakened validation/error handling; swallowed errors; weakened/deleted assertions; dead code; accidental `composer.json` / lock drift; stray modules under `app-modules/`.

**Second pass:** every file must be cleared or commented.

**Write under:** `{OUT_DIR}/regression/`

---

## Subagent 6: Performance — `<!-- pr-review:performance -->`

Load db-performance / advanced-queries rules when present. Flag only issues **visible in the diff**: N+1 / query-in-loop; unbounded `get()` without pagination; lazy relations per row; sequential awaits that should be concurrent; recomputing invariants in loops; multi-writes that should be one transaction/batch; loading full relations when `withCount`/subselects would do.

**Second pass:** every function/query/loop cleared or commented.

**Write under:** `{OUT_DIR}/performance/`

---

## Step 3: Consolidation

Spawn one Task subagent that reads **only** `{OUT_DIR}` (not GitHub review bodies):

1. Collect finding files under `security|tests|architecture|regression|performance/` (exclude `_highlight.md`).
2. Parse `<!-- pr-review:` type + `<!-- ANCHOR: path:line -->`.
3. Read `requirements/summary.md`, all `_highlight.md`, and `resolved/*`.
4. Group: 🔒 → 🚨 → ⚡ → ⚠️ → 💡.
5. Deduplicate same `{path,line}` ±3; note contributing lenses.
6. Gap detection: `gh pr diff {PR} --name-only` vs paths with findings; list logic files with zero findings (omit `*.json`/`*.yaml`/`*.lock` and pure type-only files).
7. Write `{OUT_DIR}/_summary.md` and update `INDEX.md` with the finding table.
8. **Do not** run `gh pr review` or any comment API.

**Summary format:**
```markdown
## 📋 PR Review Summary

| | |
|---|---|
| **Subagents** | 6 (Security · Requirements & DoD · Test Coverage · Architecture · Regression · Performance) |
| **Detected runner** | {TEST command} |
| **Requirements source** | {tracker / spec path / none} |
| **Modules touched** | {DIFF_TOUCHES} |
| **Project refs loaded** | {convention docs + skills} |
| **Findings** | {N} across {M} files |
| **Output** | `{OUT_DIR}` (local only — not posted to GitHub) |

---
### 🔒 Security ({N})
- [`path/file:L42`] Title — `security/01-….md`
### 🚨 Critical ({N})
### ⚡ Performance ({N})
### ⚠️ Warnings ({N})
### 💡 Suggestions ({N})

---
### 🔍 Files With No Inline Comments
…

---
### ✅ Highlights
…

---
### 📌 Locally noted resolutions
…

---
> Details under `{OUT_DIR}`. Nothing was posted to GitHub.
```

If zero findings: still write the metadata table plus `✅ No issues found across all review dimensions.`

## Step 4: Present and ask about cleanup (orchestrator)

1. Show a compact view of `_summary.md` (counts + top findings). Point at `{OUT_DIR}`.
2. **Required gate:** ask whether `pr-review-{PR}/` may be deleted now.
   - **yes** → `rm -rf` that directory from the repo root; confirm.
   - **no** → leave it; remind it is gitignored / ephemeral.
3. Never delete without an explicit yes in this turn.

---

## Examples

**A — Specs bind to `.specs/features`.** Branch `feat/review-reviewer-contract` matches `.specs/features/…` or PR body links; Track B scores FR/tasks against `app-modules/review/**`. No ticket → Track A skipped without failing the lens.

**B — Architecture matrix from Kappy docs.** Phase 1 extracts must/never rules from STATE.md Decisions + modular-design-principles + architecture.md; a new controller under `app/` for domain logic that belongs in a module is a VIOLATION citing that rule.

**C — Local write, never GitHub post.**
```bash
# ✅ pr-review-12/security/01-missing-webhook-signature.md
# ❌ gh api repos/.../pulls/12/comments ...
# ❌ gh pr review 12 --comment --body-file summary.md
```
