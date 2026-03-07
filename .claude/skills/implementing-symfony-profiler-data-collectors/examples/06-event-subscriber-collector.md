# Event Subscriber Collector

Demonstrates combining `AbstractDataCollector` with `EventSubscriberInterface` to accumulate data across
the request lifecycle. This dual-interface pattern enables the collector to listen to kernel events as they
occur during request processing, then transfer the accumulated data into the serializable `$data` property
when `collect()` is called after the response is sent.

## When to Use

- You need to track events that fire **during** the request lifecycle, not just at the end
- Multiple kernel events must be correlated in a single collector
- Timing data needs to be captured at the moment each event fires
- You want to observe the request pipeline (request -> controller -> response) without modifying it

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
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class KernelEventDataCollector extends AbstractDataCollector implements EventSubscriberInterface
{
    /** @var array<int, array{event: string, timestamp: float, details: string}> */
    private array $collectedEvents = [];

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
            KernelEvents::CONTROLLER => ['onKernelController', 1024],
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->collectedEvents[] = [
            'event' => KernelEvents::REQUEST,
            'timestamp' => microtime(true),
            'details' => $event->getRequest()->getPathInfo(),
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $controller = $event->getController();
        $controllerName = match (true) {
            \is_array($controller) => $controller[0]::class . '::' . $controller[1],
            \is_object($controller) => $controller::class . '::__invoke',
            default => '(unknown)',
        };

        $this->collectedEvents[] = [
            'event' => KernelEvents::CONTROLLER,
            'timestamp' => microtime(true),
            'details' => $controllerName,
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->collectedEvents[] = [
            'event' => KernelEvents::RESPONSE,
            'timestamp' => microtime(true),
            'details' => 'HTTP ' . $event->getResponse()->getStatusCode(),
        ];
    }

    #[Override]
    public function collect(
        Request $request,
        Response $response,
        ?\Throwable $exception = null,
    ): void {
        $this->data = [
            'events' => $this->collectedEvents,
            'event_count' => \count($this->collectedEvents),
        ];
    }

    #[Override]
    public function reset(): void
    {
        $this->data = [];
        $this->collectedEvents = [];
    }

    #[Override]
    public function getName(): string
    {
        return 'app.kernel_events';
    }

    #[Override]
    public static function getTemplate(): ?string
    {
        return 'data_collector/kernel_events.html.twig';
    }

    public function getEventCount(): int
    {
        return $this->data['event_count'] ?? 0;
    }

    /** @return array<int, array{event: string, timestamp: float, details: string}> */
    public function getEvents(): array
    {
        return $this->data['events'] ?? [];
    }
}
```

### Twig Template

```twig
{# templates/data_collector/kernel_events.html.twig #}
{% extends '@WebProfiler/Profiler/layout.html.twig' %}

{% block toolbar %}
    {% set icon %}
        {{ include('@WebProfiler/Icon/event.svg') }}
        <span class="sf-toolbar-value">{{ collector.eventCount }}</span>
    {% endset %}

    {% set text %}
        <div class="sf-toolbar-info-piece">
            <b>Kernel Events</b>
            <span class="sf-toolbar-status">{{ collector.eventCount }}</span>
        </div>
        {% for event in collector.events %}
            <div class="sf-toolbar-info-piece">
                <b>{{ event.event }}</b>
                <span>{{ event.details }}</span>
            </div>
        {% endfor %}
    {% endset %}

    {{ include('@WebProfiler/Profiler/toolbar_item.html.twig', { link: profiler_url }) }}
{% endblock %}

{% block menu %}
    <span class="label">
        <span class="icon">{{ include('@WebProfiler/Icon/event.svg') }}</span>
        <strong>Kernel Events</strong>
        <span class="count">
            <span>{{ collector.eventCount }}</span>
        </span>
    </span>
{% endblock %}

{% block panel %}
    <h2>Kernel Events</h2>

    {% if collector.eventCount == 0 %}
        <div class="empty">
            <p>No kernel events were captured during this request.</p>
        </div>
    {% else %}
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Event</th>
                    <th>Details</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                {% for event in collector.events %}
                    <tr>
                        <td>{{ loop.index }}</td>
                        <td><code>{{ event.event }}</code></td>
                        <td>{{ event.details }}</td>
                        <td>{{ '%.4f'|format(event.timestamp) }}</td>
                    </tr>
                {% endfor %}
            </tbody>
        </table>
    {% endif %}
{% endblock %}
```

### Service Registration

```yaml
# config/services/app_profiler.yaml
when@dev:
    services:
        App\DataCollector\KernelEventDataCollector:
            tags:
                - name: data_collector
                  template: 'data_collector/kernel_events.html.twig'
                  id: 'app.kernel_events'
                - name: kernel.event_subscriber
```

## Key Elements Explained

### Lifecycle: Event Listeners Fire, Then collect() Transfers

The dual-interface pattern separates data **accumulation** from data **storage**:

1. **During request**: `onKernelRequest()`, `onKernelController()`, `onKernelResponse()` fire at their
   respective points in the kernel pipeline. Each appends an entry to the private `$collectedEvents` array.
2. **After response**: Symfony calls `collect(Request, Response, ?Throwable)`. This method copies
   `$collectedEvents` into `$this->data`, which is the serializable property inherited from `DataCollector`.
3. **Profile serialization**: Only `$this->data` is serialized (via `__serialize()`). The private
   `$collectedEvents` property is NOT serialized and is discarded after `collect()` runs.
4. **Profiler rendering**: Twig template reads from `$this->data` through typed getters.

### Private Accumulators Are NOT Serialized

The `DataCollector` base class serializes only the `$data` property via `__serialize()`:

```php
public function __serialize(): array
{
    return ['data' => $this->data];
}
```

Private properties like `$collectedEvents` exist only for the duration of the request. They accumulate
data in real time and are transferred into `$this->data` during `collect()`. After serialization, only
`$data` survives into the profiler viewer.

### reset() Must Clear Both Properties

When Symfony resets the collector between requests (e.g., in long-running processes or tests), both the
serializable `$data` and the private accumulator must be cleared:

```php
public function reset(): void
{
    $this->data = [];
    $this->collectedEvents = [];
}
```

Failing to clear the accumulator causes events from previous requests to leak into subsequent profiles.

### Listener Priority and Main Request Guard

- **High priority on REQUEST/CONTROLLER** (`1024`): Ensures the collector captures events before
  application listeners modify them.
- **Low priority on RESPONSE** (`-1024`): Captures the final response after all modifications.
- **`isMainRequest()` guard**: Prevents sub-requests (e.g., rendered ESI fragments) from polluting
  the event list.

### Service Tags

The class requires two tags when registered manually:

- `data_collector`: Registers it with the Symfony Profiler
- `kernel.event_subscriber`: Registers it with the EventDispatcher

With autoconfigure enabled, both tags are applied automatically because the class extends
`AbstractDataCollector` (provides `data_collector`) and implements `EventSubscriberInterface`
(provides `kernel.event_subscriber`).

## Validation Checklist

- [ ] Class extends `AbstractDataCollector` and implements `EventSubscriberInterface`
- [ ] Class is `final` (NOT `readonly` -- `$data` must be mutable)
- [ ] `declare(strict_types=1);` at file start
- [ ] `#[Override]` on all overridden methods
- [ ] Private accumulator array declared for event data
- [ ] `getSubscribedEvents()` returns event-to-method mapping
- [ ] Each listener method guards with `isMainRequest()`
- [ ] `collect()` transfers accumulator into `$this->data`
- [ ] `reset()` clears both `$this->data` and accumulator
- [ ] `getName()` returns `'app.{purpose}'` string
- [ ] `getTemplate()` is `static` and returns template path
- [ ] Typed getters expose data from `$this->data`
- [ ] Template handles empty state (zero events)
- [ ] Service registered with both `data_collector` and `kernel.event_subscriber` tags
- [ ] Registration wrapped in `when@dev:` block

## Related Examples

- [`01-basic-data-collector.md`](01-basic-data-collector.md) -- Basic collector without event listening
- [`05-late-data-collector.md`](05-late-data-collector.md) -- Post-response data via `LateDataCollectorInterface`
- [`08-domain-metrics-collector.md`](08-domain-metrics-collector.md) -- Service metrics using event subscriber pattern
- [`03-toolbar-template.md`](03-toolbar-template.md) -- Detailed toolbar block patterns
