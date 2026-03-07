# Extension Points

[Docs Hub](../README.md) / [Integration](index.md) / **Extending**

## Configuration-Based Extension

The primary extension mechanism is through the 16 configuration parameters:

| Extension                    | Configuration                                                                    |
|------------------------------|----------------------------------------------------------------------------------|
| Adjust detection sensitivity | `thresholds.*` (4 params)                                                        |
| Add infra tables             | `infra_db_tables`                                                                |
| Add app/profiler cache pools | `app_cache_pool_prefixes`, `profiler_cache_pool_prefixes`                        |
| Exclude profiler templates   | `profiler_template_prefixes`                                                     |
| Exclude profiler listeners   | `profiler_event_namespace_prefixes`, `profiler_event_classes`                    |
| Change app namespace         | `app_namespace_prefix`                                                           |
| Add security patterns        | `sensitive_param_patterns`, `sensitive_value_patterns`, `sensitive_query_params` |

See [Configuration Reference](../configuration/index.md) for full details.

## Consuming Output Programmatically

The `OptimizationAdvisorDataCollector` exposes a rich public API for accessing analysis results:

### All Opportunities

```php
$collector = $profile->getCollector('optimization_advisor');
$opportunities = $collector->getOpportunities(); // ROI-sorted, deduplicated
```

### Filtered Views

```php
$quickWins = $collector->getQuickWins();           // effort <= 2, confidence >= 4
$highImpact = $collector->getHighImpactOpportunities(); // impact >= 4
$topFive = $collector->getTopOpportunities(5);     // top N by ROI
$risky = $collector->getRiskyChanges();             // risk = high
```

### Summary Metrics

```php
$summary = $collector->getSummary();
// [
//     'opportunity_count'  => int,
//     'quick_win_count'    => int,
//     'high_impact_count'  => int,
//     'optimization_score' => int,   // 0-100
//     'total_db_ms'        => float,
//     'total_twig_ms'      => float,
//     'total_http_ms'      => float,
//     'total_messenger_ms' => float,
// ]
```

### Per-Category Signals

```php
$dbSignals    = $collector->getDbSignals();
$cacheSignals = $collector->getCacheSignals();
$twigSignals  = $collector->getTwigSignals();
$eventSignals = $collector->getEventSignals();
$httpSignals  = $collector->getHttpSignals();
$otherSignals = $collector->getOtherSignals();
$perfSignals  = $collector->getPerformanceSignals();
```

## AI Mate / MCP Integration

When `symfony/ai-symfony-mate-extension` is installed, two channels are available:

### MCP Tool

```
optimization-advisor-opportunities(
    token?: string,       # Profiler token (null = latest request)
    category?: string,    # Filter: db, cache, twig, events, http, messenger
    type?: string,        # Filter: quick_wins, high_impact, risky
    limit?: int = 20      # Max opportunities returned
)
```

Returns opportunities with `summary` and per-opportunity `evidence_refs`, `safety_notes`, `expected_gain`, `why`, `fingerprint`, `is_quick_win`, `is_high_impact`.

> Note: `ai_prompt` and `recommended_actions` fields are stripped by `OpportunityFilter::forMcpOutput()` to keep the MCP response concise.

### Profiler Resource URI

```
symfony-profiler://profile/{token}/optimization_advisor
```

Returns full collector output: `origin`, `opportunities`, `summary`, and `signals` (with Data objects resolved via `getValue(true)`).

### Security Redaction

Both channels apply `SecurityRedactor` (defense-in-depth). See [ADR-0004](../architecture/adr/0004-security-redaction-for-mcp-output.md) for design rationale.

## Limitations

The bundle uses `final readonly` classes throughout. This means:

- **No class extension** — Analyzers, Engine, DataCollector, and all other classes are `final`
- **No pluggable detection rules** — The 14 rules are hardcoded in `AdvisorEngine`
- **No custom analyzers** — The 7 analyzers are wired directly in the DataCollector
- **No event hooks** — There are no events dispatched during analysis

These constraints are intentional — the bundle is designed as a closed, self-contained profiling tool with configuration-only customization.

---

[&larr; Optional Dependencies](optional-deps.md) | [Next: ADR Index &rarr;](../architecture/adr/index.md)
