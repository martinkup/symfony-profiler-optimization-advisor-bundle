# OptimizationAdvisorDataCollector

[Docs Hub](../README.md) / [Components](index.md) / **DataCollector**

## Responsibilities

```mermaid
mindmap
  root((DataCollector))
    Orchestration
      Coordinates 7 analyzers
      Feeds signals to AdvisorEngine
    Late Collection
      Priority -100
      Runs after all other collectors
    Signal Assembly
      DB signals from DebugDataHolder
      Cache signals from CacheDataCollector
      Twig signals from Profile tree
      Event signals from TraceableEventDispatcher
      HTTP signals from HttpClientDataCollector
      Messenger signals from TraceRegistry
      Performance signals from Stopwatch
    Summary
      Opportunity count
      Quick win count
      High impact count
      Optimization score
      Per-category timing totals
    VarDumper
      Param sanitization
      cloneVar for profiler display
```

## Public API

| Method                                   | Return Type | Description                                                     |
|------------------------------------------|-------------|-----------------------------------------------------------------|
| `collect(Request, Response, ?Throwable)` | `void`      | Stores route, controller, method, URI                           |
| `lateCollect()`                          | `void`      | Runs all analyzers, evaluates opportunities, builds summary     |
| `getName()`                              | `string`    | Returns `'optimization_advisor'`                                |
| `getTemplate()`                          | `string`    | Returns Twig template path                                      |
| `reset()`                                | `void`      | Resets data and TraceRegistry                                   |
| `getCorrelationId()`                     | `string`    | Returns correlation ID                                          |
| `getOrigin()`                            | `array`     | Returns `{route, controller, method, uri}`                      |
| `getSignals()`                           | `array`     | Returns all 7 signal categories                                 |
| `getOpportunities()`                     | `array`     | Returns all scored opportunities                                |
| `getSummary()`                           | `array`     | Returns summary metrics                                         |
| `getDbSignals()`                         | `array`     | Returns database signals                                        |
| `getCacheSignals()`                      | `array`     | Returns cache signals                                           |
| `getTwigSignals()`                       | `array`     | Returns Twig signals                                            |
| `getEventSignals()`                      | `array`     | Returns event signals                                           |
| `getHttpSignals()`                       | `array`     | Returns HTTP client signals                                     |
| `getOtherSignals()`                      | `array`     | Returns Messenger signals                                       |
| `getPerformanceSignals()`                | `array`     | Returns Stopwatch signals                                       |
| `getQuickWins()`                         | `array`     | Returns quick wins (deduplicated by code, highest ROI per code) |
| `getHighImpactOpportunities()`           | `array`     | Returns opportunities with `impact >= 4`                        |
| `getTopOpportunities(int $limit = 5)`    | `array`     | Returns top N opportunities by ROI                              |
| `getRiskyChanges()`                      | `array`     | Returns opportunities with `risk = high`                        |

## Dependencies

```mermaid
classDiagram
    class OptimizationAdvisorDataCollector {
        -DatabaseAnalyzer databaseAnalyzer
        -QueryParamSanitizer queryParamSanitizer
        -CacheAnalyzer cacheAnalyzer
        -TwigAnalyzer twigAnalyzer
        -EventAnalyzer eventAnalyzer
        -HttpClientAnalyzer httpClientAnalyzer
        -OtherSignalsAnalyzer otherSignalsAnalyzer
        -PerformanceAnalyzer performanceAnalyzer
        -AdvisorEngine advisorEngine
        -Profile twigProfile
        -CacheDataCollector cacheDataCollector
        -EventDispatcherInterface eventDispatcher
        -TraceRegistry traceRegistry
        -DebugDataHolder? debugDataHolder
        -HttpClientDataCollector? httpClientDataCollector
        -Stopwatch? stopwatch
    }

    OptimizationAdvisorDataCollector --> DatabaseAnalyzer
    OptimizationAdvisorDataCollector --> CacheAnalyzer
    OptimizationAdvisorDataCollector --> TwigAnalyzer
    OptimizationAdvisorDataCollector --> EventAnalyzer
    OptimizationAdvisorDataCollector --> HttpClientAnalyzer
    OptimizationAdvisorDataCollector --> OtherSignalsAnalyzer
    OptimizationAdvisorDataCollector --> PerformanceAnalyzer
    OptimizationAdvisorDataCollector --> AdvisorEngine
    OptimizationAdvisorDataCollector --> TraceRegistry
```

## lateCollect() Flow

```mermaid
sequenceDiagram
    participant DC as DataCollector
    participant DBA as DatabaseAnalyzer
    participant QPS as QueryParamSanitizer
    participant CA as CacheAnalyzer
    participant TA as TwigAnalyzer
    participant EA as EventAnalyzer
    participant HA as HttpClientAnalyzer
    participant OA as OtherSignalsAnalyzer
    participant PA as PerformanceAnalyzer
    participant E as AdvisorEngine
    DC ->> DBA: analyze(connectionData)
    DBA -->> DC: dbSignals
    DC ->> QPS: sanitize params per group
    DC ->> CA: analyze(poolStats)
    CA -->> DC: cacheSignals
    DC ->> TA: analyze(twigProfile)
    TA -->> DC: twigSignals
    DC ->> EA: analyze(calledListeners, notCalled)
    EA -->> DC: eventSignals
    DC ->> HA: analyze(clientsData)
    HA -->> DC: httpSignals
    DC ->> OA: analyze(traceRecords)
    OA -->> DC: otherSignals
    DC ->> E: evaluate(db, cache, twig, events, http, messenger)
    E -->> DC: opportunities
    DC ->> PA: analyze(stopwatchEvents, requestDuration)
    PA -->> DC: performanceSignals
    Note over DC: Store signals, opportunities, summary in $data
```

## Key Behaviors

### Late Collection

Uses `#[AutoconfigureTag('data_collector', ['priority' => -100])]` and implements `LateDataCollectorInterface` to ensure all other Symfony profiler collectors have finished before analysis begins.

### Parameter Sanitization

DB query params are processed through `QueryParamSanitizer` and then `cloneVar()` for safe VarDumper display in the profiler panel. Complex types (objects, resources) are converted to display-safe placeholders.

### Optional Service Degradation

Three services use `nullOnInvalid()` wiring and degrade gracefully:

| Service                      | When Absent               |
|------------------------------|---------------------------|
| `doctrine.debug_data_holder` | Empty DB section          |
| `data_collector.http_client` | Empty HTTP section        |
| `debug.stopwatch`            | Empty performance section |

---

[&larr; Components](index.md) | [Next: AdvisorEngine &rarr;](advisor-engine.md)
