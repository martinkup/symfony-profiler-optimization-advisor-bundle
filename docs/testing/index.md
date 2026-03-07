# Testing Guide

[Docs Hub](../README.md) / **Testing**

## Strategy

- **PHPUnit 12** with `#[Group('unit')]` and `#[CoversClass(...)]` attributes
- Tests mirror the `src/` directory structure under `tests/`
- Test methods use the `test` prefix naming convention
- Snake_case method names are allowed in tests only

## Test Coverage

| Test File                                                                 | Source Class                            |
|---------------------------------------------------------------------------|-----------------------------------------|
| `tests/Analyzer/DatabaseAnalyzerTest.php`                                 | `DatabaseAnalyzer`                      |
| `tests/Analyzer/CacheAnalyzerTest.php`                                    | `CacheAnalyzer`                         |
| `tests/Analyzer/TwigAnalyzerTest.php`                                     | `TwigAnalyzer`                          |
| `tests/Analyzer/EventAnalyzerTest.php`                                    | `EventAnalyzer`                         |
| `tests/Analyzer/HttpClientAnalyzerTest.php`                               | `HttpClientAnalyzer`                    |
| `tests/Analyzer/OtherSignalsAnalyzerTest.php`                             | `OtherSignalsAnalyzer`                  |
| `tests/Analyzer/PerformanceAnalyzerTest.php`                              | `PerformanceAnalyzer`                   |
| `tests/DataCollector/OptimizationAdvisorDataCollectorTest.php`            | `OptimizationAdvisorDataCollector`      |
| `tests/Engine/AdvisorEngineTest.php`                                      | `AdvisorEngine`                         |
| `tests/Enum/DataOriginTest.php`                                           | `DataOrigin`                            |
| `tests/Enum/OpportunityCategoryTest.php`                                  | `OpportunityCategory`                   |
| `tests/Enum/OpportunityCodeTest.php`                                      | `OpportunityCode`                       |
| `tests/Enum/RiskTest.php`                                                 | `Risk`                                  |
| `tests/Messenger/Middleware/MessageTracingMiddlewareTest.php`             | `MessageTracingMiddleware`              |
| `tests/Messenger/TraceRegistryTest.php`                                   | `TraceRegistry`                         |
| `tests/DependencyInjection/Compiler/MessageTracingMiddlewarePassTest.php` | `MessageTracingMiddlewarePass`          |
| `tests/OptimizationAdvisorBundleTest.php`                                 | `OptimizationAdvisorBundle`             |
| `tests/Sql/SqlNormalizerTest.php`                                         | `SqlNormalizer`                         |
| `tests/Sql/QueryParamSanitizerTest.php`                                   | `QueryParamSanitizer`                   |
| `tests/Twig/SqlFormatterExtensionTest.php`                                | `SqlFormatterExtension`                 |
| `tests/Twig/SqlFormatterFallbackExtensionTest.php`                        | `SqlFormatterFallbackExtension`         |
| `tests/AiMate/SecurityRedactorTest.php`                                   | `SecurityRedactor`                      |
| `tests/AiMate/OpportunityFilterTest.php`                                  | `OpportunityFilter`                     |
| `tests/AiMate/Capability/OptimizationAdvisorToolTest.php`                 | `OptimizationAdvisorTool`               |
| `tests/AiMate/Formatter/OptimizationAdvisorCollectorFormatterTest.php`    | `OptimizationAdvisorCollectorFormatter` |

## Running Tests

| Command                             | Description                                                      |
|-------------------------------------|------------------------------------------------------------------|
| `composer test`                     | Run full test suite                                              |
| `composer test:unit`                | Run only `#[Group('unit')]` tests                                |
| `composer test:filter -- ClassName` | Run tests matching filter                                        |
| `composer test:coverage`            | Text coverage output (requires pcov)                             |
| `composer test:coverage-html`       | HTML coverage report in `coverage/`                              |
| `composer check`                    | Full pipeline: lint &rarr; fix &rarr; cs &rarr; stan &rarr; test |

## CI Pipeline

The CI workflow (`.github/workflows/ci.yml`) runs 5 jobs:

| Job        | Tool             | Description                         |
|------------|------------------|-------------------------------------|
| `lint`     | `parallel-lint`  | PHP syntax check (PHP 8.4)          |
| `test`     | `phpunit`        | Test suite (4 matrix combinations)  |
| `phpcs`    | `phpcs`          | Coding standards (PHP 8.4)          |
| `phpstan`  | `phpstan`        | Static analysis level max (PHP 8.4) |
| `security` | `composer audit` | Dependency vulnerability scan       |

### Test Matrix

| PHP | Symfony | Composer Flags    |
|-----|---------|-------------------|
| 8.3 | 7.2.*   | *(default)*       |
| 8.3 | 7.2.*   | `--prefer-lowest` |
| 8.4 | 7.2.*   | *(default)*       |
| 8.4 | 8.0.*   | *(default)*       |

## Test Conventions

- All test classes use `#[Group('unit')]`
- All test classes use `#[CoversClass(TargetClass::class)]`
- Test methods use the `test` prefix (e.g., `testAnalyzeReturnsExpectedStructure`)
- Snake_case names are allowed for test methods (e.g., `test_empty_input_returns_defaults`)
- Tests use PHPUnit 12 attribute-based configuration
- No mocking frameworks beyond PHPUnit's built-in `createMock()`

---

[&larr; ADR-0004](../architecture/adr/0004-security-redaction-for-mcp-output.md) | [Next: Development Guide &rarr;](../development/index.md)
