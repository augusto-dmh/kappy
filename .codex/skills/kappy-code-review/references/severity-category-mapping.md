# Severity and Category Mapping

Single table mapping schema-level severity + category to the display label and dot used in comment files.

| Category | Severity | Display emoji + label | Dot | When to use |
|---|---|---|---|---|
| bug | high | `⚠️ **Potential issue**` | `🔴 **High**` | Broken behavior, wrong identifier passed to a route/method, schema/model contradiction, broken authorization gate, lost idempotency. |
| bug | medium | `⚠️ **Potential issue**` | `🟠 **Medium**` | Latent bug on a narrow path; correctness regression behind a flag; off-by-one outside a hot path. |
| bug | low | `🛠️ **Refactor suggestion**` | `🟡 **Low**` | Defensive guard for a case the code already excludes; harmless redundancy. |
| security | high | `⚠️ **Potential issue**` | `🔴 **High**` | Auth bypass, mass-assignment of sensitive columns, weakened webhook/signature verification, PII in logs, secrets in code. |
| security | medium | `⚠️ **Potential issue**` | `🟠 **Medium**` | LGPD over-fetch of PII to the front-end without need-basis; missing PII-guard test; permission gate present but untested. |
| perf | high | `⚠️ **Potential issue**` | `🔴 **High**` | N+1 on a hot endpoint, blocking I/O on the request path, ingestion job without overlap protection. |
| perf | medium | `🛠️ **Refactor suggestion**` | `🟠 **Medium**` | Eager-load gap on a list; redundant query in a loop; full-table scan on a per-request query. |
| perf | low | `🛠️ **Refactor suggestion**` | `🟡 **Low**` | Unindexed search on a small/medium table unlikely to grow much. |
| maintainability | high | `🛠️ **Refactor suggestion**` | `🔴 **High**` | Hard-rule violation: missing tests in the same PR; net-new page outside `resources/js/pages`. |
| maintainability | medium | `🛠️ **Refactor suggestion**` | `🟠 **Medium**` | Fat controller (query/aggregation inlined), business logic in a model, job not delegating to a service, missing enum for a domain state. |
| maintainability | low | `🛠️ **Refactor suggestion**` | `🟡 **Low**` | Doc/code mismatch; inconsistent null-handling between a TS type and the PHP payload; idiom inconsistency that doesn't change behavior. |
| nitpick | trivial | `🧹 **Nitpick**` | — | Cosmetic, idiom, naming, micro-readability, fragile-but-working pattern. Grouped in `nitpicks.md`, never inline. |

## Mapping rules

- **Severity is impact, not category.** A perf issue on the request path can be high; an unindexed search on a small table is low.
- **Hard-rule violations are always at least High** even when technically maintainability (tests-in-same-PR, page placement). They block merge.
- **PII over-fetch is at least Medium security**, even if the field is never rendered — the over-fetch itself is the issue.
- **Nitpicks are grouped**, never inline.
- **Potential issue vs Refactor suggestion** — does the diff change observable behavior in a wrong way (Potential issue) or just structure code against convention (Refactor suggestion)? Performance fits either, depending on whether users feel the latency now.
