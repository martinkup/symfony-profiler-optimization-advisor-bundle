# Conditional Collection

## Overview

Data collectors do not always need to capture data on every request. Conditional collection strategies reduce profiler overhead and surface only relevant information. This document demonstrates three conditional patterns: exception-only collection, route-specific collection, and threshold-based status coloring in the toolbar. A complete `ErrorTrackingDataCollector` example combines all three patterns.

## When to Use

- **Exception-Only Collection**: Capture detailed debugging data only when an error occurs during the request, avoiding overhead on successful requests
- **Route-Specific Collection**: Limit data collection to specific routes (e.g., API endpoints, admin pages) where profiling is meaningful
- **Threshold-Based Status Colors**: Highlight degraded or critical states in the Web Debug Toolbar using green/yellow/red color coding
- **Empty State Handling**: Provide clear "no data collected" messages in the profiler panel when conditional collection skips a request

## Implementation

### Exception-Only Collection

When data is only useful during error investigation, guard the collection logic behind an exception check.

```php
<?php

declare(strict_types=1);

namespace App\DataCollector;

use Override;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ExceptionOnlyDataCollector extends AbstractDataCollector
{
    #[Override]
    public function collect(
        Request $request,
        Response $response,
        ?\Throwable $exception = null,
    ): void {
        if ($exception === null) {
            $this->data = [
                'has_exception' => false,
                'exception_class' => null,
                'exception_message' => null,
                'exception_code' => null,
            ];

            return;
        }

        $this->data = [
            'has_exception' => true,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
        ];
    }

    #[Override]
    public function getName(): string
    {
        return 'app.exception_only';
    }

    #[Override]
    public static function getTemplate(): ?string
    {
        return 'data_collector/exception_only.html.twig';
    }

    public function hasException(): bool
    {
        return $this->data['has_exception'];
    }

    public function getExceptionClass(): ?string
    {
        return $this->data['exception_class'];
    }

    public function getExceptionMessage(): ?string
    {
        return $this->data['exception_message'];
    }

    public function getExceptionCode(): ?int
    {
        return $this->data['exception_code'];
    }
}
```

### Route-Specific Collection

Inspect `$request->attributes->get('_route')` to limit collection to specific routes.

```php
<?php

declare(strict_types=1);

namespace App\DataCollector;

use Override;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function in_array;
use function is_string;

final class RouteSpecificDataCollector extends AbstractDataCollector
{
    /** @var list<string> */
    private const array MONITORED_ROUTES = [
        'app.dashboard',
        'app.user.list',
        'app.user.edit',
    ];

    #[Override]
    public function collect(
        Request $request,
        Response $response,
        ?\Throwable $exception = null,
    ): void {
        $route = $request->attributes->get('_route');

        if (!is_string($route) || !in_array($route, self::MONITORED_ROUTES, true)) {
            $this->data = [
                'collected' => false,
                'route' => $route,
                'metrics' => [],
            ];

            return;
        }

        $this->data = [
            'collected' => true,
            'route' => $route,
            'metrics' => [
                'status_code' => $response->getStatusCode(),
                'content_length' => $response->headers->get('Content-Length'),
                'cache_hit' => $response->headers->has('X-Cache-Hit'),
            ],
        ];
    }

    #[Override]
    public function getName(): string
    {
        return 'app.route_specific';
    }

    #[Override]
    public static function getTemplate(): ?string
    {
        return 'data_collector/route_specific.html.twig';
    }

    public function isCollected(): bool
    {
        return $this->data['collected'];
    }

    public function getRoute(): ?string
    {
        return $this->data['route'];
    }

    /** @return array<string, mixed> */
    public function getMetrics(): array
    {
        return $this->data['metrics'];
    }
}
```

### Threshold-Based Status Colors in Toolbar

The toolbar supports three status classes that change the toolbar icon background color:

| Class                      | Color  | Meaning                     |
|----------------------------|--------|-----------------------------|
| *(default)*                | Green  | Normal, healthy metrics     |
| `sf-toolbar-status-yellow` | Yellow | Warning threshold exceeded  |
| `sf-toolbar-status-red`    | Red    | Critical threshold exceeded |

The `{% set status_color %}` pattern applies conditional logic to determine which class to use.

```twig
{% extends '@WebProfiler/Profiler/layout.html.twig' %}

{% block toolbar %}
    {# Determine status color based on threshold #}
    {% set error_count = collector.errorCount %}

    {% set status_color %}
        {% if error_count >= 3 %}
            sf-toolbar-status-red
        {% elseif error_count >= 1 %}
            sf-toolbar-status-yellow
        {% endif %}
    {% endset %}

    {% set icon %}
        {{ include('@WebProfiler/Icon/event.svg') }}
        <span class="sf-toolbar-value">{{ error_count }}</span>
    {% endset %}

    {% set text %}
        <div class="sf-toolbar-info-piece">
            <b>Error Count</b>
            <span class="sf-toolbar-status {{ status_color }}">
                {{ error_count }}
            </span>
        </div>
    {% endset %}

    {{ include('@WebProfiler/Profiler/toolbar_item.html.twig', {
        link: profiler_url,
        status: status_color
    }) }}
{% endblock %}
```

### Empty State Handling in Panel

When conditional collection skips data capture, the profiler panel should display a clear empty state rather than broken or misleading content.

```twig
{% block panel %}
    <h2>Error Tracking</h2>

    {% if collector.errorCount == 0 %}
        <div class="empty">
            <p>No errors were recorded during this request.</p>
        </div>
    {% else %}
        <table>
            <thead>
                <tr>
                    <th>Error Class</th>
                    <th>Message</th>
                    <th>Code</th>
                </tr>
            </thead>
            <tbody>
                {% for error in collector.errors %}
                    <tr>
                        <td>{{ error.class }}</td>
                        <td>{{ error.message }}</td>
                        <td>{{ error.code }}</td>
                    </tr>
                {% endfor %}
            </tbody>
        </table>
    {% endif %}
{% endblock %}
```

### Complete Example: ErrorTrackingDataCollector

This collector combines all three conditional patterns: it only captures detailed data when exceptions occur, tracks error counts for threshold-based toolbar coloring, and provides an empty state in the panel.

**Collector class:**

```php
<?php

declare(strict_types=1);

namespace App\DataCollector;

use Override;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ErrorTrackingDataCollector extends AbstractDataCollector
{
    /** @var list<array{class: string, message: string, code: int}> */
    private array $collectedErrors = [];

    public function trackError(\Throwable $exception): void
    {
        $this->collectedErrors[] = [
            'class' => $exception::class,
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ];
    }

    #[Override]
    public function collect(
        Request $request,
        Response $response,
        ?\Throwable $exception = null,
    ): void {
        // Also capture the final exception if present
        if ($exception !== null) {
            $this->trackError($exception);
        }

        $this->data = [
            'error_count' => count($this->collectedErrors),
            'errors' => $this->collectedErrors,
        ];
    }

    #[Override]
    public function reset(): void
    {
        $this->collectedErrors = [];
        $this->data = [];
    }

    #[Override]
    public function getName(): string
    {
        return 'app.error_tracking';
    }

    #[Override]
    public static function getTemplate(): ?string
    {
        return 'data_collector/error_tracking.html.twig';
    }

    public function getErrorCount(): int
    {
        return $this->data['error_count'];
    }

    /** @return list<array{class: string, message: string, code: int}> */
    public function getErrors(): array
    {
        return $this->data['errors'];
    }
}
```

**Twig template** (`templates/data_collector/error_tracking.html.twig`):

```twig
{% extends '@WebProfiler/Profiler/layout.html.twig' %}

{% block toolbar %}
    {% set error_count = collector.errorCount %}

    {% set status_color %}
        {% if error_count >= 3 %}
            sf-toolbar-status-red
        {% elseif error_count >= 1 %}
            sf-toolbar-status-yellow
        {% endif %}
    {% endset %}

    {% set icon %}
        {{ include('@WebProfiler/Icon/exception.svg') }}
        <span class="sf-toolbar-value">{{ error_count }}</span>
    {% endset %}

    {% set text %}
        <div class="sf-toolbar-info-piece">
            <b>Errors</b>
            <span class="sf-toolbar-status {{ status_color }}">
                {{ error_count }}
            </span>
        </div>
        {% if error_count > 0 %}
            <div class="sf-toolbar-info-piece">
                <b>Last Error</b>
                <span>{{ collector.errors|last.class|split('\\')|last }}</span>
            </div>
        {% endif %}
    {% endset %}

    {{ include('@WebProfiler/Profiler/toolbar_item.html.twig', {
        link: profiler_url,
        status: status_color
    }) }}
{% endblock %}

{% block menu %}
    <span class="label
        {{ collector.errorCount >= 3 ? 'label-status-error' }}
        {{ collector.errorCount >= 1 and collector.errorCount < 3
            ? 'label-status-warning' }}">
        <span class="icon">
            {{ include('@WebProfiler/Icon/exception.svg') }}
        </span>
        <strong>Errors</strong>
        {% if collector.errorCount > 0 %}
            <span class="count">
                <span>{{ collector.errorCount }}</span>
            </span>
        {% endif %}
    </span>
{% endblock %}

{% block panel %}
    <h2>Error Tracking</h2>

    {% if collector.errorCount == 0 %}
        <div class="empty">
            <p>No errors were recorded during this request.</p>
        </div>
    {% else %}
        <div class="metrics">
            <div class="metric">
                <span class="value">{{ collector.errorCount }}</span>
                <span class="label">Total Errors</span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Exception Class</th>
                    <th>Message</th>
                    <th>Code</th>
                </tr>
            </thead>
            <tbody>
                {% for error in collector.errors %}
                    <tr>
                        <td>{{ loop.index }}</td>
                        <td>
                            <code>{{ error.class }}</code>
                        </td>
                        <td>{{ error.message }}</td>
                        <td>{{ error.code }}</td>
                    </tr>
                {% endfor %}
            </tbody>
        </table>
    {% endif %}
{% endblock %}
```

## Key Elements Explained

### Exception-Only Guard

The `$exception` parameter in `collect()` is `null` on successful requests and contains the thrown `\Throwable` on errors. Checking `$exception === null` before performing expensive data gathering avoids unnecessary work on the majority of requests.

### Route Matching

`$request->attributes->get('_route')` returns the matched Symfony route name as a string, or `null` for routes that did not match. Comparing against a constant array of monitored routes ensures the collector only activates for relevant endpoints. Using a class constant for the route list keeps configuration centralized and easily maintainable.

### Status Color Cascade

The `{% set status_color %}` block evaluates thresholds from most severe to least severe. The resulting variable is passed to `toolbar_item.html.twig` via the `status` parameter, which applies the CSS class to the toolbar icon background. If no threshold is exceeded, the variable remains empty and the default green color is used.

### Reset with Accumulator

When a collector uses a private accumulator property (like `$collectedErrors`), the `reset()` method **MUST** clear both the accumulator and `$this->data`. Failing to reset the accumulator causes data from a previous request to leak into the next request during long-running processes or the Symfony profiler's "latest" view.

### Empty State Pattern

The `<div class="empty">` element is styled by the Web Profiler bundle to display a centered, muted message. Always provide this fallback when the collector conditionally skips data capture to avoid confusing blank panels.

## Validation Checklist

- [ ] `collect()` handles `$exception === null` case with default empty data
- [ ] Route-specific collector validates `_route` attribute is a string before comparison
- [ ] Threshold logic evaluates from most severe to least severe (red before yellow)
- [ ] `{% set status_color %}` variable passed to `toolbar_item.html.twig` via `status` parameter
- [ ] Panel template includes `<div class="empty">` fallback for zero-data state
- [ ] `reset()` clears both `$this->data` and any private accumulator properties
- [ ] Class is `final` (NOT `readonly`) with `declare(strict_types=1);`
- [ ] All overridden methods have `#[Override]` attribute
- [ ] `getTemplate()` is `static` and returns `?string`
- [ ] `getName()` returns `'app.{purpose}'` format string

## Related Examples

- [`01-basic-data-collector.md`](01-basic-data-collector.md) - Foundation pattern extended here
- [`03-toolbar-template.md`](03-toolbar-template.md) - Toolbar template structure details
- [`04-profiler-panel-template.md`](04-profiler-panel-template.md) - Panel template with tables and empty states
- [`06-event-subscriber-collector.md`](06-event-subscriber-collector.md) - Accumulator pattern used in `trackError()`
- [`10-testing-data-collectors.md`](10-testing-data-collectors.md) - Unit testing patterns for data collectors
