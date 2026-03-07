# Creating Symfony Data Collectors - Examples

## Overview

This directory contains detailed examples for creating custom Symfony Profiler Data Collectors. Each example demonstrates a specific pattern, from basic collectors to real-world integrations.

## Example Files

### Core Patterns

| File                                                             | Description                                                                 |
|------------------------------------------------------------------|-----------------------------------------------------------------------------|
| [`01-basic-data-collector.md`](01-basic-data-collector.md)       | Simplest `AbstractDataCollector` with `collect()`, `reset()`, typed getters |
| [`02-service-registration.md`](02-service-registration.md)       | Autoconfigure, YAML tag, `#[AutoconfigureTag]`, dev-only registration       |
| [`03-toolbar-template.md`](03-toolbar-template.md)               | Toolbar icon, hover panel, `toolbar_item.html.twig` include                 |
| [`04-profiler-panel-template.md`](04-profiler-panel-template.md) | Full panel with tables, `sf-tabs`, empty states, custom CSS                 |

### Advanced Patterns

| File                                                                   | Description                                                          |
|------------------------------------------------------------------------|----------------------------------------------------------------------|
| [`05-late-data-collector.md`](05-late-data-collector.md)               | `LateDataCollectorInterface` for post-response metrics               |
| [`06-event-subscriber-collector.md`](06-event-subscriber-collector.md) | Dual interface: `AbstractDataCollector` + `EventSubscriberInterface` |
| [`07-clone-var-serialization.md`](07-clone-var-serialization.md)       | `cloneVar()` usage, `Data` return type, `profiler_dump()` in Twig    |

### Real-World Patterns

| File                                                               | Description                                                          |
|--------------------------------------------------------------------|----------------------------------------------------------------------|
| [`08-domain-metrics-collector.md`](08-domain-metrics-collector.md) | Service metrics collector with dependency injection                  |
| [`09-conditional-collection.md`](09-conditional-collection.md)     | Exception-only, route-specific, threshold-based status colors        |
| [`10-testing-data-collectors.md`](10-testing-data-collectors.md)   | Unit tests with mock Request/Response, `#[CoversClass]`, AAA pattern |

## Quick Reference

### When to Use Each Pattern

| Pattern              | Use Case                            | Example                    |
|----------------------|-------------------------------------|----------------------------|
| **Basic Collector**  | Simple counters, static metrics     | Request attribute tracking |
| **Late Collector**   | Post-response data (memory, timing) | Peak memory usage          |
| **Event Subscriber** | Accumulate across request lifecycle | Kernel event tracking      |
| **Conditional**      | Selective data capture              | Exception-only collection  |

### Pattern Selection Decision Tree

```text
Need to collect data during the request lifecycle?
├── YES → Event Subscriber Collector (Pattern 3)
│   └── Data only available after response?
│       └── YES → Also implement LateDataCollectorInterface
└── NO → Basic Collector (Pattern 1)
    └── Data only available after response?
        └── YES → Late Data Collector (Pattern 2)
```

## Project File Locations

| Type              | Path                                           |
|-------------------|------------------------------------------------|
| Collector classes | `src/DataCollector/`                           |
| Twig templates    | `templates/data_collector/`                    |
| Service config    | `config/services/app_profiler.yaml` (when@dev) |

## Navigation

- **Main Skill**: [`SKILL.md`](../SKILL.md)
- **Related Skills**: None currently available in this project.
