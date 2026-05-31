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
5. Run:

```bash
python .claude/skills/kappy-finalize/scripts/validate_metadata.py \
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
3. Draft concise Markdown for the summary, changes, and verification sections from the diff and verification output.
4. Run `scripts/render_pr_body.py` to assemble the PR body. Pass screenshots only when the PR contains visible UI changes. Pass related issues only when applicable.

```bash
python .claude/skills/kappy-finalize/scripts/render_pr_body.py \
  --summary-file /tmp/kappy-pr-summary.md \
  --changes-file /tmp/kappy-pr-changes.md \
  --verification-file /tmp/kappy-pr-verification.md \
  --output /tmp/kappy-pr-body.md
```

5. Read [assets/pull_request_template.md](assets/pull_request_template.md) only when the renderer cannot be used or the user explicitly asks to inspect the template.
6. Create an open ready-for-review PR automatically with `gh pr create --base <base-branch> --head <branch-name> --title '<pr-title>' --body-file <pr-body-file>`. Do not create draft PRs.
7. When a PR already exists, update its title or body with `gh pr edit`.

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
