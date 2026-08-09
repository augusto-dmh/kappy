# Git hooks (Kappy)

These hooks keep commit history navigable: Conventional Commits only, and **no** AI/tooling attribution trailers (`Co-authored-by: Cursor`, `Made-with:`, etc.).

## Enable (once per clone)

```bash
git config core.hooksPath .githooks
```

Optional — confirm:

```bash
git config core.hooksPath
# → .githooks
```

## What runs

| Hook | Role |
| ---- | ---- |
| `prepare-commit-msg` | Strips `Co-authored-by:` / `Made-with:` / “Generated with …” lines Cursor may inject |
| `commit-msg` | Validates Conventional Commits via `scripts/check-commit-message` and rejects any remaining attribution |

CI runs the same checker on pull-request commit ranges so skipped local hooks still fail the build.

## Cursor IDE / CLI

Also turn off Agent attribution in the UI (Cursor Settings → Agent / Git & PRs → Attribution) and, for the CLI agent, set `attribution.attributeCommitsToAgent: false` in `~/.cursor/cli-config.json`. The hook and CI are the hard guarantee when the IDE still injects a trailer.
