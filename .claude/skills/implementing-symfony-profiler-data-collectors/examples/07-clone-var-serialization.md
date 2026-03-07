# cloneVar() Serialization

Demonstrates using the `cloneVar()` method to safely serialize complex, non-serializable objects into the
`$data` property. The `DataCollector` base class requires `$data` to be fully serializable because the
profiler persists collected data to storage. Raw objects such as `Request`, services, or entities cannot
be stored directly -- `cloneVar()` converts them into `Data` instances that the VarDumper component can
render in the profiler panel.

## When to Use

- You need to inspect complex objects (request attributes, session data, headers) in the profiler
- Objects contain circular references or non-serializable properties
- You want the VarDumper's interactive tree view in the profiler panel
- Custom object presentation is needed via `getCasters()` override

## Implementation

### PHP Class

```php
<?php

declare(strict_types=1);

namespace App\DataCollector;

use Override;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;

final class RequestInspectorDataCollector extends AbstractDataCollector
{
    #[Override]
    public function collect(
        Request $request,
        Response $response,
        ?\Throwable $exception = null,
    ): void {
        $this->data = [
            // Complex objects: use cloneVar() for safe serialization
            'request_attributes' => $this->cloneVar($request->attributes->all()),
            'request_headers' => $this->cloneVar($request->headers->all()),
            'session_data' => $this->cloneVar(
                $request->hasSession() ? $request->getSession()->all() : [],
            ),
            'server_params' => $this->cloneVar($this->filterServerParams(
                $request->server->all(),
            )),

            // Scalar values: store directly without cloneVar()
            'method' => $request->getMethod(),
            'path_info' => $request->getPathInfo(),
            'status_code' => $response->getStatusCode(),
            'content_type' => $response->headers->get('Content-Type', 'unknown'),
            'request_format' => $request->getRequestFormat('html'),
        ];
    }

    #[Override]
    public function getName(): string
    {
        return 'app.request_inspector';
    }

    #[Override]
    public static function getTemplate(): ?string
    {
        return 'data_collector/request_inspector.html.twig';
    }

    // --- Getters returning Data (cloneVar'd objects) ---

    public function getRequestAttributes(): Data
    {
        return $this->data['request_attributes'];
    }

    public function getRequestHeaders(): Data
    {
        return $this->data['request_headers'];
    }

    public function getSessionData(): Data
    {
        return $this->data['session_data'];
    }

    public function getServerParams(): Data
    {
        return $this->data['server_params'];
    }

    // --- Getters returning scalar types ---

    public function getMethod(): string
    {
        return $this->data['method'] ?? '';
    }

    public function getPathInfo(): string
    {
        return $this->data['path_info'] ?? '';
    }

    public function getStatusCode(): int
    {
        return $this->data['status_code'] ?? 0;
    }

    public function getContentType(): string
    {
        return $this->data['content_type'] ?? 'unknown';
    }

    public function getRequestFormat(): string
    {
        return $this->data['request_format'] ?? 'html';
    }

    /**
     * Override getCasters() to customize how specific objects are presented
     * in the VarDumper tree view. Each caster receives the object and returns
     * a modified attribute array controlling what is displayed.
     *
     * @return array<string, callable>
     */
    #[Override]
    protected function getCasters(): array
    {
        return parent::getCasters() + [
            \DateTimeInterface::class => static function (
                \DateTimeInterface $date,
                array $a,
            ): array {
                $a['formatted'] = $date->format(\DateTimeInterface::ATOM);

                return $a;
            },
        ];
    }

    /**
     * @param array<string, mixed> $serverParams
     * @return array<string, mixed>
     */
    private function filterServerParams(array $serverParams): array
    {
        $filtered = [];
        $allowedPrefixes = ['HTTP_', 'SERVER_', 'REQUEST_'];

        foreach ($serverParams as $key => $value) {
            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $filtered[$key] = $value;
                    break;
                }
            }
        }

        return $filtered;
    }
}
```

### Twig Template

```twig
{# templates/data_collector/request_inspector.html.twig #}
{% extends '@WebProfiler/Profiler/layout.html.twig' %}

{% block toolbar %}
    {% set icon %}
        {{ include('@WebProfiler/Icon/request.svg') }}
        <span class="sf-toolbar-value">{{ collector.method }}</span>
    {% endset %}

    {% set text %}
        <div class="sf-toolbar-info-piece">
            <b>Method</b>
            <span>{{ collector.method }}</span>
        </div>
        <div class="sf-toolbar-info-piece">
            <b>Path</b>
            <span>{{ collector.pathInfo }}</span>
        </div>
        <div class="sf-toolbar-info-piece">
            <b>Status</b>
            <span class="sf-toolbar-status sf-toolbar-status-{{ collector.statusCode < 400 ? 'green' : 'red' }}">
                {{ collector.statusCode }}
            </span>
        </div>
        <div class="sf-toolbar-info-piece">
            <b>Format</b>
            <span>{{ collector.requestFormat }}</span>
        </div>
    {% endset %}

    {{ include('@WebProfiler/Profiler/toolbar_item.html.twig', { link: profiler_url }) }}
{% endblock %}

{% block menu %}
    <span class="label">
        <span class="icon">{{ include('@WebProfiler/Icon/request.svg') }}</span>
        <strong>Request Inspector</strong>
    </span>
{% endblock %}

{% block panel %}
    <h2>Request Inspector</h2>

    {# Scalar summary #}
    <div class="metrics">
        <div class="metric">
            <span class="value">{{ collector.method }}</span>
            <span class="label">Method</span>
        </div>
        <div class="metric">
            <span class="value">{{ collector.statusCode }}</span>
            <span class="label">Status</span>
        </div>
        <div class="metric">
            <span class="value">{{ collector.contentType }}</span>
            <span class="label">Content-Type</span>
        </div>
        <div class="metric">
            <span class="value">{{ collector.requestFormat }}</span>
            <span class="label">Format</span>
        </div>
    </div>

    {# Tabbed panels for cloneVar'd data #}
    <div class="sf-tabs">
        <div class="tab">
            <h3 class="tab-title">Attributes</h3>
            <div class="tab-content">
                <h3>Request Attributes</h3>
                {{ profiler_dump(collector.requestAttributes) }}
            </div>
        </div>

        <div class="tab">
            <h3 class="tab-title">Headers</h3>
            <div class="tab-content">
                <h3>Request Headers</h3>
                {{ profiler_dump(collector.requestHeaders) }}
            </div>
        </div>

        <div class="tab">
            <h3 class="tab-title">Session</h3>
            <div class="tab-content">
                <h3>Session Data</h3>
                {{ profiler_dump(collector.sessionData) }}
            </div>
        </div>

        <div class="tab">
            <h3 class="tab-title">Server</h3>
            <div class="tab-content">
                <h3>Server Parameters</h3>
                {{ profiler_dump(collector.serverParams) }}
            </div>
        </div>
    </div>
{% endblock %}
```

## Key Elements Explained

### Why cloneVar() Is Needed

The `$data` property is serialized when the profiler stores the request profile. PHP's native
serialization fails on:

- Objects with circular references (e.g., `Request` referencing itself via attributes)
- Resources (file handles, database connections)
- Closures and anonymous functions
- Services with non-serializable dependencies

`cloneVar()` converts any variable into a `Data` instance from the VarDumper component. `Data` objects
are always serializable and retain the structure and type information of the original variable for
interactive display in the profiler.

### Data Return Type from cloneVar()

`cloneVar()` returns `Symfony\Component\VarDumper\Cloner\Data`. Getters that expose cloned variables
must declare `Data` as their return type:

```php
public function getRequestAttributes(): Data
{
    return $this->data['request_attributes'];
}
```

In Twig templates, `Data` objects are rendered with `profiler_dump()`, which produces the interactive
VarDumper tree view:

```twig
{{ profiler_dump(collector.requestAttributes) }}
```

### When to Use cloneVar() vs Extracting Scalar Values

| Data Type                         | Approach                  | Example                                                             |
|-----------------------------------|---------------------------|---------------------------------------------------------------------|
| Scalar (string, int, float, bool) | Store directly            | `$this->data['method'] = $request->getMethod()`                     |
| Simple array of scalars           | Store directly            | `$this->data['params'] = ['key' => 'value']`                        |
| Complex object                    | Use `cloneVar()`          | `$this->data['attrs'] = $this->cloneVar($obj)`                      |
| Array of objects                  | Use `cloneVar()`          | `$this->data['items'] = $this->cloneVar($items)`                    |
| Object with circular refs         | Use `cloneVar()`          | `$this->data['req'] = $this->cloneVar($request)`                    |
| Sensitive data (passwords)        | Extract sanitized scalars | `$this->data['has_auth'] = $request->headers->has('Authorization')` |

**Rule of thumb**: If the value is a primitive or a simple array of primitives, store it directly for
better getter ergonomics (typed scalar returns). If the value is an object or contains objects, use
`cloneVar()`.

### getCasters() Override for Custom Presentation

The `getCasters()` method returns an array of callables keyed by class name. Each caster receives the
object being cloned and its attributes array, and returns a modified attributes array. This controls
what the VarDumper tree displays:

```php
protected function getCasters(): array
{
    return parent::getCasters() + [
        \DateTimeInterface::class => static function (
            \DateTimeInterface $date,
            array $a,
        ): array {
            $a['formatted'] = $date->format(\DateTimeInterface::ATOM);
            return $a;
        },
    ];
}
```

Always call `parent::getCasters()` to preserve the base casters that handle nested object truncation.

### Anti-Pattern: Storing Raw Objects Without cloneVar()

```php
// FORBIDDEN -- causes serialization failure
$this->data['request'] = $request;
$this->data['session'] = $request->getSession();
$this->data['user'] = $request->getUser();

// CORRECT -- cloneVar() makes objects serializable
$this->data['request'] = $this->cloneVar($request);
$this->data['session'] = $this->cloneVar($request->getSession());

// ALSO CORRECT -- extract only the scalar values you need
$this->data['user_id'] = $request->getUser()?->getUserIdentifier();
$this->data['session_id'] = $request->getSession()->getId();
```

Storing raw objects leads to profiler corruption: the serialization either fails with an exception or
produces truncated/broken data that cannot be unserialized when viewing the profile.

## Validation Checklist

- [ ] `cloneVar()` used for all non-scalar data stored in `$this->data`
- [ ] Getters for cloned data return `Data` type
- [ ] Getters for scalar data return appropriate scalar types (`string`, `int`, `float`, `bool`)
- [ ] Template uses `profiler_dump()` to render `Data` objects
- [ ] Template uses direct output for scalar values
- [ ] `getCasters()` override calls `parent::getCasters()` if present
- [ ] No raw objects stored directly in `$this->data`
- [ ] Sensitive data filtered or excluded before `cloneVar()`
- [ ] Class is `final` (NOT `readonly`)
- [ ] `#[Override]` on all overridden methods
- [ ] `declare(strict_types=1);` at file start
- [ ] `getName()` returns `'app.{purpose}'` string
- [ ] `getTemplate()` is `static` and returns template path

## Related Examples

- [`01-basic-data-collector.md`](01-basic-data-collector.md) -- Basic collector with scalar data only
- [`04-profiler-panel-template.md`](04-profiler-panel-template.md) -- Panel template with `sf-tabs`
- [`06-event-subscriber-collector.md`](06-event-subscriber-collector.md) -- Accumulator pattern for event data
- [`08-domain-metrics-collector.md`](08-domain-metrics-collector.md) -- Service metrics combining scalars and cloned data
