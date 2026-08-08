# Implement prompt template

Fill the `{{PLACEHOLDERS}}` from the research session, then emit the content between the `BEGIN`/`END` markers as the "Prompt 2 — Implement" code block and save it to `.specs/features/{{FEATURE}}/handoff-implement.md`. Delete any optional line that does not apply. Keep every prose line unwrapped (single line per paragraph).

Placeholders:

- `{{FEATURE}}` — feature slug (matches `.specs/features/<feature>/`).
- `{{GATE_COMMANDS}}` — the project's verification commands, discovered from the repo (tests, linter, formatter, type-check, build). Example for this repo: `php artisan test --compact`, `npm run lint`, `npm run types:check`, `npm run build`, and `vendor/bin/pint --dirty --format agent` after PHP edits.
- `{{CONVENTIONS_PATHS}}` — project convention sources and relevant domain skills to activate.
- `{{DEP_INTEGRITY_CMD}}` — dependency-integrity no-op check for the stack (e.g. `composer install --dry-run`, `npm ci --dry-run`).

---

BEGIN IMPLEMENT PROMPT

You are picking up the **Implement** phase of an RPI (Research → Plan → Implement) workflow in a fresh session. The plan already exists. Your job is to execute it task-by-task with verification, keeping your own context lean by delegating to sub-agents.

## Read these first (in order)

1. `.specs/features/{{FEATURE}}/tasks.md` — the task list with dependencies, verification, and gates. This is your work queue and contract.
2. `.specs/features/{{FEATURE}}/design.md` — for the how, the code patterns, and the risks.
3. `.specs/project/STATE.md` — the lessons and gotchas. Treat every gotcha as a guardrail, not trivia.
4. {{CONVENTIONS_PATHS}} — conventions to follow; activate the relevant domain skills when working in their area.

Read `research.md` only if a task is unclear — you usually do not need the full research narrative.

## Steps

1. Invoke the `tlc-spec-driven` skill and drive its **Execute** phase against `tasks.md`. (If unavailable, execute `tasks.md` directly, task-by-task.)
2. Mirror the tasks in your todo tracker; mark each in-progress when started and complete when its gate passes.
3. Work the dependency graph in order. After every task, run the relevant gate and keep it green before moving on.
4. Resolve any spike task fully — verify against ground truth (read the real file, run the real command) — before any dependent task starts.

## Sub-agent and isolation strategy (for context optimization)

- Delegate each implementation task to its own sub-agent so file reads, edits, and test output stay out of your main context. Give each sub-agent only its task block, the relevant `design.md` section, the conventions, and the applicable `STATE.md` gotchas. It returns: status, files changed, gate result, and any deviations. You keep sequencing and todo updates.
- Run parallel-safe tasks concurrently.
- Use git-worktree isolation **only** for sub-agents that edit files in parallel and would otherwise conflict. For sequential tasks that share state (lockfiles, config, generated files), do not use worktrees — isolation there only causes merge friction.

## Process guards (from hard-won lessons)

- **Dependency integrity:** after any dependency-affecting work, run `{{DEP_INTEGRITY_CMD}}` and confirm it is a no-op. A clean clone must reproduce the setup.
- **Never blanket-restore tracked files:** do not run `git restore`/`git checkout` across tracked files to "clean up" — it can silently revert manifest/lock changes and break the build. Restore narrowly and intentionally.
- **Clean up throwaway artifacts:** if you scaffold anything to probe a command, delete it (files, symlinks, generated registry entries) before the final gate.

## Definition of done

- Every task in `tasks.md` is complete with its gate passing.
- The full project gate passes: {{GATE_COMMANDS}}.
- `{{DEP_INTEGRITY_CMD}}` is a no-op; no stray throwaway artifacts remain.
- Pre-existing tests still pass (no regressions).

## Stop conditions

- Do not commit or push unless the user explicitly asks.
- If a decision recorded in `STATE.md` turns out to be wrong, or a task is blocked, stop and ask the user rather than improvising around the plan.

When done, report the gate output as evidence (test counts, lint/build results) and list the files changed.

END IMPLEMENT PROMPT
