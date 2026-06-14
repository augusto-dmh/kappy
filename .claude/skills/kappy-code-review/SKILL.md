---
name: kappy-code-review
description: Produce grounded GitHub code-review comments for a Kappy feature branch, then post them to the PR only on explicit confirmation. Emits one anchor-verified inline-comment file per finding plus a PR-level summary, with every cited file:line verified against the real git diff first. Grounds findings in Kappy's own conventions by activating laravel-best-practices, pest-testing, inertia-react-development and tailwindcss-development and reading the .specs and AGENTS/CLAUDE docs, not generic Laravel or React idioms. Prose in pt-BR with category/severity headers and an English Prompt for AI Agents block. Triggers on 'revisa esse PR', 'gera os comentários do code review', 'review my branch before I open the PR', 'code review do Kappy'. Strictly review-only — never edits source, never pushes, never opens the PR, posts to GitHub only when you say so. Do NOT use for debugging, planning unimplemented features (tlc-spec-driven), implementing fixes (pr-execute), or branching/committing/opening PRs (kappy-finalize).
license: CC-BY-4.0
metadata:
  author: Kappy contributors
  version: 1.0.0
---

# Kappy Code Review

Generates grounded, paste-ready GitHub inline-comment files for a Kappy feature branch, then posts them to the PR only when you confirm. Read-only by design — it sits between implementation (`pr-execute`) and the human opening or merging the PR on GitHub.

## Operating principles

- **Review-only.** Never edits source, never pushes, never opens or merges the PR. The only filesystem writes are the comment files under `code-review-<slug>/` at repo root. Posting to GitHub happens only on an explicit go-ahead (Step 10).
- **Evidence over hypothesis.** Every cited `file:line` is verified against the real diff via [scripts/verify-anchors.sh](scripts/verify-anchors.sh) before any comment file is shipped. A wrong anchor renders as an unrelated inline comment and erodes trust in the whole review.
- **Kappy's own conventions are the judgement basis.** Generic Laravel/React idioms are not the standard — Kappy's skills and specs are. Every run activates `laravel-best-practices` and reads `AGENTS.md`, `CLAUDE.md`, and `.specs/project/*`. Frontend diffs additionally activate `inertia-react-development`, `tailwindcss-development`, and `wayfinder-development`. This skill routes to those sources rather than duplicating their rules — see [references/review-dimensions.md](references/review-dimensions.md).
- **Findings as facts, not weighed choices.** Each `Fix sugerido:` is a single recommendation grounded in a specific rule file, spec, or prior commit. No A/B menus.
- **Tests in the same PR is a hard rule.** New behavior/wiring/validation/route surface without co-located Pest tests in this branch is `⚠️ Potential issue | 🔴 High`. "Tests in a follow-up PR" is a blocker, never a nitpick. (See `pest-testing` and `laravel-best-practices/rules/testing.md`.)
- **LGPD discipline.** PII over-fetch or PII in logs/payloads is a security `⚠️ Potential issue`. Never echo real PII in the comment prose — use placeholders or local-id refs.
- **Pt-BR prose, English Prompt for AI Agents.** Bold category, bold severity, bold title, woven `Fix sugerido:`, then a plain `🤖 Prompt for AI Agents` section (no `<details>` wrapper) — single canonical format per [references/comment-format.md](references/comment-format.md).
- **One finding per file.** GitHub renders markdown reliably, but one self-contained file per finding keeps inline anchoring clean and lets [scripts/post-review.sh](scripts/post-review.sh) map each file to one PR line comment. The PR-level `_summary.md` is the one consolidated comment.

## When to use

- The user wants inline review comments on a feature branch before opening the GitHub PR.
- The user wants a second, deeper, repo-grounded review on an already-open PR.
- Trigger phrases: "revisa esse PR", "gera os comentários do code review", "code review do Kappy", "review the branch with inline comments", "review this branch", "review my branch before I open the PR", "preparar comentários antes de abrir o PR".

## When NOT to use

- Bug investigation from a stack trace, exception, or log → general debugging flow, not this skill.
- Planning a not-yet-implemented feature → `tlc-spec-driven` (spec/design/tasks).
- Generating the next PR's handoff prompt → `pr-handoff`.
- Implementing fixes from this review's findings → hand off to `pr-execute` with the findings as context. Never edit source from inside this skill.
- Generating the PR description, committing, branching, pushing, or opening the PR → `kappy-finalize`.
- Reviewing branches in repos other than `kappy` — this skill is repo-scoped.

## Workflow

### Step 0 — Ensure feature branch and resolve review slug

Run `git branch --show-current`.

- If on `main` or any protected branch: refuse and ask the user to switch first. This skill reviews work already on a feature branch; it never creates one.
- Otherwise derive a **review slug** from the branch name by stripping the type prefix: `feat/github-webhook-receiver` → `github-webhook-receiver`, `fix/142-duplicate-invoices` → `142-duplicate-invoices`. The slug names the output folder `code-review-<slug>/`. If a matching `.specs/features/<feature>/` dir exists, prefer that feature name for the slug.

### Step 1 — Load base context (always, parallel)

These inform every finding. Never review without them in context.

- Activate the `laravel-best-practices` skill — its `rules/*.md` are the backend judgement basis.
- Read `AGENTS.md` and `CLAUDE.md` (repo working rules, stack, Boost tooling).
- Read `.specs/project/PROJECT.md` and `.specs/project/STATE.md` (product intent, current architecture state).

Kappy is a **modular monolith**: domain code lives under `app-modules/<module>/src/` (`Http/Controllers`, `Jobs`, `Services`, `Models`, `Contracts`, `Enums`, `Scm`, `Providers`), with co-located tests under `app-modules/<module>/tests/{Feature,Unit}`, routes under `app-modules/<module>/routes/`, and migrations/factories under `app-modules/<module>/database/`. Per-module frontend lives at `app-modules/<module>/resources/js/`; app-level pages live at `resources/js/pages`. Anchor every finding to the real module path, not a flat `app/` path.

### Step 2 — Compute diff scope

**Branch mode (default):**

```
git fetch origin main
git diff origin/main...HEAD --stat
git diff origin/main...HEAD --name-only
git log --oneline origin/main..HEAD
```

**PR-number mode (user gives a PR number):** `gh pr view <n> --json headRefName,baseRefName,headRefOid,number`, then `gh pr diff <n> --name-only`. `gh` (2.46+) is the canonical CLI here. Capture `headRefOid` — it is the `commit_id` that Step 10's posting needs.

### Step 2b — Activate frontend skills when `resources/js/**` is touched

If `--name-only` includes any `.tsx`/`.ts` file under `resources/js/` or `app-modules/*/resources/js/` (hooks, components, pages), activate and apply as additional review dimensions:

- `inertia-react-development` — Inertia v3 React patterns: app-level pages live in `resources/js/pages`, components in `resources/js/components`, layouts in `resources/js/layouts`; per-module UI in `app-modules/<module>/resources/js`; `useForm`/`<Form>`/`router` usage; deferred-prop empty states.
- `wayfinder-development` — typed route functions from `@/actions` / `@/routes` instead of hardcoded URL strings.
- `tailwindcss-development` — Tailwind v4 utility idioms; no ad-hoc inline styles where a utility fits.

These checks are additive — run them alongside the backend dimensions, not instead of them.

### Step 3 — Read touched feature specs

If the diff maps to a `.specs/features/<feature>/` directory, read its `research.md`, `tasks.md`, and any `STATE.md` before drafting findings. Code that contradicts a documented invariant or a planned task scope is a finding.

### Step 4 — Map the touched code (delegate to Explore)

Spawn an `Explore` subagent:

```
Task(subagent_type="Explore", description="Map touched code for <feature>",
  prompt="In /home/augusto/projects/kappy (modular monolith), for these changed files <list>:
    (1) controllers/services/jobs/commands/models/enums under app-modules/<module>/src/ — paths + 1-line purpose;
    (2) React files under app-modules/<module>/resources/js or resources/js (pages, components, hooks, layouts);
    (3) existing co-located Pest tests in app-modules/<module>/tests/{Feature,Unit} or root tests/ touching this area (or note absence);
    (4) external integrations involved (GitHub App / webhooks / SCM driver / OAuth) + their service/contract paths;
    (5) any sibling feature with the same domain/UX shape to reference.
    Return a concise file map (paths + 1-line purpose). Skip irrelevant scaffolding.")
```

Dispatch multiple Explore agents in parallel if the diff spans distinct areas. Use the returned map to ground findings — never review from generic assumptions.

### Step 5 — Apply the review dimensions

Walk the diff using [references/review-dimensions.md](references/review-dimensions.md), which routes each pass to the rule file or skill that owns it (architecture/layering, security/authz, DB/perf, validation, tests-with-PR, LGPD/PII, plus the frontend dimensions from Step 2b). Produce a draft list with `path:line`, severity, category, and a one-line title per finding.

### Step 6 — Map findings to display labels

Use [references/severity-category-mapping.md](references/severity-category-mapping.md) to convert each draft finding's severity (high/medium/low) and category (bug/security/perf/maintainability) to its display label (`⚠️ Potential issue` / `🛠️ Refactor suggestion` / `🧹 Nitpick`) and dot (`🔴` / `🟠` / `🟡`). Hard-rule violations (missing tests in same PR, page outside `resources/js/pages`) are at least High.

### Step 7 — Verify anchors (mandatory gate)

Draft the comment files under `code-review-<slug>/`, then run:

```
./.claude/skills/kappy-code-review/scripts/verify-anchors.sh code-review-<slug> origin/main HEAD
```

(PR-number mode: pass the PR head ref as the third arg.) The script asserts every `<!-- ANCHOR: path:line -->` points to a `+` (added/modified) line in `git diff origin/main...HEAD`. Non-zero exit → fix the anchors before proceeding.

### Step 8 — Emit per-finding files

Write one file per finding under `code-review-<slug>/`, following [references/comment-format.md](references/comment-format.md) and the shapes in [references/examples/](references/examples/):

- **First line of every per-finding file is the anchor header** `<!-- ANCHOR: path/to/file.ext:LINE -->` (HTML comment, consumed by the scripts, invisible when rendered). `_summary.md`, `INDEX.md`, and `nitpicks.md` skip it.
- Naming: `B<n>-<slug>.md` for blocking high-severity issues, `H<n>-/M<n>-/L<n>-<slug>.md` for the rest, `nitpicks.md` for the grouped nitpicks (one comment, never inline).
- Prose pt-BR; bold title (no `file:line` in the title — the anchor handles it).
- `Fix sugerido:` woven inline as a single recommendation, citing the relevant `laravel-best-practices/rules/*.md`, `.specs/*`, or a prior commit.
- `🤖 Prompt for AI Agents` in English as a plain section. Never echo real PII.

### Step 9 — Emit `_summary.md` and `INDEX.md`

`_summary.md` is the PR-level comment: risk badge, walkthrough paragraph, counts by severity. `INDEX.md` is the anchor table mapping each file to its `file:line`, category, and severity, plus the posting/paste guidance (see [references/comment-format.md](references/comment-format.md)).

### Step 10 — Present, then post only on confirmation

Present the review summary and counts. Then route by intent:

- **"posta no PR" / "post it" / "manda pro GitHub"** → run [scripts/post-review.sh](scripts/post-review.sh) `<pr-number> code-review-<slug>`. It posts each per-finding file as an inline comment (`gh api .../pulls/<n>/comments` with the head `commit_id`, `path`, `line`, `side=RIGHT`) and `_summary.md` as a general PR comment (`gh pr comment`). Never run this without an explicit go-ahead.
- **"vamos consertar X" / "implementa as correções"** → hand off to `pr-execute` with the findings as context.
- **"finaliza" / "abre o PR"** → hand off to `kappy-finalize`.
- Otherwise: stop. The user pastes the comments manually or posts later.

Never edit source from inside this skill. Producing a code fix here is a hard error — route it out.

## Hard rules

- **Never write a final comment file before `verify-anchors.sh` exits 0.**
- **Never post to GitHub without an explicit confirmation** in the same turn.
- **Never echo real PII** (CPF, full name, personal email, phone, address, RG, birth date) in the comment prose. Use placeholders or local-id refs.
- **One finding per file.** Only `_summary.md` consolidates.
- **Never edit source code.** Hand off to `pr-execute` for any fix work.
