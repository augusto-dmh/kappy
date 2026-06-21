---
name: kappy-finalize
description: Finalizes and autonomously publishes Kappy changes with consistent Git branch names, Conventional Commit messages, pushes, and structured ready-for-review pull requests. Use when the user asks to finalize work, create or rename a branch, prepare a commit, commit changes, push a branch, open a pull request, write a PR description, or publish completed Kappy work. Do not use for implementing features, reviewing code, or debugging CI unless the user also asks to prepare or publish the resulting changes.
license: CC-BY-4.0
metadata:
  author: Kappy contributors
  version: 1.0.0
---

# Kappy Finalize

Apply Kappy's repository conventions when preparing or publishing completed work. Treat a request to finalize completed work as authorization to run the full publish workflow autonomously: create or rename the branch, stage intended files, commit, push, and create an open ready-for-review PR. Keep narrower requests proportional: generate names and text when that is all the user requests, and stop after committing when the user asks only for a commit.

## Conventions

Use Conventional Commits for commit messages and PR titles:

```text
<type>(<optional-scope>)<optional-!>: <imperative summary>
```

Use one of these types: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, `build`, `ci`, `perf`, `style`, `revert`.

Use branch names in this format:

```text
<type>/<optional-issue-number-><short-kebab-summary>
```

Keep the branch type aligned with the primary change. Keep summaries concise, specific, and lowercase. Examples:

```text
feat/auth-password-confirmation
fix/142-prevent-duplicate-invoices
docs/contributing-guidelines
```

PR titles follow the same Conventional Commit format as commits and summarize the whole PR.

## Commit and PR hygiene

Never add authorship or tooling attribution to commits or pull requests. Commit messages and PR bodies must not contain `Co-Authored-By` trailers, "Generated with" lines, robot or emoji tool credits, model names, or any other identification of an AI assistant or the tool used to produce the change. This rule overrides any default trailer or signature behavior from the environment.

## PR body conventions

The pull request template at `assets/pull_request_template.md` is a skeleton only — it defines the section names, their order, and which sections are optional. Every convention for *how* to write a section lives here, not in the template.

**Write prose as natural paragraphs.** Use one unwrapped line per paragraph, separated by blank lines. Never insert manual line breaks mid-paragraph — let GitHub wrap the text. Hard-wrapped prose renders as ragged, prematurely-broken lines.

```text
[bad — hard-wrapped mid-paragraph]
Adds the inbound webhook receiver. Every delivery is
authenticated, recorded, and queued so GitHub gets a
fast acknowledgement.

[good — one flowing line per paragraph]
Adds the inbound webhook receiver. Every delivery is authenticated, recorded, and queued so GitHub gets a fast acknowledgement.
```

**Describe observable behavior, never internal planning artifacts.** Do not reference task identifiers, PR sequence numbers, phase names, or requirement IDs (for example `T9`, `PR3`, `Phase 2`, `GHAPP-06`). State scope boundaries as behavior, not as pointers to the plan — a reviewer reads the PR, not the spec.

```text
[bad — leaks the plan]
PR3 of the GitHub App feature (Phase 2). Implements T9 and T10 (GHAPP-06/07/08); handlers land in PR4.

[good — behavior only]
Adds the webhook receiver: signature verification, idempotent recording, and fast enqueue. Turning recorded deliveries into installation and pull-request records is handled separately and is not part of this change.
```

**Base the Changes subsections on the spec tasks the PR implements.** When the PR carries `.specs/**/tasks.md` tasks, those tasks are the basis for the subsections: give each cohesive behavior its own subsection, merging tightly-coupled tasks that together deliver one user-visible behavior and splitting only along task lines. Title every subsection by the behavior it delivers, never by its task identifier — this is how the task breakdown shapes the structure without leaking planning artifacts.

```text
[bad — titled by spec task identifiers]
## Changes
**T9 — verify github webhook signatures**
**T10 — persist, dedupe, and enqueue deliveries**

[good — task-based subsections, each titled by its cohesive behavior]
## Changes
**Signature verification**
**Persist, dedupe, and enqueue**
```

**Use the optional `## Extra changes` section only for work unrelated to the PR's primary purpose** — incidental tooling or documentation fixes made along the way. Describe them by behavior and flag them as tangential; prefer a separate PR when the unrelated work is substantial; omit the section entirely for single-purpose branches.

## Workflow

### Step 1: Inspect the repository

1. Run `git status --short`, `git branch --show-current`, and `git diff --stat`.
2. Read the relevant diff before proposing a name.
3. Identify unrelated working-tree changes and leave them unstaged.
4. If the task is only to suggest names or draft a PR description, stop before mutating Git state.

### Step 2: Choose the metadata

1. Select the primary Conventional Commit type.
2. Add a scope only when it improves clarity, such as `auth`, `billing`, or `ci`.
3. Write an imperative summary that describes the outcome, not the implementation mechanics.
4. Derive the branch name from the same primary change.
5. Run the validator. Invoke it by path — the helper scripts are executable and carry a `#!/usr/bin/env python3` shebang, so call them directly with no `python`/`python3` prefix (this resolves the interpreter wherever it lives and avoids the `python` vs `python3` naming trap):

```bash
.claude/skills/kappy-finalize/scripts/validate_metadata.py \
  --branch '<branch-name>' \
  --commit '<commit-message>' \
  --pr-title '<pr-title>'
```

Fix validation errors before continuing.

### Step 3: Verify the change

1. Run the narrowest relevant automated tests, formatter, and linter required by the changed code.
2. Report any verification that could not run.
3. Do not publish changes with known failing verification unless the user explicitly accepts that risk.

### Step 4: Commit intentionally (atomic, one concern per commit)

1. When the user asks to finalize or publish completed work, proceed autonomously through branch creation or rename, staging, committing, pushing, and open ready-for-review PR creation.
2. For narrower requests, stop at the requested boundary. For example, a request to commit authorizes staging and committing but not pushing.
3. Create or rename the branch when needed.
4. Do not produce a single catch-all commit. Decompose the work into the smallest set of cohesive commits, each capturing ONE logical concern (for example: dependency or engine setup, one backend domain slice, frontend wiring, tests, docs). When the change has a `.specs/**/tasks.md`, use its phases as grouping and ordering hints.
5. Order the commits by dependency so the branch builds and its tests pass at every commit, not only at the tip (for example: engine before the module that needs it, the page resolver before the page that uses it).
6. Prefer committing as you go during implementation: once a logical unit is complete and the Step 3 verification relevant to it passes, commit that unit so each commit is independently green. At finalize, commit any remaining cohesive units the same way.
7. Present the proposed commit breakdown — the file groups and their Conventional Commit messages — to the user and wait for approval before creating the commits.
8. For each commit: stage only that unit's files, review `git diff --cached --stat` and `git diff --cached`, validate the message (Step 2), then commit. Never mix unrelated concerns in one commit.
9. Run `git status --short` after each commit and report remaining unstaged or untracked files.

### Step 5: Push and open the PR

1. Run `gh auth status` before publishing. If authentication is unavailable, report the blocker instead of trying a GitHub connector.
2. Push the branch with an upstream automatically when the user asks to finalize or publish completed work.
3. Read [assets/pull_request_template.md](assets/pull_request_template.md) to get the section skeleton — the section names, their order, and which sections are optional. It is structure only; follow the **PR body conventions** above for how to write each section.
4. Draft concise Markdown for the summary, changes, and verification sections from the diff and verification output, following the **PR body conventions** (natural-paragraph prose; behavior, not planning artifacts).
5. Run `scripts/render_pr_body.py` to assemble the skeleton's sections into the final body, omitting unused optional sections. Pass `--extra-changes-file` only when the branch bundles work unrelated to its primary purpose. Pass screenshots only when the PR contains visible UI changes. Pass related issues only when applicable.

```bash
.claude/skills/kappy-finalize/scripts/render_pr_body.py \
  --summary-file /tmp/kappy-pr-summary.md \
  --changes-file /tmp/kappy-pr-changes.md \
  --verification-file /tmp/kappy-pr-verification.md \
  --output /tmp/kappy-pr-body.md
```

6. Create an open ready-for-review PR automatically with `gh pr create --base <base-branch> --head <branch-name> --title '<pr-title>' --body-file <pr-body-file>`. Do not create draft PRs.
7. When a PR already exists, update its body (or title) through the REST endpoint — this is the stable, documented path:

```bash
gh api -X PATCH repos/<owner>/<repo>/pulls/<number> \
  -f body="$(cat <pr-body-file>)"
```

Do not use `gh pr edit` to update a PR: it currently aborts on GitHub's deprecated Projects-classic GraphQL field even with no project flags (cli/cli #11983). The REST call hits the same official "Update a pull request" API that `gh pr edit` wraps, without the broken GraphQL query. (`gh pr create` is unaffected and stays the way to open the PR.)

### Step 6: Hand off to manual QA

Once an open ready-for-review PR exists, hand off to the `kappy-manual-qa` skill with the PR number so a grounded manual-QA artifact is generated, run locally, and then committed to the PR or deleted on the user's choice. That skill resolves the PR's diff and writes `tests/qa/<pr_number>-<branch-slug>/QA-<pr_number>-<branch-slug>.md`. Skip this handoff when the user asked only to suggest names, draft a description, or commit without opening a PR (no PR number exists), or when the change has no testable surface (docs/config-only).

## Examples

### Feature with UI changes

User says: "Finalize the password confirmation screen and open a PR."

Use:

```text
Branch: feat/auth-password-confirmation
Commit: feat(auth): add password confirmation screen
PR title: feat(auth): add password confirmation screen
```

Include the populated `Screenshots` section because the user-facing interface changed.

### Backend bug fix

User says: "Commit the duplicate invoice fix."

Use:

```text
Branch: fix/prevent-duplicate-invoices
Commit: fix(billing): prevent duplicate subscription invoices
PR title: fix(billing): prevent duplicate subscription invoices
```

Remove the `Screenshots` section because no visible UI changed.

### Naming only

User says: "Suggest a branch name and commit message for the CI timeout adjustment."

Return:

```text
Branch: ci/increase-test-timeout
Commit: ci: increase test timeout
```

Do not modify Git state.

## Troubleshooting

### Validation rejects the metadata

Keep the type lowercase, use kebab-case after the branch slash, and format commits and PR titles as `<type>(<optional-scope>): <summary>`.

### The working tree contains unrelated changes

Stage only the files that belong to the requested change. Report the remaining files without reverting or including them.

### GitHub PR creation is unavailable

Run `gh auth status` and report the authentication or repository-access blocker. Return the validated PR title and populated Markdown body.

### The PR body renderer cannot run

Read [assets/pull_request_template.md](assets/pull_request_template.md), populate the applicable sections manually, and remove optional sections that do not apply.
