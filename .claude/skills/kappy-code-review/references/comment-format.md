# Comment Format Reference

Single source of truth for the comment format used by `kappy-code-review`. Every file under `code-review-<slug>/` must match this template exactly. GitHub renders markdown reliably, so the format optimizes for clean inline anchoring and one-comment-per-finding posting — not for any editor paste workaround.

## Anchor header (machine-readable, required on every per-finding file)

Every per-finding file MUST start with an HTML-comment line declaring the inline anchor:

```
<!-- ANCHOR: app/path/to/File.php:LINE -->
```

Then a blank line, then the visible content begins with the category-emoji header. The HTML comment is invisible when rendered and lets [scripts/verify-anchors.sh](../scripts/verify-anchors.sh) validate every anchor against `git diff origin/main...HEAD`, and lets [scripts/post-review.sh](../scripts/post-review.sh) map the file to one inline PR comment. `_summary.md`, `INDEX.md`, and `nitpicks.md` carry no anchor header — they are not inline.

## Canonical template

```
<!-- ANCHOR: app/path/to/File.php:LINE -->

<category-emoji> **<Category>** | <severity-dot> **<Severity>**

**<Bold title — no file:line; the inline anchor handles it>**

<Prose body in pt-BR. Inline `code` and ```php / ```tsx fenced snippets allowed.>

- bullets for sub-points when needed

Fix sugerido: <single recommendation woven inline, with refs to laravel-best-practices/rules/*.md, .specs/*, prior commits, or a sibling feature>

**🤖 Prompt for AI Agents**

​```
Verify each finding against the current code and only fix it if needed.

<English actionable prompt naming the exact file:line and the precise change required.>
​```
```

(The `​` marks above are only to escape the nested fence in this reference doc — real files use bare triple backticks.)

## Field rules

- **Category emoji + label** — one of `⚠️ **Potential issue**` (bugs, security, blocking risks), `🛠️ **Refactor suggestion**` (structure or convention divergence), `🧹 **Nitpick**` (trivial polish). Both emoji and label are bold.
- **Severity dot + label** — `🔴 **High**`, `🟠 **Medium**`, `🟡 **Low**`. Both bold. Nitpicks omit the dot.
- **Title** — bold, single line, no `file:line`. The inline anchor handles location.
- **Prose** — pt-BR. May include inline backticked code, ```php / ```tsx / ```ts fenced snippets, bulleted sub-points, and `path:line` cross-references.
- **`Fix sugerido:`** — single recommendation, not a menu. Ground it in a specific source: `laravel-best-practices/rules/<file>.md`, a `pest-testing` rule, `.specs/features/<feature>/*`, a prior commit SHA, or a sibling feature path.
- **Prompt for AI Agents** — always English, plain section (bold `**🤖 Prompt for AI Agents**` header, then a fenced code block — no `<details>` wrapper). Starts with `Verify each finding against the current code and only fix it if needed.` Then names file:line and the precise change.

## File kinds

| File | Purpose | Posted as |
|---|---|---|
| `_summary.md` | PR-level summary (risk + walkthrough + counts). | General PR comment (`gh pr comment`). |
| `B<n>-<slug>.md` | High-severity blocking finding (prevents merge). | Inline comment on the anchored line. |
| `H<n>-/M<n>-/L<n>-<slug>.md` | High / medium / low non-blocking finding. | Inline comment on the anchored line. |
| `nitpicks.md` | All `🧹 Nitpick` items grouped into one comment (bulleted). | General PR comment; never inline. |
| `INDEX.md` | Anchor table + posting/paste guidance. Reference doc for the human. | Not posted. |

## Posting and paste guidance (verbatim in INDEX.md)

1. **Automated (preferred):** on explicit confirmation, the skill runs `scripts/post-review.sh <pr-number> code-review-<slug>`, which posts each per-finding file as an inline comment on its anchored line and `_summary.md` as a general PR comment.
2. **Manual:** open the PR "Files changed" tab, click the `+` on the cited `file:line`, and paste the entire per-finding file body. `_summary.md` and `nitpicks.md` go in the bottom PR comment box. GitHub renders the markdown directly — no code-block fallback is needed.

## INDEX.md template

```
# Code review — <slug> — comentários para o PR no GitHub

Cada arquivo desta pasta é um comentário (inline para os findings; geral para _summary.md e nitpicks.md). Pode postar automático via scripts/post-review.sh ou colar manualmente o conteúdo inteiro de um arquivo na linha indicada.

| Arquivo | Âncora (arquivo:linha) | Categoria | Severidade |
|---|---|---|---|
| `_summary.md` | comentário geral do PR (não-inline) | — | Risco: <ALTO/MÉDIO/BAIXO> |
| ...           | ...                                  | ... | ... |

Para postar: ./.claude/skills/kappy-code-review/scripts/post-review.sh <pr-number> code-review-<slug>
```

## _summary.md shape

```
**Resumo do Code Review** (Risco: <ALTO/MÉDIO/BAIXO>)

**Walkthrough**

<Parágrafo único em pt-BR resumindo o que a branch entrega, o que ficou bem feito, e onde estão os pontos fracos. Cita .specs/* ou laravel-best-practices/rules/* quando relevante.>

**<Solicito alterações | Aprovado com ressalvas | Aprovado>** — <justificativa em uma frase>.

Findings: 🔴 High ×N · 🟠 Medium ×N · 🟡 Low ×N · 🧹 Nitpicks ×N
```

## Anti-patterns

- File:line in the bold title — the inline anchor already handles location.
- Multiple findings concatenated into one inline comment — one finding per file/comment so each anchors to its own line.
- Portuguese inside the Prompt for AI Agents body — prompts are English for downstream agent consumption.
- `Fix sugerido:` as a weighed choice ("opção A ou opção B") — pick one and cite the rule that grounds it.
- Echoing real PII in the prose — anonymize with placeholders or local-id refs.
