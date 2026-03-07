# Service Metrics Collector

Demonstrates a data collector that tracks service call metrics through dependency injection and public
instrumentation methods. This collector combines `AbstractDataCollector` with `EventSubscriberInterface` to
accumulate metrics across the request lifecycle, then presents call counts, durations, and per-service
breakdowns in the Web Debug Toolbar and Profiler panel.

## When to Use

- Monitoring service call frequency and duration per request
- Tracking external API call performance (HTTP clients, third-party services)
- Identifying slow service operations during development
- Debugging which service calls are made during a request
- Comparing expected vs actual call counts

## Implementation

### PHP Class

```php
<?php

declare(strict_types=1);

namespace App\DataCollector;

use Override;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;

final class ServiceMetricsDataCollector extends AbstractDataCollector implements EventSubscriberInterface
{
    /** @var array<int, array{name: string, duration: float, timestamp: float, service: string}> */
    private array $serviceCalls = [];

    /** @var array<int, array{name: string, duration: float, timestamp: float}> */
    private array $apiCalls = [];

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -2048],
        ];
    }

    /**
     * Ensures collect() is triggered even when no explicit response-time
     * data is needed from the event. The actual data comes from the
     * record*() methods called by instrumented service adapters.
     */
    public function onKernelResponse(): void
    {
        // No-op: data is accumulated via record*() calls during the request.
        // This listener ensures the subscriber is registered and the collector
        // participates in the event lifecycle.
    }

    public function recordServiceCall(string $name, float $duration, string $service): void
    {
        $this->serviceCalls[] = [
            'name' => $name,
            'duration' => $duration,
            'timestamp' => microtime(true),
            'service' => $service,
        ];
    }

    public function recordApiCall(string $name, float $duration): void
    {
        $this->apiCalls[] = [
            'name' => $name,
            'duration' => $duration,
            'timestamp' => microtime(true),
        ];
    }

    #[Override]
    public function collect(
        Request $request,
        Response $response,
        ?\Throwable $exception = null,
    ): void {
        $serviceDuration = $this->sumDuration($this->serviceCalls);
        $apiDuration = $this->sumDuration($this->apiCalls);

        $this->data = [
            'service_calls' => $this->serviceCalls,
            'api_calls' => $this->apiCalls,
            'service_call_count' => \count($this->serviceCalls),
            'api_call_count' => \count($this->apiCalls),
            'service_duration' => $serviceDuration,
            'api_duration' => $apiDuration,
            'total_duration' => $serviceDuration + $apiDuration,
        ];
    }

    #[Override]
    public function reset(): void
    {
        $this->data = [];
        $this->serviceCalls = [];
        $this->apiCalls = [];
    }

    #[Override]
    public function getName(): string
    {
        return 'app.service_metrics';
    }

    #[Override]
    public static function getTemplate(): ?string
    {
        return 'data_collector/service_metrics.html.twig';
    }

    // --- Typed Getters: Counts ---

    public function getServiceCallCount(): int
    {
        return $this->data['service_call_count'] ?? 0;
    }

    public function getApiCallCount(): int
    {
        return $this->data['api_call_count'] ?? 0;
    }

    // --- Typed Getters: Durations ---

    public function getServiceDuration(): float
    {
        return $this->data['service_duration'] ?? 0.0;
    }

    public function getApiDuration(): float
    {
        return $this->data['api_duration'] ?? 0.0;
    }

    public function getTotalDuration(): float
    {
        return $this->data['total_duration'] ?? 0.0;
    }

    // --- Typed Getters: Lists ---

    /**
     * @return array<int, array{name: string, duration: float, timestamp: float, service: string}>
     */
    public function getServiceCalls(): array
    {
        return $this->data['service_calls'] ?? [];
    }

    /** @return array<int, array{name: string, duration: float, timestamp: float}> */
    public function getApiCalls(): array
    {
        return $this->data['api_calls'] ?? [];
    }

    /**
     * @param array<int, array{duration: float, ...}> $items
     */
    private function sumDuration(array $items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $total += $item['duration'];
        }

        return $total;
    }
}
```

### Twig Template

```twig
{# templates/data_collector/service_metrics.html.twig #}
{% extends '@WebProfiler/Profiler/layout.html.twig' %}

{% block toolbar %}
    {% set totalCount = collector.serviceCallCount + collector.apiCallCount %}
    {% set statusColor = collector.totalDuration > 0.5 ? 'yellow' : '' %}

    {% set icon %}
        {{ include('@WebProfiler/Icon/event.svg') }}
        <span class="sf-toolbar-value">{{ totalCount }}</span>
    {% endset %}

    {% set text %}
        <div class="sf-toolbar-info-piece">
            <b>Service Calls</b>
            <span class="sf-toolbar-status">{{ collector.serviceCallCount }}</span>
        </div>
        <div class="sf-toolbar-info-piece">
            <b>API Calls</b>
            <span class="sf-toolbar-status">{{ collector.apiCallCount }}</span>
        </div>
        <div class="sf-toolbar-info-piece">
            <b>Total Duration</b>
            <span>{{ '%.2f'|format(collector.totalDuration * 1000) }} ms</span>
        </div>
    {% endset %}

    {{ include('@WebProfiler/Profiler/toolbar_item.html.twig', {
        link: profiler_url,
        status: statusColor,
    }) }}
{% endblock %}

{% block menu %}
    <span class="label {{ collector.totalDuration > 0.5 ? 'label-status-warning' : '' }}">
        <span class="icon">{{ include('@WebProfiler/Icon/event.svg') }}</span>
        <strong>Services</strong>
        <span class="count">
            <span>{{ collector.serviceCallCount + collector.apiCallCount }}</span>
        </span>
    </span>
{% endblock %}

{% block panel %}
    <h2>Service Metrics</h2>

    {# Summary metrics #}
    <div class="metrics">
        <div class="metric">
            <span class="value">{{ collector.serviceCallCount }}</span>
            <span class="label">Service Calls</span>
        </div>
        <div class="metric">
            <span class="value">{{ collector.apiCallCount }}</span>
            <span class="label">API Calls</span>
        </div>
        <div class="metric">
            <span class="value">{{ '%.2f'|format(collector.totalDuration * 1000) }} ms</span>
            <span class="label">Total Duration</span>
        </div>
    </div>

    {# Tabbed detail panels #}
    <div class="sf-tabs">
        {# Service Calls Tab #}
        <div class="tab">
            <h3 class="tab-title">
                Service Calls
                <span class="badge">{{ collector.serviceCallCount }}</span>
            </h3>
            <div class="tab-content">
                {% if collector.serviceCallCount == 0 %}
                    <div class="empty">
                        <p>No service calls were made during this request.</p>
                    </div>
                {% else %}
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Method</th>
                                <th>Service</th>
                                <th class="text-right">Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            {% for call in collector.serviceCalls %}
                                <tr>
                                    <td class="text-muted">{{ loop.index }}</td>
                                    <td><code>{{ call.name }}</code></td>
                                    <td>
                                        <span class="badge">{{ call.service }}</span>
                                    </td>
                                    <td class="text-right">
                                        <span class="{{ call.duration > 0.2 ? 'text-warning' : '' }}">
                                            {{ '%.2f'|format(call.duration * 1000) }} ms
                                        </span>
                                    </td>
                                </tr>
                            {% endfor %}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3"><strong>Total</strong></td>
                                <td class="text-right">
                                    <strong>
                                        {{ '%.2f'|format(collector.serviceDuration * 1000) }} ms
                                    </strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                {% endif %}
            </div>
        </div>

        {# API Calls Tab #}
        <div class="tab">
            <h3 class="tab-title">
                API Calls
                <span class="badge">{{ collector.apiCallCount }}</span>
            </h3>
            <div class="tab-content">
                {% if collector.apiCallCount == 0 %}
                    <div class="empty">
                        <p>No API calls were made during this request.</p>
                    </div>
                {% else %}
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Endpoint</th>
                                <th class="text-right">Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            {% for call in collector.apiCalls %}
                                <tr>
                                    <td class="text-muted">{{ loop.index }}</td>
                                    <td><code>{{ call.name }}</code></td>
                                    <td class="text-right">
                                        <span class="{{ call.duration > 0.1 ? 'text-warning' : '' }}">
                                            {{ '%.2f'|format(call.duration * 1000) }} ms
                                        </span>
                                    </td>
                                </tr>
                            {% endfor %}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2"><strong>Total</strong></td>
                                <td class="text-right">
                                    <strong>
                                        {{ '%.2f'|format(collector.apiDuration * 1000) }} ms
                                    </strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                {% endif %}
            </div>
        </div>
    </div>
{% endblock %}
```

### Service Registration

```yaml
# config/services/app_profiler.yaml
when@dev:
    services:
        App\DataCollector\ServiceMetricsDataCollector:
            tags:
                - name: data_collector
                  template: 'data_collector/service_metrics.html.twig'
                  id: 'app.service_metrics'
                - name: kernel.event_subscriber
```

## Key Elements Explained

### Public record*() Methods for Instrumentation

The `recordServiceCall()` and `recordApiCall()` methods are the collector's public API.
They accept only scalar parameters (string names, float durations) and append entries to private
accumulator arrays. These methods are called by instrumented service decorators or middleware
during the request:

```php
public function recordServiceCall(string $name, float $duration, string $service): void
{
    $this->serviceCalls[] = [
        'name' => $name,
        'duration' => $duration,
        'timestamp' => microtime(true),
        'service' => $service,
    ];
}
```

The `service` parameter identifies the target service (e.g., `'PaymentGateway'`,
`'NotificationService'`) for service calls, enabling per-service analysis in the profiler panel.

### Summary Statistics in collect()

The `collect()` method computes aggregate statistics (counts and total durations) when transferring
accumulator data into `$this->data`. This avoids recomputing aggregates in Twig templates:

```php
$this->data = [
    'service_calls' => $this->serviceCalls,
    'service_call_count' => \count($this->serviceCalls),
    'service_duration' => $this->sumDuration($this->serviceCalls),
    // ...
];
```

### Toolbar Status Colors for Performance Thresholds

The toolbar uses Symfony's built-in status color classes to highlight slow requests:

```twig
{% set statusColor = collector.totalDuration > 0.5 ? 'yellow' : '' %}

{{ include('@WebProfiler/Profiler/toolbar_item.html.twig', {
    link: profiler_url,
    status: statusColor,
}) }}
```

The `status` parameter accepts `''` (green/default), `'yellow'` (warning), or `'red'` (error).
Individual items in the detail tables also highlight slow operations using `text-warning` when
a single call exceeds a duration threshold.

### Dev-Only Registration

Data collectors are development tools and must never run in production. The `when@dev:` YAML prefix
ensures the service is only registered in the `dev` environment. Alternatively, the `#[When('dev')]`
PHP attribute achieves the same result when using autoconfigure.

## Validation Checklist

- [ ] Class extends `AbstractDataCollector` and implements `EventSubscriberInterface`
- [ ] Class is `final` (NOT `readonly`)
- [ ] `declare(strict_types=1);` at file start
- [ ] `#[Override]` on all overridden methods
- [ ] Private accumulator arrays for service calls, API calls
- [ ] `record*()` methods accept only scalar parameters
- [ ] `collect()` transfers accumulators to `$this->data` with summary stats
- [ ] `reset()` clears `$this->data` and all accumulator arrays
- [ ] `getName()` returns `'app.service_metrics'`
- [ ] `getTemplate()` is `static` and returns `'data_collector/service_metrics.html.twig'`
- [ ] Typed getters for all data fields (counts return `int`, durations return `float`, lists return `array`)
- [ ] Template toolbar shows call counts and total duration
- [ ] Template panel uses `sf-tabs` with Service Calls and API Calls tabs
- [ ] Each tab handles empty state with `<div class="empty">`
- [ ] Duration formatted in milliseconds (`* 1000`)
- [ ] Toolbar status color applied for slow requests
- [ ] Service registered with `when@dev:` block
- [ ] Both `data_collector` and `kernel.event_subscriber` tags applied

## Related Examples

- [`06-event-subscriber-collector.md`](06-event-subscriber-collector.md) -- Event subscriber pattern foundation
- [`07-clone-var-serialization.md`](07-clone-var-serialization.md) -- Serialization techniques for complex data
- [`03-toolbar-template.md`](03-toolbar-template.md) -- Toolbar icon and hover panel details
- [`04-profiler-panel-template.md`](04-profiler-panel-template.md) -- Panel layout with `sf-tabs` and tables
- [`02-service-registration.md`](02-service-registration.md) -- Service registration methods and `when@dev`
