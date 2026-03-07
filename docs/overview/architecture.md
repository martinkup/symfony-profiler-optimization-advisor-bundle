# Architecture

[Docs Hub](../README.md) / [Overview](index.md) / **Architecture**

## High-Level Data Flow

```mermaid
graph TB
    REQ[HTTP Request] --> SPC[Symfony Profiler Collectors<br/>Doctrine, Cache, Twig, Events, HttpClient, Stopwatch]
    SPC --> DC["OptimizationAdvisorDataCollector::lateCollect()<br/>priority -100"]
    DC --> DBA[DatabaseAnalyzer]
    DC --> CAA[CacheAnalyzer]
    DC --> TWA[TwigAnalyzer]
    DC --> EVA[EventAnalyzer]
    DC --> HCA[HttpClientAnalyzer]
    DC --> OSA[OtherSignalsAnalyzer]
    DC --> PFA[PerformanceAnalyzer]
    DBA --> ENG["AdvisorEngine::evaluate()<br/>14 detection rules"]
    CAA --> ENG
    TWA --> ENG
    EVA --> ENG
    HCA --> ENG
    OSA --> ENG
    ENG --> DEDUP[Deduplicate by fingerprint]
    DEDUP --> SORT[Sort by ROI descending]
    SORT --> CAP["Cap at max_items (default 200)"]
    CAP --> PANEL[Profiler Panel]
    PFA --> DC
```

## Request Lifecycle

```mermaid
sequenceDiagram
    participant R as Request
    participant P as Symfony Profiler
    participant DC as DataCollector
    participant A as Analyzers (7)
    participant E as AdvisorEngine
    R ->> P: Request handled
    P ->> DC: collect(request, response)
    Note over DC: Stores route, controller, method, URI
    P ->> P: Other collectors finish
    P ->> DC: lateCollect()
    DC ->> A: analyze(profiler data)
    A -->> DC: signals (per category)
    DC ->> E: evaluate(db, cache, twig, events, http, messenger)
    E -->> DC: scored opportunities
    Note over DC: Build summary, store in $data
    DC -->> P: Ready for panel rendering
```

## Origin Classification

All signals are classified into three origins. Only **app** signals generate opportunities.

```mermaid
flowchart TD
    SIG[Signal] --> TYPE{Signal type?}
    TYPE -->|DB query| DB_CHECK{Tables match<br/>infra prefixes?}
    DB_CHECK -->|pg_, information_schema| INFRA[infra]
    DB_CHECK -->|infra_db_tables config| INFRA
    DB_CHECK -->|SQL contains<br/>CURRENT_DATABASE etc .| INFRA
    DB_CHECK -->|No match| APP[app]
    TYPE -->|Cache pool| CACHE_CHECK{Pool name prefix?}
    CACHE_CHECK -->|profiler_cache_pool_prefixes| PROF[profiler]
    CACHE_CHECK -->|app_cache_pool_prefixes| APP
    CACHE_CHECK -->|Neither| INFRA
    TYPE -->|Twig template| TWIG_CHECK{Template prefix?}
    TWIG_CHECK -->|profiler_template_prefixes| PROF
    TWIG_CHECK -->|No match| APP
    TYPE -->|Event listener| EVT_CHECK{Listener class?}
    EVT_CHECK -->|profiler_event_namespace_prefixes| PROF
    EVT_CHECK -->|profiler_event_classes| PROF
    EVT_CHECK -->|app_namespace_prefix| APP
    EVT_CHECK -->|Other| INFRA
    TYPE -->|Messenger handler| MSG_CHECK{Handler namespace?}
    MSG_CHECK -->|app_namespace_prefix| APP
    MSG_CHECK -->|Other| INFRA
    TYPE -->|HTTP call| HTTP_APP[app]
```

## Scoring Model

Each opportunity is scored on three dimensions (1-5 scale):

| Dimension      | Description                                          |
|----------------|------------------------------------------------------|
| **Impact**     | Expected performance improvement magnitude           |
| **Effort**     | Implementation work required                         |
| **Confidence** | Detection certainty (higher = fewer false positives) |

### Derived Metrics

| Metric                 | Formula                                | Purpose                 |
|------------------------|----------------------------------------|-------------------------|
| **ROI**                | `impact * confidence / effort`         | Sorting (highest first) |
| **Quick Win**          | `effort <= 2 AND confidence >= 4`      | Low-hanging fruit flag  |
| **High Impact**        | `impact >= 4`                          | High-value change flag  |
| **Fingerprint**        | `md5(code + "\|" + evidence)`          | Deduplication key       |
| **Optimization Score** | `100 - SUM(impact * confidence * 0.5)` | Request health (0-100)  |

## Signal Flow Summary

| Analyzer             | Data Source                | Output Key    | Detection Rules                                                                                                                            |
|----------------------|----------------------------|---------------|--------------------------------------------------------------------------------------------------------------------------------------------|
| DatabaseAnalyzer     | `DebugDataHolder`          | `db`          | PG_SLOW_QUERY_GROUP, PG_N_PLUS_ONE_SUSPECTED, PG_DUPLICATE_QUERY, PG_LARGE_RESULTSET, CACHE_CANDIDATE_DB_RESULTS, DOCTRINE_2LC_OPPORTUNITY |
| CacheAnalyzer        | `CacheDataCollector`       | `cache`       | CACHE_LOW_HITRATE_POOL                                                                                                                     |
| TwigAnalyzer         | `Twig\Profiler\Profile`    | `twig`        | TWIG_HOT_TEMPLATE, TWIG_DUP_RENDER                                                                                                         |
| EventAnalyzer        | `TraceableEventDispatcher` | `events`      | EVENTS_TOO_MANY_LISTENERS, EVENTS_SLOW_LISTENER                                                                                            |
| HttpClientAnalyzer   | `HttpClientDataCollector`  | `http`        | HTTP_SLOW_ENDPOINT, HTTP_DUP_CALL                                                                                                          |
| OtherSignalsAnalyzer | `TraceRegistry`            | `other`       | MESSENGER_SYNC_HEAVY                                                                                                                       |
| PerformanceAnalyzer  | `Stopwatch`                | `performance` | *(informational only)*                                                                                                                     |

---

[&larr; Overview](index.md) | [Next: Tech Stack &rarr;](tech-stack.md)
