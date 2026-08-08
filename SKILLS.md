# Kappy Skills — How They Fit Together

This repository ships a set of **skills** (reusable, model-invoked playbooks under `SKILL.md` files) that encode how to work on Kappy consistently. This document explains what each skill is for, how they interact, and shows a full worked workflow that chains them end to end.

> Skills are mirrored across three agent toolchains so each can find them: `.claude/skills/` (Claude Code), `.agents/skills/` (generic AGENTS), and `.codex/skills/` (Codex). When you add or change a skill, mirror it to the toolchains you use. This document is the single, tool-agnostic source of truth and is **not** mirrored.

## Three layers of skills

Think of the skills in three layers.

**1. Domain skills — auto-activate while you build.** These fire automatically based on the files and topics you touch. You rarely invoke them by name; they sharpen *how* code in their area is written.

| Skill | Activates when you work on | Hands quality to |
| --- | --- | --- |
| `laravel-best-practices` | Backend PHP: controllers, models, migrations, requests, jobs, Eloquent | The whole backend |
| `fortify-development` | Authentication: login, register, password reset, email verification, 2FA | Auth flows |
| `inertia-react-development` | Inertia v3 + React pages, forms, navigation, `useForm`, deferred props | Client pages |
| `wayfinder-development` | Wiring frontend calls to backend routes/controllers with typed functions | The FE↔BE seam |
| `tailwindcss-development` | Tailwind v4 utility classes, layouts, responsive/dark-mode styling | UI styling |
| `pest-testing` | Writing or fixing Pest tests (Feature/Unit/Browser, datasets, `arch()`) | Test coverage |

**2. Workflow skills — you invoke them to drive a process.** These orchestrate multi-step work and call on the domain skills as they go.

| Skill | Role | Typical trigger |
| --- | --- | --- |
| `rpi-handoff` | At the end of research, emits two fresh-session prompts (Plan, Implement) with a seam-verification gate | "research is done, what next", "generate handoff prompts" |
| `tlc-spec-driven` | Plans and executes: Specify → Design → Tasks → Execute, with EARS ACs, deterministic validation scripts, independent Verifier, and lessons | "specify feature", "design", "tasks", "implement", "validate", "resume work" |
| `kappy-ship-cycle` | Orchestrates one full PR end-to-end: tlc cycle → finalize → fresh-context code review → triage → fixes → thread closure → user-gated merge; resumable via `.specs/.ship-status` | "ship the next PR", "run the ship cycle", "continue" |
| `kappy-code-review` | Grounded inline review of a feature branch/PR; posts only on confirmation | "revisa esse PR", "review my branch" |
| `kappy-review-triage` | Evaluates posted findings one sub-agent each; closes threads as [RESOLVED]/[ADIADO]/[INVÁLIDO] | "avalia os findings do PR N", "triage the review comments" |
| `kappy-manual-qa` | Produces a grounded manual-QA artifact for a shipped slice | "gera o QA", "manual QA for PR N" |
| `skill-architect` | Designs and builds new skills (Discovery → Architecture → Craft → Validate) | "create a skill", "turn this into a skill" |
| `kappy-finalize` | Publishes finished work: branch name, Conventional Commit, push, ready-for-review PR | "finalize", "commit", "open a PR" |

**3. Decision & analysis skills — capture *why* before *what*.** Ported/adapted from the learny and fakeflix (Tech Leads Club) harnesses.

| Skill | Role | Typical trigger |
| --- | --- | --- |
| `grilling` / `grill-me` | Relentless one-question-at-a-time interview to stress-test a plan before building | "grill me", "stress-test this plan" |
| `create-rfc` | Proposal document while a decision is still open | "write an RFC", "draft a proposal" |
| `create-adr` | Records an accepted architecture decision in `.specs/adr/` | "write an ADR", "document this decision" |
| `create-technical-design-doc` | Implementation-ready technical design once a decision is made | "write a design doc", "create a TDD" |
| `coupling-analysis` | Khononov 3-D coupling analysis (strength × distance × volatility) of modules/dependencies | "analyze coupling", "evaluate architecture" |
| `domain-analysis`, `modular-decomposition`, `modular-design-principles`, `decomposition-planning-roadmap` | Decomposition family: mapping domains and planning module boundaries | research/design phases |

Decision artifacts flow: `grilling` sharpens the idea → `create-rfc` while open → `create-adr` once accepted → executable enforcement where possible (see `.specs/adr/0001-module-boundary-rules-and-architecture-fitness-functions.md`, the proposed Pest `arch()` fitness suite).

> All three mirrors (`.claude/skills/`, `.agents/skills/`, `.codex/skills/`) now carry the identical full set, including the decomposition family used for breaking a system into modules/domains (it feeds the *Research* and *Design* phases below).

## How they interact

The workflow skills form a pipeline; the domain skills plug in during execution.

```
RESEARCH ─────────────► PLAN ──────────────► IMPLEMENT ─────────► PUBLISH
(you + research agents)  (tlc Specify→Tasks)  (tlc Execute)         (kappy-finalize)
        │                      ▲                    │
        │ rpi-handoff          │ reads              │ activates per file/topic:
        ▼ emits 2 prompts +    │ research.md        ▼ laravel-best-practices,
   research.md ────────────────┘               fortify-, inertia-react-,
   handoff-plan.md / handoff-implement.md       wayfinder-, tailwindcss-,
                                                pest-testing

  skill-architect ── orthogonal: builds/maintains the skills above
```

Key relationships:

- **`rpi-handoff` sits on top of `tlc-spec-driven`.** It does not plan or code; it produces the prompts that drive `tlc-spec-driven` in two separate, lean sessions (Plan, then Implement), splitting exactly at that skill's `Tasks | Execute` boundary.
- **`tlc-spec-driven` is the engine.** Its Specify/Design/Tasks phases write `.specs/features/<feature>/{spec,design,tasks}.md`; its Execute phase consumes `tasks.md` and delegates tasks to sub-agents.
- **Domain skills activate during Execute.** As a task touches a model, an Inertia page, a route, styling, or a test, the matching domain skill auto-engages so that slice of code follows Kappy conventions.
- **`kappy-finalize` closes the loop**, turning the completed change into a branch, commit, and PR.
- **`skill-architect` is orthogonal** — you use it to build new skills (it built `rpi-handoff`), not to ship features.

## Worked example — the modular-monolith conversion

This is the real workflow that produced `.specs/features/modular-monolith/` and the `app-modules/` setup in this repo.

**1. Research (this kind of session).** Parallel research agents investigated the Laracasts "Modular Laravel" approach, `internachi/modular` specifics, the `@inertiajs/vite` page-resolution mechanism, and Feature-Sliced Design. Decisions were locked (engine = `internachi/modular`; auth stays in `app/`; frontend = domain-first co-location). No code was written.

**2. Handoff — `rpi-handoff`.** At the end of research: "research is done, give me the handoff prompts." The skill ran its **Seam-Verification Gate** (every integration touch-point must be checked against ground truth — the real file or the real `--help`, not docs), wrote `.specs/features/modular-monolith/research.md`, and emitted two prompts saved to `handoff-plan.md` and `handoff-implement.md`.

**3. Plan — fresh session, paste Prompt 1.** It reads `research.md`, invokes `tlc-spec-driven` through Specify → Design → Tasks, and **hard-stops before Execute**. Output: `spec.md` (requirements FR-1..FR-13), `design.md` (architecture + 7 components), `tasks.md` (14 atomic tasks with dependencies and gates). You review `tasks.md` before going further.

**4. Implement — another fresh session, paste Prompt 2.** It reads `tasks.md` + `STATE.md` gotchas, invokes `tlc-spec-driven` Execute, and works task by task. As tasks land, domain skills engage:

- `laravel-best-practices` → the `Product` model, controller, migration in `app-modules/catalog`.
- `inertia-react-development` → the co-located `index.tsx` page and the `module::page` resolver in `app.tsx`.
- `wayfinder-development` → typed route functions for the new module route.
- `tailwindcss-development` → styling the catalog page (and the `@source` scan for `app-modules/**`).
- `pest-testing` → the catalog feature test asserting the Inertia component and props.

Process guards from the prompt apply throughout: sub-agent delegation per task, dependency-integrity check (`composer install --dry-run` must be a no-op), no blanket `git restore`, and cleanup of throwaway scaffolding. The gate (`php artisan test --compact`, `npm run lint`, `npm run types:check`, `npm run build`) stays green.

**5. Publish — `kappy-finalize`.** "Finalize this." It creates a branch like `feat/modular-monolith-catalog`, writes a Conventional Commit, pushes, and opens a ready-for-review PR.

## Smaller examples

**A focused change (no full RPI).** "Add a 2FA recovery-codes page." This is small enough to skip the handoff. You work directly; `fortify-development` drives the auth backend, `inertia-react-development` + `tailwindcss-development` build the page, `wayfinder-development` wires the route, `pest-testing` covers it, then `kappy-finalize` ships it.

**Building a new skill.** "Create a skill that does X." `skill-architect` runs Discovery → Architecture → Craft → Validate and produces a validated `SKILL.md` (this is how `rpi-handoff` was created). Mirror the result into the toolchains you use.

## When to reach for what

- **Big or ambiguous feature, want clean context per phase** → start with research, then `rpi-handoff`.
- **A feature you can plan in one sitting** → invoke `tlc-spec-driven` directly.
- **A small, well-understood change** → just build it; the domain skills auto-activate.
- **You keep re-explaining the same workflow** → capture it with `skill-architect`.
- **Work is done and verified** → `kappy-finalize`.
