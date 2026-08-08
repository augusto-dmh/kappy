# Plan prompt template

Fill the `{{PLACEHOLDERS}}` from the research session, then emit the content between the `BEGIN`/`END` markers as the "Prompt 1 — Plan" code block and save it to `.specs/features/{{FEATURE}}/handoff-plan.md`. Delete any optional line that does not apply. Keep every prose line unwrapped (single line per paragraph).

Placeholders:

- `{{FEATURE}}` — feature slug (matches `.specs/features/<feature>/`).
- `{{ONE_LINE_GOAL}}` — one sentence describing the outcome.
- `{{LOCKED_DECISIONS}}` — bullet list of decisions already made (so the Plan session does not re-litigate them).
- `{{CONVENTIONS_PATHS}}` — project convention sources to honor (e.g. `CLAUDE.md`, `.specs/codebase/*`, relevant skills).
- `{{OPEN_SPIKES}}` — unresolved seams / questions to convert into spike tasks (or "none").

---

BEGIN PLAN PROMPT

You are picking up the **Plan** phase of an RPI (Research → Plan → Implement) workflow in a fresh session. Research is already done and persisted. Your job is to produce the planning artifacts only — you must not write feature code or execute tasks.

## Read these first (in order)

1. `.specs/features/{{FEATURE}}/research.md` — the research handoff: goal, decisions, findings, verified seams, gotchas. This is your primary source; trust it over your own assumptions.
2. {{CONVENTIONS_PATHS}} — project conventions you must follow.
3. `.specs/project/STATE.md` — if present, for prior decisions and lessons.

Do not read or load anything beyond what you need to plan. Keep context lean.

## Goal

{{ONE_LINE_GOAL}}

## Locked decisions (do not re-open)

{{LOCKED_DECISIONS}}

## Steps

1. Invoke the `tlc-spec-driven` skill and drive it through **Specify → Design → Tasks** for feature `{{FEATURE}}`. (If `tlc-spec-driven` is unavailable, produce `.specs/features/{{FEATURE}}/spec.md`, `design.md`, and `tasks.md` manually following its structure.)
2. Treat `research.md` as the input: derive requirements with traceable IDs from its goal and decisions; build the design from its findings; break the work into atomic tasks with dependencies, verification, and gate checks.
3. Convert every open spike into an explicit spike task that must be resolved before any task depends on it: {{OPEN_SPIKES}}.
4. If you hit an unknown not covered by `research.md`, do targeted verification before deciding — read the real file or run the real command (`<command> --help` / a dry run). Never guess; record what you verified in `design.md`.
5. Make tasks small enough that one sub-agent can complete each in the Implement phase, and mark independent tasks that can run in parallel.

## Definition of done

- `.specs/features/{{FEATURE}}/spec.md`, `design.md`, and `tasks.md` exist and are internally consistent (every requirement traces to at least one task).
- Spikes are tasks, not assumptions.
- `tasks.md` encodes dependencies and parallel-safe markers.

## Stop conditions — do NOT cross the line

- **Hard stop after `tasks.md`. Do not start Execute. Do not write feature code.** Producing the task list is the end of this session; implementation happens in a separate session.
- If a locked decision turns out to be wrong or a requirement is ambiguous, stop and ask the user rather than guessing.

When done, briefly summarize the task count and any spikes, and tell the user the plan is ready to review before running the Implement prompt.

END PLAN PROMPT
