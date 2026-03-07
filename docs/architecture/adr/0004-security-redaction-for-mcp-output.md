# ADR-0004: Security Redaction for MCP Output

[Docs Hub](../../README.md) / [ADRs](index.md) / **ADR-0004**

## Status

**Accepted**

## Context

When `symfony/ai-symfony-mate-extension` is installed, the bundle exposes profiler data to AI assistants via two MCP channels:

1. `OptimizationAdvisorTool` — direct tool queries
2. `OptimizationAdvisorCollectorFormatter` — profiler resource URI

This data may contain:

- **URI query strings** with tokens, API keys, session IDs
- **URI path segments** with email addresses or PII
- **SQL queries** with literal values (emails, passwords, names)
- **SQL parameters** with sensitive key-value pairs

Exposing this data to AI assistants without redaction creates a security risk.

## Decision

Implement a pattern-based `SecurityRedactor` with:

### Three Configurable Pattern Arrays

| Config                     | Match Type                         | Purpose                                                       |
|----------------------------|------------------------------------|---------------------------------------------------------------|
| `sensitive_param_patterns` | Key substring (case-insensitive)   | Match parameter keys containing sensitive terms               |
| `sensitive_value_patterns` | Regex on values (case-insensitive) | Match values matching sensitive patterns (e.g., email format) |
| `sensitive_query_params`   | Exact name (case-insensitive)      | Match URI query parameter names exactly                       |

### Redaction Rules

| Data Path                                  | Rule                                                                                                                                      |
|--------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------|
| `origin.uri` path segments                 | Values matching `sensitive_value_patterns` regex replaced with `***REDACTED***`                                                           |
| `origin.uri` query params                  | Params matching `sensitive_query_params` (exact), `sensitive_param_patterns` (key substring), or `sensitive_value_patterns` (value regex) |
| `signals.db.query_groups[].example_sql`    | Always replaced with normalized `pattern`                                                                                                 |
| `signals.db.query_groups[].example_params` | Keys matching `sensitive_param_patterns` or values matching `sensitive_value_patterns`                                                    |

### Design Principles

**Defense-in-depth:** Both `OptimizationAdvisorTool` AND `OptimizationAdvisorCollectorFormatter` apply redaction. Even if one channel is bypassed, the other still redacts.

**Fail-closed regex:** Invalid regex patterns in `sensitive_value_patterns` cause the value to be redacted, not skipped. This is implemented by checking `preg_match` return value — `false` (error) is treated the same as `1` (match).

```php
$result = @preg_match("\x01" . $pattern . "\x01i", $value);
// false (invalid regex) OR 1 (match) => redact
// 0 (no match) => keep
if ($result === false || $result === 1) {
    return true; // redact
}
```

**`\x01` delimiter:** Uses SOH character as regex delimiter to avoid conflicts with regex content (which might contain `/`, `~`, `#`, etc.).

**Configurable with safe defaults:** Default patterns cover common sensitive terms (email, password, token, etc.) and can be extended via bundle configuration.

### Constructor

```php
public function __construct(
    private bool $enabled = true,
    private array $sensitiveParamPatterns = [],
    private array $sensitiveValuePatterns = [],
    private array $sensitiveQueryParams = [],
)
```

Wired in `src/AiMate/config.php` with all 4 parameters from bundle configuration.

## Consequences

- PII and secrets are redacted before reaching AI assistants
- Non-sensitive data (pagination, sorting, IDs) is preserved for debugging value
- Invalid patterns fail safely (redact rather than leak)
- Redaction can be disabled for isolated local development (`redact_sensitive_data: false`)
- New signal types added to MCP output must be reviewed for PII exposure

## Alternatives Rejected

| Alternative               | Why Rejected                                                                            |
|---------------------------|-----------------------------------------------------------------------------------------|
| No redaction              | Security risk — profiler data routinely contains tokens, emails, and other PII          |
| Non-configurable patterns | Too inflexible — different projects have different sensitive data patterns              |
| Redact all params         | Loses debugging value — non-sensitive params (page, sort, limit) provide useful context |
| Single-layer redaction    | Defense-in-depth is safer — applying in both Tool and Formatter provides redundancy     |

---

[&larr; ADR-0003](0003-messenger-middleware-compiler-pass.md) | [Next: Testing Guide &rarr;](../../testing/index.md)
