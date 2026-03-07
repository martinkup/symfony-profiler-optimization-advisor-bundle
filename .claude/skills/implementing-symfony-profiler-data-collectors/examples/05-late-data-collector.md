# Late Data Collector

A late data collector implements `LateDataCollectorInterface` alongside `AbstractDataCollector` to capture metrics that are only available after the response has been sent to the client. The `collect()` method captures request-time data while `lateCollect()` runs post-response to gather final measurements such as peak memory usage, total execution duration, or accumulated event counts. This two-phase approach ensures accurate profiling without impacting response time.

## When to Use

- Measuring peak memory usage, which is only meaningful after all processing completes
- Capturing total request duration including kernel termination work
- Collecting metrics from services that finalize their state after the response (e.g., message bus flush counts)
- Aggregating data from listeners that fire during `kernel.terminate`
- Gathering post-response cleanup statistics (cache warm-up, deferred writes)

## Implementation

```php
<?php

declare(strict_types=1);

namespace App\DataCollector;

use Override;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

final class PerformanceDataCollector extends AbstractDataCollector implements LateDataCollectorInterface
{
    private float $requestStartTime = 0.0;

    #[Override]
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->requestStartTime = $request->server->get('REQUEST_TIME_FLOAT', microtime(true));

        $this->data = [
            'route' => $request->attributes->get('_route', 'unknown'),
            'controller' => $request->attributes->get('_controller', 'unknown'),
            'status_code' => $response->getStatusCode(),
            'request_memory' => memory_get_usage(true),
            'peak_memory' => 0,
            'total_duration_ms' => 0.0,
            'response_size_bytes' => strlen($response->getContent() ?: ''),
        ];
    }

    #[Override]
    public function lateCollect(): void
    {
        $this->data['peak_memory'] = memory_get_peak_usage(true);
        $this->data['total_duration_ms'] = (microtime(true) - $this->requestStartTime) * 1000;
    }

    #[Override]
    public function reset(): void
    {
        $this->data = [];
        $this->requestStartTime = 0.0;
    }

    #[Override]
    public function getName(): string
    {
        return 'app.performance';
    }

    #[Override]
    public static function getTemplate(): ?string
    {
        return 'data_collector/performance.html.twig';
    }

    public function getRoute(): string
    {
        return $this->data['route'];
    }

    public function getController(): string
    {
        return $this->data['controller'];
    }

    public function getStatusCode(): int
    {
        return $this->data['status_code'];
    }

    public function getRequestMemory(): int
    {
        return $this->data['request_memory'];
    }

    public function getPeakMemory(): int
    {
        return $this->data['peak_memory'];
    }

    public function getTotalDurationMs(): float
    {
        return $this->data['total_duration_ms'];
    }

    public function getResponseSizeBytes(): int
    {
        return $this->data['response_size_bytes'];
    }

    public function getFormattedPeakMemory(): string
    {
        return $this->formatBytes($this->data['peak_memory']);
    }

    public function getFormattedRequestMemory(): string
    {
        return $this->formatBytes($this->data['request_memory']);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return sprintf('%.2f MB', $bytes / 1_048_576);
        }

        return sprintf('%.2f KB', $bytes / 1_024);
    }
}
```

## Key Elements Explained

### Class Declaration

The class is declared `final` but **not** `readonly`. The `$data` property inherited from `DataCollector` must remain mutable because it is written to during both `collect()` and `lateCollect()`, and later serialized by the profiler. The private `$requestStartTime` property also requires mutability since it is set in `collect()` and read in `lateCollect()`.

### LateDataCollectorInterface

Implementing `LateDataCollectorInterface` requires a single method:

```php
public function lateCollect(): void;
```

This interface signals the Symfony profiler to call `lateCollect()` after the response has been sent. The class implements both `AbstractDataCollector` (which provides `collect()`, `getName()`, `getTemplate()`, and `reset()`) and `LateDataCollectorInterface` (which provides `lateCollect()`).

### Lifecycle: collect() vs lateCollect()

The two-phase collection lifecycle works as follows:

```text
1. Request arrives
2. Controller executes, response generated
3. collect(Request, Response, ?Throwable) called     <-- Phase 1
   - Access to Request and Response objects
   - Capture route, controller, status code, current memory
   - Store request start time for duration calculation
4. Response sent to client
5. kernel.terminate event dispatched
6. lateCollect() called                               <-- Phase 2
   - No parameters (Request/Response not available)
   - Access $this->data set during collect()
   - Capture peak memory, total duration
7. Profile serialized to storage
```

**Phase 1 -- `collect()`**: Called immediately after the response is created but before it is sent. It receives the `Request`, `Response`, and an optional `Throwable` (if an exception occurred). Use this phase to capture request-specific data such as the route name, controller class, status code, and response size. The request start time is saved to a private property for use in Phase 2.

**Phase 2 -- `lateCollect()`**: Called after the response has been fully sent to the client and after the `kernel.terminate` event. It receives no parameters but can access and modify `$this->data` that was populated during `collect()`. Use this phase for measurements that must include post-response work, such as peak memory (which may increase during terminate listeners) and total wall-clock duration.

### When to Use lateCollect() vs collect()

| Data Source                             | Use `collect()` | Use `lateCollect()`                       |
|-----------------------------------------|-----------------|-------------------------------------------|
| Request attributes (route, controller)  | Yes             | No -- Request not available               |
| Response data (status, headers, body)   | Yes             | No -- Response not available              |
| Current memory usage at response time   | Yes             | No                                        |
| Peak memory usage (final)               | No              | Yes -- includes terminate phase           |
| Total wall-clock duration               | No              | Yes -- includes terminate phase           |
| Deferred service metrics (flush counts) | No              | Yes -- services finalize post-response    |
| Exception information                   | Yes             | No -- Throwable parameter only in collect |

### Accessing Data Between Phases

The `lateCollect()` method can read and modify `$this->data` that was set during `collect()`. This allows it to augment existing data rather than replace it. In this example, `collect()` initializes `peak_memory` and `total_duration_ms` to zero, and `lateCollect()` overwrites them with final values. The private `$requestStartTime` property bridges the two phases for duration calculation.

### reset() Method

The `reset()` method must clear both the `$data` array and any private accumulator properties. The profiler calls `reset()` between requests when running under a long-lived process (e.g., Swoole, RoadRunner). Failing to reset private properties causes data leakage between requests.

```php
#[Override]
public function reset(): void
{
    $this->data = [];
    $this->requestStartTime = 0.0;
}
```

### Typed Getters

Every value stored in `$this->data` is exposed through a typed getter method. This provides type safety for templates and tests, and decouples the internal data structure from external consumers. Formatting helpers like `getFormattedPeakMemory()` keep presentation logic in the collector rather than the Twig template.

## Validation Checklist

- [ ] Class declared `final` (not `readonly`)
- [ ] Class extends `AbstractDataCollector`
- [ ] Class implements `LateDataCollectorInterface`
- [ ] `declare(strict_types=1);` present at file start
- [ ] `#[Override]` attribute on `collect()`, `lateCollect()`, `reset()`, `getName()`, `getTemplate()`
- [ ] `collect()` stores only serializable scalars and arrays in `$this->data`
- [ ] `lateCollect()` accesses `$this->data` set during `collect()` without parameters
- [ ] `reset()` clears `$this->data` AND all private accumulator properties
- [ ] `getName()` returns short `'app.{purpose}'` string
- [ ] `getTemplate()` is `static` and returns template path string
- [ ] Typed getter methods for every data field
- [ ] Private properties used to bridge data between `collect()` and `lateCollect()`
- [ ] All lines within 120 characters

## Related Examples

- [`01-basic-data-collector.md`](01-basic-data-collector.md) -- Basic collector without late collection
- [`04-profiler-panel-template.md`](04-profiler-panel-template.md) -- Twig template to render collected data
- [`06-event-subscriber-collector.md`](06-event-subscriber-collector.md) -- Accumulating data during request via events
- [`07-clone-var-serialization.md`](07-clone-var-serialization.md) -- Storing complex objects with `cloneVar()`
