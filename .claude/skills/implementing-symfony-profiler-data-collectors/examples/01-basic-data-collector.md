# Basic Data Collector

## Overview

This example demonstrates the simplest `AbstractDataCollector` subclass that tracks the number of Twig template renders per request. It shows the minimal structure needed: extending `AbstractDataCollector`, implementing `collect()` to capture data from the request, providing typed getters for the template, and overriding `reset()` to clear state between requests.

## When to Use

- **Simple Request Metrics**: Counting occurrences of a specific action during a request
- **Request Attribute Tracking**: Reading data stored in request attributes by listeners or middleware
- **Lightweight Monitoring**: Metrics that require no event subscription or late collection
- **First Data Collector**: Starting point when learning the data collector pattern

## Implementation

### TemplateRenderDataCollector

```php
<?php

declare(strict_types=1);

namespace App\DataCollector;

use Override;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tracks the number of Twig template renders per request.
 *
 * Reads render data from request attributes populated by a kernel event listener.
 * Displays render count and template list in the Web Debug Toolbar.
 */
final class TemplateRenderDataCollector extends AbstractDataCollector
{
    #[Override]
    public function collect(
        Request $request,
        Response $response,
        ?\Throwable $exception = null,
    ): void {
        /** @var array<int, string> $templates */
        $templates = $request->attributes->get('_rendered_templates', []);

        $this->data = [
            'render_count' => count($templates),
            'templates' => $templates,
        ];
    }

    #[Override]
    public function getName(): string
    {
        return 'app.template_render';
    }

    #[Override]
    public static function getTemplate(): ?string
    {
        return 'data_collector/template_render.html.twig';
    }

    #[Override]
    public function reset(): void
    {
        $this->data = [
            'render_count' => 0,
            'templates' => [],
        ];
    }

    public function getRenderCount(): int
    {
        return $this->data['render_count'];
    }

    /** @return array<int, string> */
    public function getTemplates(): array
    {
        return $this->data['templates'];
    }
}
```

## Key Elements Explained

### 1. Class Declaration: `final` but NOT `readonly`

```php
final class TemplateRenderDataCollector extends AbstractDataCollector
```

- **`final`**: Recommended practice -- all classes are `final` by default
- **NOT `readonly`**: The `$data` property inherited from `DataCollector` is mutable (`protected array|Data $data = []`). Declaring the class `readonly` would conflict with this mutable property and cause a fatal error

### 2. Extending `AbstractDataCollector`

```php
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
```

`AbstractDataCollector` extends `DataCollector` and implements `TemplateAwareDataCollectorInterface`. This provides:

- The mutable `$data` property for storing collected metrics
- The `cloneVar()` method for serializing complex objects
- Default `getName()` returning the FQCN (overridden here)
- Default `getTemplate()` returning `null` (overridden here)
- Default `reset()` that sets `$this->data = []` (overridden here for type safety)

### 3. The `collect()` Method

```php
public function collect(
    Request $request,
    Response $response,
    ?\Throwable $exception = null,
): void {
    /** @var array<int, string> $templates */
    $templates = $request->attributes->get('_rendered_templates', []);

    $this->data = [
        'render_count' => count($templates),
        'templates' => $templates,
    ];
}
```

- Called by the Symfony Profiler after the response is sent
- Receives the `Request`, `Response`, and any uncaught `Throwable`
- Reads data from request attributes (populated by a listener elsewhere)
- Stores only **serializable** data in `$this->data` -- scalars and arrays of scalars are safe. Never store entities, services, or closures

### 4. `getName()` Returns a Short Identifier

```php
public function getName(): string
{
    return 'app.template_render';
}
```

- Convention: `'app.{purpose}'` with snake_case purpose
- Used as the collector ID in service configuration and the profiler URL
- Must be unique across all registered data collectors

### 5. `getTemplate()` is `static`

```php
public static function getTemplate(): ?string
{
    return 'data_collector/template_render.html.twig';
}
```

- **Must be `static`** -- this is a requirement of `TemplateAwareDataCollectorInterface` in Symfony 8.0
- Returns the path to the Twig template relative to the templates directory
- Convention: `'data_collector/{purpose}.html.twig'`
- Return `null` to hide the collector from the profiler panel (toolbar-only collectors are unusual)

### 6. `reset()` Override

```php
public function reset(): void
{
    $this->data = [
        'render_count' => 0,
        'templates' => [],
    ];
}
```

- Called between requests when the profiler resets state (e.g., in long-running workers)
- The base `DataCollector::reset()` simply sets `$this->data = []`, which is valid but loses the typed structure
- Overriding with explicit keys ensures getters always find expected keys, preventing `undefined index` warnings if called after reset but before the next `collect()`

### 7. Typed Getter Methods

```php
public function getRenderCount(): int
{
    return $this->data['render_count'];
}

/** @return array<int, string> */
public function getTemplates(): array
{
    return $this->data['templates'];
}
```

- Expose collected data through typed accessor methods
- Used by the Twig template: `{{ collector.renderCount }}`, `{{ collector.templates }}`
- PHPDoc `@return` annotations provide PHPStan with generic type information
- Never expose raw `$data` -- getters provide a stable public API

## Validation Checklist

- [ ] `declare(strict_types=1);` at file start
- [ ] `final` class (NOT `readonly`)
- [ ] Extends `AbstractDataCollector`
- [ ] `#[Override]` on `collect()`, `getName()`, `getTemplate()`, `reset()`
- [ ] `collect()` stores only serializable data (scalars, arrays)
- [ ] `getName()` returns `'app.{purpose}'` string
- [ ] `getTemplate()` is `static` and returns template path
- [ ] `reset()` resets `$data` to valid default structure
- [ ] Typed getter methods for every data field
- [ ] All lines within 120 character limit

## Related Examples

- [`02-service-registration.md`](02-service-registration.md) - How to register this collector as a service
- [`03-toolbar-template.md`](03-toolbar-template.md) - Twig template for the toolbar icon and hover panel
- [`04-profiler-panel-template.md`](04-profiler-panel-template.md) - Full profiler panel template
- [`07-clone-var-serialization.md`](07-clone-var-serialization.md) - Handling complex objects with `cloneVar()`
