# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-03-07

### Added

#### Core profiling

- Web Debug Toolbar panel with optimization score badge
- Profiler page with 9 tabs: Overview, Opportunities, Database, Cache, Twig, Performance, Events, Other, Export
- Late data collector (priority `-100`) orchestrating 7 analyzers via `LateDataCollectorInterface`

#### Signal analysis (7 analyzers)

- `DatabaseAnalyzer`: slow query groups, N+1 detection, duplicate queries, large resultsets, query fingerprinting via `SqlNormalizer`, table extraction, query kind classification (SELECT/INSERT/UPDATE/DELETE)
- `CacheAnalyzer`: hit rate analysis, pool classification into app/infra/profiler origins
- `TwigAnalyzer`: hot templates (by render time), duplicate renders
- `EventAnalyzer`: excessive listener calls, slow listeners
- `HttpClientAnalyzer`: slow endpoints, duplicate calls, endpoint path fingerprinting
- `OtherSignalsAnalyzer`: heavy synchronous Messenger handler detection
- `PerformanceAnalyzer`: Stopwatch timeline breakdown, top 3 slowest events, request duration

#### Advisor engine

- 14 detection rules across 6 categories (db, cache, twig, events, http, messenger) with ROI-based scoring (`impact × confidence / effort`)
- Quick win (`effort ≤ 2, confidence ≥ 4`) and high impact (`impact ≥ 4`) classification
- Fingerprint-based deduplication of opportunities
- AI agent prompt generation with problem description, evidence, recommended actions, and safety notes for each opportunity
- Optimization score (0–100) computed from detected opportunity penalties

#### Origin classification

- Signal classification into `app`, `infra`, or `profiler` origins — only `app` signals generate opportunities
- Configurable `app_namespace_prefix` for application code detection
- Per-category classification lists: `infra_db_tables`, `app_cache_pool_prefixes`, `profiler_cache_pool_prefixes`, `profiler_template_prefixes`, `profiler_event_namespace_prefixes`, `profiler_event_classes`

#### AI Mate / MCP integration

- MCP tool `optimization-advisor-opportunities` for AI assistants to fetch profiled opportunities with filtering by category and type (`quick_wins`, `high_impact`, `risky`)
- `OptimizationAdvisorCollectorFormatter` for AI Mate profiler integration
- `OpportunityFilter` for stripping internal fields from MCP output

#### Security

- Pattern-based PII redaction for MCP/AI output via `SecurityRedactor` (URI path segments, query params, SQL examples, query parameters)
- Configurable sensitive patterns: `sensitive_param_patterns` (key substring match), `sensitive_value_patterns` (regex), `sensitive_query_params` (exact name match)
- Fail-closed regex handling — invalid patterns cause redaction, not bypass
- Defense-in-depth: redaction applied in both `OptimizationAdvisorCollectorFormatter` and `OptimizationAdvisorTool`
- Production environment warning when bundle loaded outside dev/test

#### SQL utilities

- `SqlNormalizer`: SQL normalization, fingerprinting (`md5`), table extraction, IN-list collapsing
- `QueryParamSanitizer`: parameter sanitization with `cloneVar()` for profiler display
- Twig filters: `oi_prettify_sql`, `oi_format_sql`, `oi_replace_query_params` via `SqlFormatterExtension`
- `SqlFormatterFallbackExtension` used automatically when `doctrine/sql-formatter` is not installed

#### Messenger tracing

- Auto-registration of `MessageTracingMiddleware` for all configured Messenger buses via `MessageTracingMiddlewarePass` compiler pass
- `TraceRegistry`: in-memory store with dispatch stack tracking, record cap (200), and truncation detection
- Conditional removal of middleware definition when `symfony/messenger` is not installed

#### Configuration

- 15 configurable parameters: detection thresholds (`slow_query_ms`, `n_plus_one_count`, `slow_listener_ms`, `max_items`), origin classification lists, and security redaction patterns
- Graceful degradation for optional dependencies: Doctrine (`DebugDataHolder`), HttpClient (`HttpClientDataCollector`), Stopwatch, Messenger — services wired with `nullOnInvalid()`

[0.1.0]: https://github.com/martinkup/symfony-profiler-optimization-advisor-bundle/releases/tag/0.1.0
