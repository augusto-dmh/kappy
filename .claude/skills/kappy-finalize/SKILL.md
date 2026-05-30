---
name: kappy-finalize
description: Finalizes and autonomously publishes Kappy changes with consistent Git branch names, Conventional Commit messages, pushes, and structured draft pull requests. Use when the user asks to finalize work, create or rename a branch, prepare a commit, commit changes, push a branch, open a pull request, write a PR description, or publish completed Kappy work. Do not use for implementing features, reviewing code, or debugging CI unless the user also asks to prepare or publish the resulting changes.
license: CC-BY-4.0
metadata:
  author: Kappy contributors
  version: 1.0.0
---

# Kappy Finalize

Apply Kappy's repository conventions when preparing or publishing completed work. Treat a request to finalize completed work as authorization to run the full publish workflow autonomously: create or rename the branch, stage intended files, commit, push, and create a draft PR. Keep narrower requests proportional: generate names and text when that is all the user requests, and stop after committing when the user asks only for a commit.

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

### Step 4: Commit intentionally

1. When the user asks to finalize or publish completed work, proceed autonomously through branch creation or rename, staging, committing, pushing, and draft PR creation.
2. For narrower requests, stop at the requested boundary. For example, a request to commit authorizes staging and committing but not pushing.
3. Create or rename the branch when needed.
4. Stage only the intended files. If unrelated files are present and the intended scope cannot be determined safely, ask the user before staging.
5. Review `git diff --cached --stat` and `git diff --cached`.
6. Create a commit using the validated Conventional Commit message.
7. Run `git status --short` after committing and report remaining unstaged or untracked files.

### Step 5: Push and open the PR

1. Push the branch with an upstream automatically when the user asks to finalize or publish completed work.
2. Draft concise Markdown for the summary, changes, and verification sections from the diff and verification output.
3. Run `scripts/render_pr_body.py` to assemble the PR body. Pass screenshots only when the PR contains visible UI changes. Pass related issues only when applicable.

```bash
python .claude/skills/kappy-finalize/scripts/render_pr_body.py \
  --summary-file /tmp/kappy-pr-summary.md \
  --changes-file /tmp/kappy-pr-changes.md \
  --verification-file /tmp/kappy-pr-verification.md \
  --output /tmp/kappy-pr-body.md
```

4. Read [assets/pull_request_template.md](assets/pull_request_template.md) only when the renderer cannot be used or the user explicitly asks to inspect the template.
5. Create a draft PR automatically unless the user explicitly requests a ready-for-review PR.
6. Prefer the available GitHub integration for PR creation. Use `gh pr create` when the integration is unavailable.

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

Try the available GitHub integration first, then `gh pr create`. If neither path can create the PR, report the blocker and return the validated PR title and populated Markdown body.

### The PR body renderer cannot run

Read [assets/pull_request_template.md](assets/pull_request_template.md), populate the applicable sections manually, and remove optional sections that do not apply.
