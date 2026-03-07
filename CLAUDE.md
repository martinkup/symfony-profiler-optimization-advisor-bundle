f# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Symfony Profiler bundle (`martinkup/symfony-profiler-optimization-advisor-bundle`) that analyzes per-request profiling signals across 7 categories and produces scored, actionable optimization opportunities with AI agent prompts. Registered only in `dev`/`test` environments.

**Namespace:** `MartinKup\OptimizationAdvisorBundle\`
**PHP:** >= 8.3 | **Symfony:** 7.2+ / 8.0+ | **PHPStan:** level max

## Commands

```bash
composer install              # Install dependencies
composer check                # Full QA pipeline: lint → fix → cs → stan → test
composer test                 # Run PHPUnit test suite
composer test:filter -- Name  # Run filtered tests (e.g. composer test:filter -- DatabaseAnalyzer)
composer test:unit            # Run only unit tests (@group unit)
composer cs                   # Check coding standards (PHP_CodeSniffer + Slevomat)
composer fix                  # Auto-fix coding standard violations
composer stan                 # PHPStan static analysis (level max)
composer test:coverage        # Text coverage (requires pcov)
composer test:coverage-html   # HTML coverage report in coverage/
```

## Architecture

### Data Flow

```
Request → Symfony Profiler Collectors (Doctrine, Cache, Twig, Events, ...)
          → OptimizationAdvisorDataCollector::lateCollect() (priority -100)
            → 7 Analyzers produce signals
              → AdvisorEngine::evaluate() scores opportunities (14 detection rules)
                → Profiler Panel (Twig template)
```

### Key Components

- **`OptimizationAdvisorBundle`** — `AbstractBundle` with `configure()` (config tree) + `loadExtension()` (parameters). No separate Extension class.
- **`DataCollector/OptimizationAdvisorDataCollector`** — Late data collector. Orchestrates all analyzers, feeds signals to AdvisorEngine, builds summary. Uses `#[AutoconfigureTag]` with priority `-100`.
- **`Engine/AdvisorEngine`** — Contains all 14 detection rules. Evaluates signals, scores opportunities (impact/effort/confidence/ROI), deduplicates by fingerprint, sorts by ROI descending, caps at `max_items`.
- **`Analyzer/*`** — Seven stateless analyzers: `DatabaseAnalyzer`, `CacheAnalyzer`, `TwigAnalyzer`, `EventAnalyzer`, `HttpClientAnalyzer`, `OtherSignalsAnalyzer` (Messenger), `PerformanceAnalyzer` (Stopwatch). Each produces a signal array.
- **`Enum/*`** — `DataOrigin` (app/infra/profiler), `OpportunityCode` (14 codes), `OpportunityCategory`, `Risk`.
- **`Sql/SqlNormalizer`** — SQL fingerprinting, normalization, table extraction.
- **`Messenger/TraceRegistry`** — In-memory store; user provides middleware to populate it.
- **`Twig/SqlFormatterExtension`** — Twig filters: `oi_prettify_sql`, `oi_format_sql`, `oi_replace_query_params`. Falls back to `SqlFormatterFallbackExtension` when `doctrine/sql-formatter` is not installed.

### Security Redaction (AiMate/MCP Output)

**CRITICAL**: All MCP/AI Mate output MUST pass through `SecurityRedactor` before being returned.
Both `OptimizationAdvisorCollectorFormatter` and `OptimizationAdvisorTool` apply redaction (defense-in-depth).
Pattern-based redaction (configurable, enabled by default):

- `origin.uri` path segments → values matching `sensitive_value_patterns` regex replaced with `***REDACTED***`
- `origin.uri` query params → params matching `sensitive_query_params` (exact match), or `sensitive_param_patterns` (key substring), or `sensitive_value_patterns` (value regex) replaced with `***REDACTED***`
- `signals.db.query_groups[].example_sql` → always replaced with normalized `pattern`
- `signals.db.query_groups[].example_params` → values matching `sensitive_param_patterns` (key)
  or `sensitive_value_patterns` (regex on value) replaced with `***REDACTED***`

**Fail-closed regex**: Invalid regex patterns in `sensitive_value_patterns` cause values to be redacted (not skipped).
Uses `\x01` delimiter to avoid conflicts with regex content.

Default patterns match: email, password, token, secret, auth, credential, phone, address, ssn, card, iban keys,
email-format values. Query params also match: session_id, _token, session, cookie.
Extend via bundle config.

Controlled by `redact_sensitive_data` config (default: `true`). Never disable in shared environments.
When adding new signal types to MCP output, review for PII exposure and extend `SecurityRedactor`.

### Service Wiring

`config/services.php` uses autowire+autoconfigure for the entire `src/` directory, with an explicit service definition for `OptimizationAdvisorDataCollector` that wires debug-only services (`twig.profile`, `data_collector.cache`) and optional services (`doctrine.debug_data_holder`, `data_collector.http_client`, `debug.stopwatch`) using `nullOnInvalid()` for graceful degradation when not available. The bundle's `MessageTracingMiddleware` is auto-registered for all configured Messenger buses via `prependExtension()`. When `symfony/messenger` is not installed, the middleware definition is conditionally removed (same pattern as `SqlFormatterExtension`).

### Origin Classification

Signals are classified as `app`, `infra`, or `profiler`. Only `app` signals generate opportunities. Classification is controlled by `app_namespace_prefix` config and various prefix lists.

## Coding Standards

- PSR-12 base + full Slevomat Coding Standard ruleset (with exclusions in `phpcs.xml.dist`)
- `declare(strict_types=1)` required in all PHP files
- Line length limit: 120 chars (comments and imports excluded)
- Test methods may use snake_case names
- Class member ordering enforced: uses → enum cases → constants → properties → constructor → public → protected → private methods
- No trailing commas on single-line calls/declarations
- Imports required (no FQCNs), unused imports forbidden
- Forbidden functions: `var_dump`, `print_r`, `die`, `exit`, `eval`, `phpinfo`, `is_null`

## PHPStan

- Never use `@phpstan-ignore` inline comments or `ignoreErrors` in `phpstan.neon.dist`. All code must be written with proper types so PHPStan level max passes naturally without any workarounds.

## Testing

Tests mirror `src/` structure under `tests/`. Each analyzer and engine has its own test class. Tests use PHPUnit 12 with `#[Group('unit')]` attribute. Test methods use the `test` prefix naming convention.