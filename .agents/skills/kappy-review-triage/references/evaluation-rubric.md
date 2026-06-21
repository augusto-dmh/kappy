# Evaluation rubric — one sub-agent per finding

Each sub-agent evaluates ONE review finding independently and adversarially, grounded in the **current** code and git history — not in the original review's wording. Default to skepticism: a finding is only `RESOLVED`/`ADIADO`/`INVÁLIDO` when the evidence supports it.

## Inputs given to the sub-agent
- The finding: `path`, `line`, marker `TYPE` (security/tests/architecture/performance/regression/…), and the root comment body.
- The PR number + branch, and the review comment's `createdAt` timestamp (the cutoff for "fixed after the review").

## What to determine (return all five)

1. **real** — `yes` / `partial` / `no`. Read the current file at/around the anchor and decide whether the described problem genuinely exists in the code as it stands now. A finding can be `no` because it never held, or because the code no longer matches the description.
2. **fixed** + **fix_commit** — did a commit on this branch *after* the review address it, and which one? Detect from git history (below). `fixed=true` requires a concrete commit whose diff actually resolves the finding — not merely "the file changed".
3. **worth** — `must-fix` / `should-fix` / `optional` / `skip`. Only meaningful when `real` and not yet `fixed`. Weigh severity, spec/convention backing, blast radius, fix effort, and fix risk. Be honest: a real nit can still be `optional`/`skip`.
4. **why** — one or two sentences of evidence: quote the code/line, cite the rule/spec/convention or the commit, and say plainly why you reached `real`/`worth`.
5. **disposition** — derive per the policy: fixed → `RESOLVED`; real+worth+unfixed → `FLAG`; real+not-worth → `ADIADO`; not real → `INVÁLIDO`.

## Detecting the fix commit (auto, never guess)

Look only at commits that touched the finding's file **after** the review timestamp:

```bash
git log --oneline --since='<review createdAt>' -- <path>
git log -L<line>,<line>:<path> --no-patch        # history of the exact anchor lines
git log -p --since='<review createdAt>' -- <path> # inspect the diffs to find the one that addresses it
```

- Choose the commit whose diff **actually addresses the finding** (not an unrelated edit to the same file).
- If exactly one clearly addresses it → that is `fix_commit`.
- If **several** plausibly could, or none clearly does → do **not** guess: set `fixed` accordingly and return the **candidate SHAs** so the Step-3 gate can resolve it with the user.
- Use the short SHA (7+ chars) in the verdict.

## Verdict schema (return exactly this)

```json
{
  "real": "yes | partial | no",
  "fixed": true,
  "fix_commit": "e38ab3a",
  "fix_commit_candidates": [],
  "worth": "must-fix | should-fix | optional | skip",
  "why": "Evidence-grounded one/two-liner citing code/line, rule, or commit.",
  "disposition": "RESOLVED | ADIADO | INVALIDO | FLAG",
  "proposed_comment": "[RESOLVED] Resolvido em e38ab3a — …"
}
```

`proposed_comment` is the pt-BR follow-up in the exact `disposition-policy.md` format (omit for `FLAG`).

## Calibration
- Don't inflate severity to justify a comment; an honest `ADIADO` with a real reason is better than a forced `RESOLVED`/`FLAG`.
- `INVÁLIDO` needs a concrete refutation against current code, not just doubt.
- When `real=partial` (e.g. the factual core holds but the severity/label was overstated), prefer `ADIADO` with the nuance, unless it's actually fixed.
- Treat the marker `TYPE` as a hint, not a verdict — re-judge from the code.
