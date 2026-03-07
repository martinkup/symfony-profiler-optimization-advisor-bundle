# Development Guide

[Docs Hub](../README.md) / **Development**

## Getting Started

```bash
git clone https://github.com/martinkup/symfony-profiler-optimization-advisor-bundle.git
cd symfony-profiler-optimization-advisor-bundle
composer install
composer check   # lint → fix → cs → stan → test
```

## Development Workflow

1. Make changes
2. `composer fix` — auto-fix coding standard violations
3. `composer cs` — verify coding standards pass
4. `composer stan` — verify static analysis passes
5. `composer test` — verify all tests pass

Or run everything at once:

```bash
composer check
```

## Quality Gates

| Command          | What It Checks                      |
|------------------|-------------------------------------|
| `composer lint`  | PHP syntax (parallel-lint)          |
| `composer fix`   | Auto-fix CS violations (phpcbf)     |
| `composer cs`    | Coding standards (phpcs + Slevomat) |
| `composer stan`  | Static analysis (PHPStan level max) |
| `composer test`  | Unit tests (PHPUnit 12)             |
| `composer check` | All of the above in sequence        |

## How to Add a New Detection Rule

1. **Add enum case** in `src/Enum/OpportunityCode.php`:
   ```php
   case MY_NEW_RULE = 'MY_NEW_RULE';
   ```

2. **Add label** in the `label()` match expression:
   ```php
   self::MY_NEW_RULE => 'Description of the new rule',
   ```

3. **Add detect method** in `src/Engine/AdvisorEngine.php`:
   ```php
   private function detectMyNewRule(array $signals): array
   {
       // Filter app-origin signals, apply threshold, call buildOpportunity()
   }
   ```

4. **Wire in evaluate()** — add the method call to the `array_merge` in `evaluate()`:
   ```php
   $opportunities = array_merge(
       // ... existing rules
       $this->detectMyNewRule($relevantSignals),
   );
   ```

5. **Add test** in `tests/Engine/AdvisorEngineTest.php`:
    - Test that the rule triggers when threshold is met
    - Test that the rule does not trigger below threshold
    - Test that only `app`-origin signals are evaluated
    - Verify scoring values (impact, effort, confidence, risk)

6. **Run quality gates**: `composer check`

## How to Add a New Analyzer

1. **Create class** `src/Analyzer/MyAnalyzer.php`:
   ```php
   final readonly class MyAnalyzer
   {
       public function analyze(/* typed input */): array
       {
           // Process data, classify origins, return structured signals
       }
   }
   ```

2. **Add dependency** in `OptimizationAdvisorDataCollector` constructor:
   ```php
   private readonly MyAnalyzer $myAnalyzer,
   ```

3. **Wire signals** in `lateCollect()`:
   ```php
   $mySignals = $this->myAnalyzer->analyze(/* data source */);
   ```

4. **Add accessor** method:
   ```php
   public function getMySignals(): array { /* ... */ }
   ```

5. **Add to signals array** in `lateCollect()`:
   ```php
   $this->data['signals']['my'] = $mySignals;
   ```

6. **Add tests**:
    - `tests/Analyzer/MyAnalyzerTest.php` — analyzer logic
    - Update `tests/DataCollector/OptimizationAdvisorDataCollectorTest.php` — integration

7. **Run quality gates**: `composer check`

## Section Contents

| Document                                | Description                                    |
|-----------------------------------------|------------------------------------------------|
| [Coding Standards](coding-standards.md) | PSR-12 + Slevomat rules, PHPStan configuration |

---

[&larr; Testing Guide](../testing/index.md) | [Next: Coding Standards &rarr;](coding-standards.md)
