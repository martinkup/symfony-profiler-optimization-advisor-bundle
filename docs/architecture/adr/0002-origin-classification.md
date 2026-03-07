# ADR-0002: Origin Classification

[Docs Hub](../../README.md) / [ADRs](index.md) / **ADR-0002**

## Status

**Accepted**

## Context

Raw profiler data contains signals from three distinct sources:

1. **Application code** — queries, cache calls, template renders, and event listeners belonging to the user's project
2. **Framework infrastructure** — Doctrine migrations, Symfony internal listeners, system catalog queries
3. **Profiler overhead** — the WebProfiler's own cache calls, templates, and listeners

Without classification, the noise from framework and profiler signals drowns out actionable findings from the user's code.

## Decision

Implement a three-tier origin classification system: `app`, `infra`, and `profiler`. Only `app`-origin signals are evaluated by the AdvisorEngine to generate opportunities.

Each analyzer applies classification rules specific to its signal type:

```mermaid
flowchart TD
    SIG[Signal] --> TYPE{Signal type?}
    TYPE -->|DB query| DB1{SQL contains<br/>CURRENT_DATABASE etc.?}
    DB1 -->|Yes| INFRA[infra]
    DB1 -->|No| DB2{Table starts with<br/>pg_ or information_schema?}
    DB2 -->|Yes| INFRA
    DB2 -->|No| DB3{Table in<br/>infra_db_tables?}
    DB3 -->|Yes| INFRA
    DB3 -->|No| APP[app]
    TYPE -->|Cache pool| CP1{Pool starts with<br/>profiler_cache_pool_prefixes?}
    CP1 -->|Yes| PROF[profiler]
    CP1 -->|No| CP2{Pool starts with<br/>app_cache_pool_prefixes?}
    CP2 -->|Yes| APP
    CP2 -->|No| INFRA
    TYPE -->|Twig template| TW1{Template starts with<br/>profiler_template_prefixes?}
    TW1 -->|Yes| PROF
    TW1 -->|No| APP
    TYPE -->|Event listener| EV1{Namespace in<br/>profiler_event_namespace_prefixes?}
    EV1 -->|Yes| PROF
    EV1 -->|No| EV2{Class in<br/>profiler_event_classes?}
    EV2 -->|Yes| PROF
    EV2 -->|No| EV3{Starts with<br/>app_namespace_prefix?}
    EV3 -->|Yes| APP
    EV3 -->|No| INFRA
    TYPE -->|Messenger handler| MH1{Starts with<br/>app_namespace_prefix?}
    MH1 -->|Yes| APP
    MH1 -->|No| INFRA
    TYPE -->|HTTP call| HTTP_APP[app]
```

## Consequences

- Users only see opportunities relevant to their code
- Infrastructure and profiler signals are still collected for informational display
- All classification rules are configurable via bundle parameters
- The `app_namespace_prefix` setting (`App\` by default) must match the project's root namespace

## Alternatives Rejected

| Alternative                | Why Rejected                                                                                                                  |
|----------------------------|-------------------------------------------------------------------------------------------------------------------------------|
| Two-tier (app vs. non-app) | Cannot distinguish profiler overhead from framework infra — profiler cache calls would inflate infra metrics                  |
| No classification          | Too noisy — framework internals generate many false-positive opportunities (e.g., Doctrine migration queries flagged as slow) |
| Allowlist-only             | Too restrictive — new user code would need manual registration                                                                |

---

[&larr; ADR-0001](0001-late-data-collector-priority.md) | [Next: ADR-0003 &rarr;](0003-messenger-middleware-compiler-pass.md)
