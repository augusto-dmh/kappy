# ADR-0001: Module Boundary Rules as Executable Fitness Functions

- **Date**: 2026-08-02
- **Status**: Proposed
- **Deciders**: augusto-dmh
- **Tags**: architecture, modular-monolith, testing, ci

## Context and Problem Statement

Kappy is a modular monolith (`internachi/modular`, modules under `app-modules/`) whose boundary conventions live in prose: `app-modules/README.md`, `.specs/project/STATE.md`, and review-time judgment in `kappy-code-review`. Nothing fails when a module reaches into another module's internals — consistency depends on the reviewer noticing. Sibling projects already close this gap: learny runs a stdlib boundary script as a `fitness` step inside its lint gate (encoding its ADRs 0007/0009/0019/0020), and fakeflix wires `@nx/enforce-module-boundaries` into ESLint. Kappy's frontend has the analog (`eslint-plugin-boundaries` enforcing unidirectional imports); the PHP side has nothing — and `STATE.md` already lists Pest `arch()` boundary tests as a deferred idea.

As more cycles ship (each one adding cross-module seams like `github-app ↔ review`), the cost of drift compounds. The rules must hold **before** the next cycles, and hold for agents as much as humans.

## Decision Drivers

- Architecture decisions should fail CI, not rely on review prose (learny's principle).
- Zero new dependencies (CLAUDE.md forbids dependency changes without approval).
- Must encode the *current accepted* architecture — the suite must pass on `main` from day one.
- Same vocabulary locally, in agent sessions, and in CI (learny's `lint` / `test` / `fitness` / `check`).
- Agents (ship cycles, sub-agent workers) need a mechanical gate; a skill's prose is advisory, a red test is not.

## Considered Options

- **A. Pest `arch()` suite in `tests/Architecture/`** (built into Pest v4)
- **B. Standalone stdlib PHP/Python boundary script** (learny-style)
- **C. Deptrac** (dedicated dependency-analysis tool)
- **D. Status quo** — prose conventions + review

## Decision Outcome

Chosen option: **"A. Pest `arch()` suite"**, because it adds zero dependencies, runs inside the existing `composer test` / `tests.yml` for free, is already part of the team vocabulary (the `pest-testing` skill covers `arch()`), and its expectations read as executable documentation.

### The rules (v1 — all pass on today's `main`)

These lock in the architecture as it exists; they are a ratchet, not an aspiration.

1. **SCM anti-corruption layer.** `Firebase\JWT` (firebase/php-jwt) and `Illuminate\Support\Facades\Http` are used only inside `Modules\GitHubApp\Scm` and `Modules\GitHubApp\Services`. All GitHub API access stays behind the Scm driver contract.
2. **Cross-module imports are surface-only.** A module may reference another module's `Models`, `Enums`, and `Contracts` — never its `Services`, `Actions`, `Jobs`, or `Http` namespaces. (Today's cross-module imports are Models-only; this rule preserves that.)
3. **Modules touch the core narrowly.** `Modules\*` may import from `App\` only `App\Models\User` (the shared identity anchor). No module imports `App\Http`, `App\Providers`, or other core internals.
4. **Enums and Contracts stay pure.** `Modules\*\Enums` and `Modules\*\Contracts` depend on no framework classes (no `Illuminate\*` beyond contracts/interfaces, no facades).
5. **Debug hygiene.** `dd`, `dump`, `ray`, `var_dump`, `env()` outside `config/` — banned repo-wide (Pest's `arch()->preset()->php()` + a `env` expectation).

**Accepted debt, documented not forbidden (v1):** the model-level cycles `identity ↔ github-app` (`Account ↔ Installation`) and `github-app ↔ review` (`PullRequest ↔ Review`), and the core↔identity coupling through `User`. These are relationship back-references inherent to the current Eloquent design. The test file records them in a comment block as *accepted*; a future ADR may ratchet them out (e.g. via contracts or domain events), at which point the allowlist shrinks.

### Wiring

- `tests/Architecture/ModuleBoundariesTest.php` (+ one file per concern if it grows), each expectation citing this ADR in a comment — learny's "script cites the ADRs it encodes" pattern.
- `composer fitness` → `php artisan test --compact tests/Architecture` (the canonical name, matching learny's `make fitness`).
- `composer test` already covers it (Pest discovers the suite); add a **named step** "Architecture fitness" to `.github/workflows/lint.yml` running `composer fitness`, so boundary breaks surface in the lint job like learny's, not buried in the test matrix.
- `kappy-code-review` and `kappy-ship-cycle` reference the suite: a boundary finding is not a style nit, it is a failing-gate class issue.

### Positive Consequences

- Boundary violations become red CI, catchable by any agent or human before review.
- The rules are self-documenting and colocated with the tests that enforce them.
- Locks the SCM anti-corruption layer (currently clean by discipline alone) before the next cycles build on it.
- No new dependency, no new CI job — one composer script and one workflow step.

### Negative Consequences

- Pest `arch()` is class/namespace-level; it cannot express everything Deptrac can (e.g. layered ruleset visualization, baseline files).
- The accepted-debt cycles are only *documented*, not prevented from deepening — model-level coupling can still grow inside the allowed `Models` surface.
- One more suite to keep honest: when a new module lands, rules 2–4 must name it (mitigated by writing rules over `Modules\*` wildcards wherever Pest allows).

## Pros and Cons of the Options

### A. Pest `arch()` suite ✅ Chosen

- ✅ Zero dependencies; runs in the existing test pipeline.
- ✅ Expectations read as documentation; failures name the offending class and rule.
- ✅ Team/agent vocabulary already includes it (`pest-testing` skill).
- ❌ Less expressive than a dedicated tool for allowlisted-edge graphs.

### B. Stdlib boundary script (learny-style)

- ✅ Total control; trivially fast.
- ❌ Regex-over-`use`-statements in PHP misses aliased/FQN/dynamic usage that Pest's reflection-based arch layer handles.
- ❌ A second enforcement mechanism outside the test suite to maintain.

### C. Deptrac

- ✅ Purpose-built: layers, ruleset, baseline for accepted debt.
- ❌ New dev dependency (needs approval, contradicts driver #2 when A suffices).
- ❌ Separate config dialect to learn; overkill at 4 modules.

### D. Status quo

- ✅ No work.
- ❌ The exact failure mode this ADR exists to prevent; already deferred once in STATE.md.

## Implementation Plan (on acceptance)

1. `tests/Architecture/ModuleBoundariesTest.php` with rules 1–5 + accepted-debt comment block; confirm green on `main`.
2. Add `fitness` script to `composer.json`; add the lint.yml step.
3. Flip this ADR to **Accepted**; add a decision row to `.specs/project/STATE.md` and remove the corresponding "Deferred ideas" entry.
4. Follow-up candidates (separate ADRs): ratchet the model cycles via contracts/events; a `kappy-modular-architecture` expert skill (fakeflix pattern) once the rules are executable.

## Links

- learny: `backend/scripts/check_boundaries.py`, `Makefile` (`fitness` target), `ci.yml` lint job — the pattern this adapts.
- fakeflix: `eslint.config.mjs` (`@nx/enforce-module-boundaries`), `.agents/skills/modular-architecture/SKILL.md`, `.agents/skills/coupling-analysis/SKILL.md`.
- Kappy: `app-modules/README.md` (prose conventions), `.specs/project/STATE.md` (deferred-idea entry, frontend `eslint-plugin-boundaries` decision), `eslint.config.js:122-169` (frontend boundary rules).
