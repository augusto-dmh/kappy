# Disposition policy

Each unresolved `kappy-review` finding gets exactly one disposition and, for three of them, one pt-BR follow-up comment added to its thread. The follow-up format mirrors the project's established pattern (the last merged PR): a bracketed status tag, then a concise reason, and — for fixes — the commit short SHA.

## The four dispositions

| Disposition | When | Follow-up comment (pt-BR) | Thread action |
| --- | --- | --- | --- |
| `RESOLVED` | The finding is real **and** addressed by a commit on the branch | `[RESOLVED] Resolvido em <short-sha> — <o que mudou>` | **Resolve** the thread |
| `ADIADO` | The finding is real but **not worth acting on now** (optional/skip after evaluation) | `[ADIADO] Mantido aberto de propósito. <motivo>` | **Keep open** |
| `INVÁLIDO` | The finding is **not a real issue** (false positive against the current code) | `[INVÁLIDO] Não procede — <por quê>` | **Resolve** the thread |
| `FLAG` | The finding is real and worth acting on but **not yet fixed** | *(no comment)* | **Leave untouched**; route to `pr-execute` |

## Format rules

- **pt-BR**, one line per follow-up, concise — state the substance, not a paragraph.
- `[RESOLVED]` **must** cite a real short SHA (7+ chars) that actually addresses the finding. No SHA → it is not `RESOLVED`.
- `[ADIADO]` gives the *reason it's deferred* (cost/benefit, low risk, convention not yet adopted), not a vague "later".
- `[INVÁLIDO]` explains *why it doesn't hold* against the current code (e.g. the guard already exists, the value is safe-by-coercion).
- Keep the original `<!-- pr-review:TYPE -->` root comment untouched (legacy `<!-- kappy-review:TYPE -->` accepted); the disposition is a **reply** beneath it when a GitHub thread exists.
- `FLAG` never produces a comment — silence keeps `[RESOLVED]` meaningful and avoids promising work on the thread.
- **Internal artifacts ground, never cite.** A follow-up may cite real code in the diff, sibling code, or a commit SHA — never an internal planning/spec artifact (`.specs/**`: PROJECT, STATE, ROADMAP, research, design, spec, tasks) or a skill rule-file path, nor its line number. Read them to justify the disposition; state their substance in plain prose in the comment.
- **Plain language, enough overview.** Write the *why* for a developer who doesn't share the internal vocabulary: explain the consequence, introduce terms before leaning on them, avoid bare invariant-names/jargon ("no diff at rest", "fixtures"). Concise but not shallow.

## Worked examples (the shape to match)

```text
[RESOLVED] Resolvido em e38ab3a — o estado passa a ser derivado do payload do PR
(`pull_request.merged`, senão `pull_request.state`) em vez da action, então uma action
não-`closed` não reverte mais um PR já merged para open. Inclui teste de regressão.

[ADIADO] Mantido aberto de propósito. Overhead constante de 1 query num PATCH de toggle
de linha única (não é N+1); irrelevante na frequência real do endpoint.

[INVÁLIDO] Não procede — a verificação de assinatura já usa `hash_equals` e o segredo é
lido com guarda fail-closed, então não há comparação insegura nem bypass possível.
```

## Resolving threads

`RESOLVED` and `INVÁLIDO` resolve the thread; `ADIADO` leaves it open (a deliberate, visible "won't do now"); `FLAG` leaves it exactly as-is. Resolution uses the GraphQL `resolveReviewThread` mutation (handled by `scripts/post_dispositions.py`); the author resolving their own threads is the established self-review flow in this repo.
