#!/usr/bin/env bash
# Usage: post-review.sh <pr-number> <comments-dir> [--force]
#
# Posts a kappy-code-review folder to a GitHub PR via `gh`:
#   - each per-finding *.md  -> one INLINE review comment on its anchored line
#                               (gh api repos/{owner}/{repo}/pulls/{n}/comments,
#                                using the PR head commit as commit_id, side=RIGHT)
#   - _summary.md, nitpicks.md -> one GENERAL PR comment each (gh pr comment)
#
# Run ONLY after the user explicitly confirms posting. The skill never calls
# this without a go-ahead in the same turn. Re-running is blocked by a
# <comments-dir>/.posted marker unless --force is passed.
#
# Requires: gh (authenticated), jq.
# Exit 0 -> all comments posted; exit 2 -> setup error; exit 1 -> a post failed.
set -euo pipefail

pr="${1:?usage: post-review.sh <pr-number> <comments-dir> [--force]}"
dir="${2:?usage: post-review.sh <pr-number> <comments-dir> [--force]}"
force="${3:-}"

command -v gh >/dev/null 2>&1 || { echo "post-review: gh not found on PATH" >&2; exit 2; }
command -v jq >/dev/null 2>&1 || { echo "post-review: jq not found on PATH" >&2; exit 2; }
[[ -d "$dir" ]] || { echo "post-review: directory not found: $dir" >&2; exit 2; }

if [[ -f "$dir/.posted" && "$force" != "--force" ]]; then
  echo "post-review: $dir/.posted exists — this review was already posted. Re-run with --force to post again." >&2
  exit 2
fi

repo="$(gh repo view --json nameWithOwner -q .nameWithOwner)"
sha="$(gh pr view "$pr" --json headRefOid -q .headRefOid)"
[[ -n "$repo" && -n "$sha" ]] || { echo "post-review: could not resolve repo/head SHA for PR #$pr" >&2; exit 2; }

echo "post-review: PR #$pr on $repo (head $sha)"
fail=0
inline=0

# Inline per-finding comments.
while IFS= read -r f; do
  bn="$(basename "$f")"
  case "$bn" in
    _summary.md|INDEX.md|nitpicks.md) continue ;;
  esac
  anchor="$(grep -m1 -oE '<!-- ANCHOR: [A-Za-z0-9_./\-]+:[0-9]+ -->' "$f" 2>/dev/null | sed -E 's/^<!-- ANCHOR: ([^ ]+) -->/\1/' || true)"
  if [[ -z "$anchor" ]]; then
    echo "post-review: skipping $bn (no anchor header)" >&2
    fail=1
    continue
  fi
  path="${anchor%:*}"
  line="${anchor##*:}"
  # Body = file content minus the anchor header line (and a leading blank line).
  body="$(sed '1{/^<!-- ANCHOR: .* -->$/d};' "$f" | sed '1{/^$/d}')"

  if jq -n --arg body "$body" --arg commit "$sha" --arg path "$path" --argjson line "$line" \
       '{body:$body, commit_id:$commit, path:$path, line:($line|tonumber), side:"RIGHT"}' \
     | gh api --method POST "repos/$repo/pulls/$pr/comments" --input - >/dev/null; then
    echo "  inline  $anchor  ($bn)"
    inline=$((inline + 1))
  else
    echo "  FAILED  $anchor  ($bn)" >&2
    fail=1
  fi
done < <(find "$dir" -maxdepth 1 -type f -name '*.md' | sort)

# General PR comments.
for general in "$dir/_summary.md" "$dir/nitpicks.md"; do
  [[ -f "$general" ]] || continue
  if gh pr comment "$pr" --body-file "$general" >/dev/null; then
    echo "  general $(basename "$general")"
  else
    echo "  FAILED  general $(basename "$general")" >&2
    fail=1
  fi
done

if (( fail )); then
  echo "post-review: one or more comments failed to post." >&2
  exit 1
fi

date > "$dir/.posted"
echo "post-review: posted $inline inline comment(s) + summary to PR #$pr"
