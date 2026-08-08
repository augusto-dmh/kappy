---
name: rpi-handoff
description: Generates two ready-to-paste handoff prompts (Plan and Implement) at the end of a Research phase so each RPI phase runs in a fresh, lean, phase-scoped session. Use when research is done and the user asks "what next", wants handoff prompts, asks to split work into separate sessions, to generate plan and implement prompts, or to set up an RPI / research-plan-implement handoff. Runs a seam-verification gate, persists research.md, and splits work at the tlc-spec-driven Tasks-to-Execute boundary. Do not use for doing the research itself, for writing the spec or executing code directly, or for small trivial tasks that do not warrant phase separation.
license: CC-BY-4.0
metadata:
  author: Kappy contributors
  version: 1.0.0
---

# RPI Handoff

Turn a finished research session into two precise, self-contained prompts — one for the **Plan** phase, one for the **Implement** phase — that a user pastes into fresh sessions. The goal is RPI (Research → Plan → Implement) with clean context boundaries: each phase gets exactly the context it needs and nothing it does not.

## Core principle

Each phase needs *different* context. Planning needs the *what* and *why* (research findings, decisions, conventions). Implementation needs the *how* and the guardrails (the task list, gotchas, gate commands). A single long session blends them and the context rots. This skill curates a phase-scoped "read-first" manifest for each prompt so the next session starts lean but never under-informed.

## When this runs

Run this at the **end of a research session**, after findings and decisions exist but before any planning artifacts or code. It assumes a complementary planning skill (`tlc-spec-driven`) will do the actual Specify/Design/Tasks/Execute work in the downstream sessions. If `tlc-spec-driven` is not installed, still emit the prompts but tell the user the downstream sessions will plan and execute manually instead of via that skill.

## Workflow

Do the steps in order. Do not emit any prompt until Step 2 (the gate) passes.

### Step 1: Identify the feature and confirm the phase

1. Confirm research is genuinely done: the problem is understood, the key decisions are made, and the open questions are resolved or consciously deferred. If decisions are still open, stop and resolve them with the user first — downstream sessions must not inherit ambiguity.
2. Determine the feature slug and its spec home: `.specs/features/<feature>/`. Reuse an existing folder if one matches; otherwise pick a short kebab-case slug.

### Step 2: Seam-Verification Gate (CRITICAL — do this before emitting anything)

This is the most important step. It exists because docs and assumptions lie; only ground truth does not. Enumerate every **integration touch-point** the feature will modify or depend on, then prove each was verified against ground truth *in this session*.

Integration touch-points to enumerate (not exhaustive — adapt to the stack):

- Config files the change reads or writes.
- Framework bootstrap / wiring files (providers, service registration, app bootstrap).
- Build / bundler config and any template or entrypoint files that reference build output.
- CLI commands the plan will run — **including their exact flags** (defaults bite).
- External package or library APIs the change calls.

For each touch-point, confirm one of:

- The **actual file was read** this session (not a doc describing it), or
- The **actual command output was observed** this session (e.g. `<command> --help`, a dry run).

Rules:

- If a touch-point was only verified via documentation, a blog post, or assumption, it is **unverified**. Either verify it now (read the real file / run the real command) or log it explicitly as a risk in `research.md` and as a spike in the plan.
- Do not emit the prompts until every touch-point is either verified or consciously recorded as a gap. Silent gaps are forbidden.

Why this matters: in the session that motivated this skill, a template entrypoint file and a CLI command's flags were never read against ground truth — they were assumed from docs — and caused a runtime HTTP 500 and a scaffolding failure during implementation. The fix costs one file read or one `--help` at research time.

### Step 3: Write `research.md`

Persist the research as the durable handoff artifact at `.specs/features/<feature>/research.md`. This is what the Plan session reads instead of scrolling chat history. Include:

- **Problem & goal** — one paragraph.
- **Decisions** — each locked decision with a one-line rationale.
- **Findings** — the load-bearing facts, with sources (file paths, URLs, command output).
- **Verified seams** — the Step 2 list, each marked verified (with how) or a logged risk.
- **Open questions / spikes** — anything deferred, to become spikes in the plan.
- **Gotchas** — hazards the implementer must know.

### Step 4: Generate the Plan prompt

Read `references/plan-prompt.md` and fill its placeholders. This prompt drives `tlc-spec-driven` through Specify → Design → Tasks and **hard-stops before Execute** — that stop is the session boundary. Keep its read-first manifest scoped to planning context (research.md + relevant conventions); do not load execution minutiae.

### Step 5: Generate the Implement prompt

Read `references/implement-prompt.md` and fill its placeholders. This prompt drives `tlc-spec-driven` Execute only. It references `tasks.md` and `STATE.md` **by path** (the artifacts are the contract — tasks.md is written later by the Plan session, so do not inline task details). It carries the process guards: sub-agent delegation, worktree isolation only for parallel conflicting tasks, dependency-integrity checks, and throwaway-artifact cleanup.

### Step 6: Deliver

1. **Print** both prompts in the session as separate fenced code blocks, clearly labeled "Prompt 1 — Plan" and "Prompt 2 — Implement", with a one-line instruction: run Plan first in a fresh session, review the resulting `tasks.md`, then run Implement in another fresh session.
2. **Save** them to `.specs/features/<feature>/handoff-plan.md` and `.specs/features/<feature>/handoff-implement.md` so they are resumable later.

## Prompt-writing rules (apply to both generated prompts)

Both emitted prompts are real artifacts that steer downstream sessions, so write them to Anthropic prompt-engineering standards:

- **Assign a role** in the first line (e.g. "You are picking up the Plan phase of an RPI workflow.").
- **Open with a scoped "Read these first" manifest** — explicit file paths, ordered, nothing extraneous.
- **Give ordered, numbered steps**, not prose. One instruction per line.
- **State the definition of done and the stop conditions** explicitly.
- **State what NOT to do** (e.g. the Plan prompt must forbid writing code or executing tasks).
- **Prefer specific, verifiable instructions** over "be careful". Name the command, the file, the check.
- **Keep each paragraph on a single line** (no hard wraps) so renderers do not corrupt the copied text.

## Examples

### Example 1: End of a research session

User says: "Research is done — give me the handoff prompts."
Actions:
1. Confirm decisions are locked; set feature = `payment-webhooks`.
2. Run the Seam-Verification Gate: read the webhook controller, the queue config, and `php artisan queue:work --help`; one config file was only doc-verified, so read it now.
3. Write `.specs/features/payment-webhooks/research.md`.
4. Fill `plan-prompt.md` and `implement-prompt.md`.
5. Print both prompts and save to `handoff-plan.md` / `handoff-implement.md`.
Result: Two labeled prompts in the chat plus two saved files; the Plan prompt stops before Execute, the Implement prompt points at `tasks.md` by path.

### Example 2: Decisions not yet locked

User says: "We researched the auth options, make the handoff prompts."
Actions:
1. Notice two unresolved decisions (session driver, 2FA scope).
2. Stop and ask the user to choose before generating anything.
Result: No prompts emitted yet; ambiguity resolved first so downstream sessions are clean.

## Troubleshooting

### Gap: a touch-point can't be verified this session

Cause: the file or command is not available in the current environment. Solution: do not pretend it is verified. Record it in `research.md` under risks and add a spike for it in the Plan prompt so the Plan session verifies it before depending on it.

### `tlc-spec-driven` is not installed

Cause: the downstream planning skill is absent. Solution: still emit both prompts, but replace "invoke tlc-spec-driven" with explicit instructions to produce `spec.md` / `design.md` / `tasks.md` (Plan) and to execute `tasks.md` task-by-task with verification (Implement).

### The Implement prompt feels incomplete without task details

Cause: `tasks.md` does not exist yet at research time. This is expected — the Implement prompt is artifact-driven and references `tasks.md` by path. The Plan session writes that file; the Implement session reads it. Do not inline task specifics.
