---
name: implementing-symfony-profiler-data-collectors
description: Implements and modifies Symfony Profiler Data Collectors with toolbar panels, Twig templates, and service registration. Use when creating, editing, or debugging data collectors for profiling, performance monitoring, or custom metrics visualization in the Web Debug Toolbar. Also use this skill when working with DataCollectorInterface, LateDataCollectorInterface, AbstractDataCollector, cloneVar(), profiler_dump(), or the data_collector service tag — even if the user doesn't explicitly mention "data collector".
---

# Creating Symfony Data Collectors

## Purpose

Creates custom Symfony Profiler Data Collectors that integrate with the Web Debug Toolbar and Profiler panel. Data collectors capture request-specific metrics, timing data, and debugging information displayed during development.

## When to Use

- **Performance Profiling**: Track execution time of specific subsystems (services, subsystems)
- **Metrics Visualization**: Display request-scoped counters, timings, or statistics in toolbar
- **Debugging Tools**: Capture and inspect complex objects during request lifecycle
- **Event Tracking**: Monitor kernel events, domain events, or message dispatches
- **Integration Monitoring**: Track external service calls, cache hits/misses, query counts

## When NOT to Use

- **Production Monitoring**: Use Prometheus/Grafana, not data collectors
- **Logging**: Use Monolog with appropriate log levels
- **Business Analytics**: Use domain events and reporting infrastructure
- **Error Tracking**: Use Sentry or structured exception handling

## Core Concepts

### Class Hierarchy

```text
DataCollectorInterface (symfony/http-kernel) extends ResetInterface
    - public collect(Request, Response, ?Throwable): void
    - public getName(): string
    |
    v
DataCollector (abstract, symfony/http-kernel)
    - protected array|Data $data = []
    - protected cloneVar(mixed $var): Data
    - protected getCasters(): callable[]
    - public reset(): void             → sets $this->data = []
    - public __serialize(): array
    - public __unserialize(array $data): void
    |
    v
AbstractDataCollector (symfony/framework-bundle)
    extends DataCollector
    implements TemplateAwareDataCollectorInterface
    - public getName(): string         → returns static::class
    - public static getTemplate(): ?string → returns null
```

### Key Interfaces

| Interface                             | Purpose                  | Key Method                                     |
|---------------------------------------|--------------------------|------------------------------------------------|
| `DataCollectorInterface`              | Base contract            | `collect(Request, Response, ?Throwable): void` |
| `TemplateAwareDataCollectorInterface` | Links to Twig template   | `static getTemplate(): ?string`                |
| `LateDataCollectorInterface`          | Post-response collection | `lateCollect(): void`                          |

### Data Flow

```text
1. Request arrives
2. collect(Request, Response, ?Throwable) called after response
3. Data stored in $this->data (must be serializable)
4. Profile serialized to storage
5. Twig template renders data in toolbar/panel
```

**CRITICAL**: The `$data` property is serialized. Store only scalars, arrays, and `Data` objects (from `cloneVar()`). Non-serializable objects (entities, services) cause profiler corruption.

### cloneVar() for Complex Objects

Use `cloneVar()` to convert non-serializable objects into `Data` instances:

```php
$this->data['request_attributes'] = $this->cloneVar($request->attributes->all());
```

In Twig templates, render `Data` objects with `profiler_dump()`:

```twig
{{ profiler_dump(collector.requestAttributes) }}
```

## Implementation Patterns

### Pattern 1: Basic Collector (Recommended)

Extend `AbstractDataCollector` for the simplest approach:

```php
<?php

declare(strict_types=1);

namespace App\DataCollector;

use Override;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ExampleDataCollector extends AbstractDataCollector
{
    #[Override]
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = [
            'metric_count' => 0,
            'items' => [],
        ];
    }

    #[Override]
    public function getName(): string
    {
        return 'app.example';
    }

    #[Override]
    public static function getTemplate(): ?string
    {
        return 'data_collector/example.html.twig';
    }

    public function getMetricCount(): int
    {
        return $this->data['metric_count'];
    }

    /** @return array<string, mixed> */
    public function getItems(): array
    {
        return $this->data['items'];
    }
}
```

See [`examples/01-basic-data-collector.md`](examples/01-basic-data-collector.md) for complete implementation.

### Pattern 2: Late Data Collector

Implement `LateDataCollectorInterface` when data is only available after the response:

```php
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

final class MemoryDataCollector extends AbstractDataCollector implements LateDataCollectorInterface
{
    #[Override]
    public function lateCollect(): void
    {
        $this->data['peak_memory'] = memory_get_peak_usage(true);
    }
}
```

See [`examples/05-late-data-collector.md`](examples/05-late-data-collector.md) for complete implementation.

### Pattern 3: Event Subscriber Collector

Combine data collection with event listening to accumulate metrics across the request:

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class EventTrackingCollector extends AbstractDataCollector implements EventSubscriberInterface
{
    /** @var array<int, array{name: string, time: float}> */
    private array $collectedEvents = [];

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onController'];
    }

    public function onController(ControllerEvent $event): void
    {
        $this->collectedEvents[] = ['name' => 'controller', 'time' => microtime(true)];
    }

    #[Override]
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data['events'] = $this->collectedEvents;
    }
}
```

See [`examples/06-event-subscriber-collector.md`](examples/06-event-subscriber-collector.md) for complete implementation.

## Service Registration

### Autoconfigure (Preferred)

Classes extending `AbstractDataCollector` are auto-tagged via Symfony autoconfigure. No manual registration needed if services are auto-discovered.

### YAML Tag (Manual)

```yaml
# config/services/app_profiler.yaml
when@dev:
    services:
        App\DataCollector\ExampleDataCollector:
            tags:
                - name: data_collector
                  template: 'data_collector/example.html.twig'
                  id: 'app.example'
```

### #[AutoconfigureTag] Attribute

```php
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('data_collector', ['template' => 'data_collector/example.html.twig', 'id' => 'app.example'])]
final class ExampleDataCollector extends AbstractDataCollector
```

### Dev-Only Registration

**MUST** register collectors only in `dev` environment:

- **YAML**: Wrap in `when@dev:` block
- **PHP Attribute**: Use `#[When('dev')]` on the class
- **Autoconfigure**: Ensure service file is loaded only in dev

See [`examples/02-service-registration.md`](examples/02-service-registration.md) for all registration methods.

## Twig Template Structure

Templates define four blocks for the profiler UI:

### toolbar Block (Web Debug Toolbar)

```twig
{% extends '@WebProfiler/Profiler/layout.html.twig' %}

{% block toolbar %}
    {% set icon %}
        {# SVG icon + summary value #}
        {{ include('@WebProfiler/Icon/event.svg') }}
        <span class="sf-toolbar-value">{{ collector.metricCount }}</span>
    {% endset %}

    {% set text %}
        {# Hover panel content #}
        <div class="sf-toolbar-info-piece">
            <b>Metric Count</b>
            <span>{{ collector.metricCount }}</span>
        </div>
    {% endset %}

    {{ include('@WebProfiler/Profiler/toolbar_item.html.twig', { link: profiler_url }) }}
{% endblock %}
```

### menu Block (Profiler Left Sidebar)

```twig
{% block menu %}
    <span class="label">
        <span class="icon">{{ include('@WebProfiler/Icon/event.svg') }}</span>
        <strong>Example</strong>
    </span>
{% endblock %}
```

### panel Block (Profiler Main Content)

```twig
{% block panel %}
    <h2>Example Collector</h2>

    {% if collector.metricCount == 0 %}
        <div class="empty">
            <p>No data collected.</p>
        </div>
    {% else %}
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                {% for item in collector.items %}
                    <tr>
                        <td>{{ item.name }}</td>
                        <td>{{ item.value }}</td>
                    </tr>
                {% endfor %}
            </tbody>
        </table>
    {% endif %}
{% endblock %}
```

### head Block (Optional CSS/JS)

```twig
{% block head %}
    {{ parent() }}
    <style>
        .metrics-highlight { color: var(--color-warning); font-weight: bold; }
    </style>
{% endblock %}
```

See [`examples/03-toolbar-template.md`](examples/03-toolbar-template.md) and [`examples/04-profiler-panel-template.md`](examples/04-profiler-panel-template.md) for complete templates.

## Best Practices

1. **Lightweight `collect()`**: Minimize work in collect — heavy processing goes in `lateCollect()`
2. **Typed Getters**: Expose data through typed accessor methods, not raw `$data` access
3. **Serializable Data Only**: Store scalars, arrays, `Data` (via `cloneVar()`) — never services or entities
4. **Dev-Only Registration**: Data collectors **MUST** only be registered in `dev` environment
5. **Passive Observer**: Collectors **MUST NOT** modify request, response, or application state
6. **Status Colors**: Use toolbar status classes (`sf-toolbar-status-yellow`, `sf-toolbar-status-red`) for thresholds
7. **Empty States**: Always handle zero-data case in templates
8. **Reset Implementation**: Override `reset()` to clear both `$data` and any private accumulator properties

## Anti-Patterns (FORBIDDEN)

### Non-Serializable Data in $data

```php
// FORBIDDEN - Entity stored directly
$this->data['user'] = $request->getUser();

// CORRECT - Use cloneVar() or extract scalars
$this->data['user'] = $this->cloneVar($request->getUser());
$this->data['user_email'] = $request->getUser()?->getUserIdentifier();
```

### Side Effects in collect()

```php
// FORBIDDEN - Modifying state during collection
public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
{
    $this->logger->info('Collecting data');  // Side effect
    $this->cache->delete('key');             // Side effect
}

// CORRECT - Only read and store
public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
{
    $this->data['status_code'] = $response->getStatusCode();
}
```

### Wrong Layer Placement

```php
// FORBIDDEN - Inside domain layer
namespace App\Domain\Profiler;

// CORRECT - Inside infrastructure/DataCollector namespace
namespace App\DataCollector;
```

### readonly Class

```php
// FORBIDDEN - $data must be mutable
final readonly class ExampleDataCollector extends AbstractDataCollector

// CORRECT - final but not readonly
final class ExampleDataCollector extends AbstractDataCollector
```

### Production Registration

```yaml
# FORBIDDEN - Registered in all environments
services:
    App\DataCollector\ExampleDataCollector:
        tags: ['data_collector']

# CORRECT - Dev only
when@dev:
    services:
        App\DataCollector\ExampleDataCollector:
            tags: ['data_collector']
```

## Validation Checklist

### Class Structure

- [ ] Extends `AbstractDataCollector`
- [ ] `final` class (NOT `readonly`)
- [ ] `declare(strict_types=1);`
- [ ] `#[Override]` on all overridden methods
- [ ] `getName()` returns short `'app.{purpose}'` string
- [ ] `getTemplate()` is `static` and returns template path

### Data Handling

- [ ] `collect()` stores only serializable data
- [ ] `cloneVar()` used for complex objects
- [ ] Typed getter methods for all data fields
- [ ] `reset()` clears `$data` and private accumulators

### Service Registration

- [ ] Registered in `dev` environment only
- [ ] Template path matches `getTemplate()` return value
- [ ] Collector ID matches `getName()` return value

### Twig Template

- [ ] Extends `@WebProfiler/Profiler/layout.html.twig`
- [ ] `toolbar` block with icon and hover panel
- [ ] `menu` block with label and icon
- [ ] `panel` block with data tables
- [ ] Empty state handled (zero data case)
- [ ] `profiler_dump()` used for `Data` objects

## Examples

- **[Examples README](examples/README.md)** - Navigation guide and index
- [`01-basic-data-collector.md`](examples/01-basic-data-collector.md) - Simplest `AbstractDataCollector`
- [`02-service-registration.md`](examples/02-service-registration.md) - Autoconfigure, YAML, `#[AutoconfigureTag]`
- [`03-toolbar-template.md`](examples/03-toolbar-template.md) - Toolbar icon and hover panel
- [`04-profiler-panel-template.md`](examples/04-profiler-panel-template.md) - Full panel with tables, tabs, CSS
- [`05-late-data-collector.md`](examples/05-late-data-collector.md) - `LateDataCollectorInterface`
- [`06-event-subscriber-collector.md`](examples/06-event-subscriber-collector.md) - Dual interface pattern
- [`07-clone-var-serialization.md`](examples/07-clone-var-serialization.md) - `cloneVar()` for complex objects
- [`08-domain-metrics-collector.md`](examples/08-domain-metrics-collector.md) - Service metrics example
- [`09-conditional-collection.md`](examples/09-conditional-collection.md) - Exception-only, route-specific
- [`10-testing-data-collectors.md`](examples/10-testing-data-collectors.md) - Unit and integration testing

## Reference Implementations

### Symfony Built-in Collectors

- [`vendor/symfony/http-kernel/DataCollector/DataCollector.php`](../../../vendor/symfony/http-kernel/DataCollector/DataCollector.php) - Base abstract class
- [`vendor/symfony/framework-bundle/DataCollector/AbstractDataCollector.php`](../../../vendor/symfony/framework-bundle/DataCollector/AbstractDataCollector.php) - Framework abstract class
- [`vendor/symfony/http-kernel/DataCollector/DataCollectorInterface.php`](../../../vendor/symfony/http-kernel/DataCollector/DataCollectorInterface.php) - Base interface
- [`vendor/symfony/http-kernel/DataCollector/LateDataCollectorInterface.php`](../../../vendor/symfony/http-kernel/DataCollector/LateDataCollectorInterface.php) - Late collection interface
- [`vendor/symfony/framework-bundle/DataCollector/TemplateAwareDataCollectorInterface.php`](../../../vendor/symfony/framework-bundle/DataCollector/TemplateAwareDataCollectorInterface.php) - Template interface

## Related Skills

No additional skills are currently available in this project.

## External References

- [Symfony Data Collectors Documentation](https://symfony.com/doc/current/profiler/data_collector.html)
- [Symfony Profiler Documentation](https://symfony.com/doc/current/profiler.html)
- [Web Debug Toolbar](https://symfony.com/doc/current/web_debug_toolbar.html)

---

**Key Principle**: Data Collectors are dev-only, passive observers in the infrastructure layer. They capture request-scoped metrics into serializable `$data`, expose them via typed getters, and render them through Twig templates in the Web Debug Toolbar and Profiler panel. They MUST NOT have side effects or be registered in production.
