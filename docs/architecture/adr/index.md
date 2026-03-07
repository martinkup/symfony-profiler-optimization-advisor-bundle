# Architecture Decision Records

[Docs Hub](../../README.md) / **ADRs**

## ADR Index

| #                                                  | Title                              | Status   | Summary                                                                                 |
|----------------------------------------------------|------------------------------------|----------|-----------------------------------------------------------------------------------------|
| [0001](0001-late-data-collector-priority.md)       | Late Data Collector Priority       | Accepted | Use `LateDataCollectorInterface` with priority `-100` to run after all other collectors |
| [0002](0002-origin-classification.md)              | Origin Classification              | Accepted | Three-tier classification (app/infra/profiler); only app generates opportunities        |
| [0003](0003-messenger-middleware-compiler-pass.md) | Messenger Middleware Compiler Pass | Accepted | Auto-register tracing middleware via CompilerPass to handle `when@dev` overrides        |
| [0004](0004-security-redaction-for-mcp-output.md)  | Security Redaction for MCP Output  | Accepted | Pattern-based `SecurityRedactor` with defense-in-depth and fail-closed regex            |

---

[&larr; Extending](../../integration/extending.md) | [Next: ADR-0001 &rarr;](0001-late-data-collector-priority.md)
