# Service Registration

## Overview

Data collectors must be registered as Symfony services tagged with `data_collector`. This example shows three registration methods side by side -- autoconfigure (preferred), YAML tag (manual), and the `#[AutoconfigureTag]` PHP attribute -- plus dev-only patterns to prevent collectors from running in production.

## When to Use

- **Autoconfigure**: Default choice when extending `AbstractDataCollector` with standard auto-discovery
- **YAML Tag**: When overriding `id` or `template` attributes, or when autoconfigure is insufficient
- **`#[AutoconfigureTag]`**: When you need explicit tag control directly in the PHP class
- **Dev-Only**: Always -- data collectors MUST NOT be registered in production

## Implementation

### Method 1: Autoconfigure (Preferred)

Classes extending `AbstractDataCollector` are automatically tagged with `data_collector` by Symfony's autoconfigure mechanism. No manual registration is needed as long as services are auto-discovered.

```yaml
# config/services/app_profiler.yaml
when@dev:
    services:
        _defaults:
            autowire: true
            autoconfigure: true

        App\DataCollector\:
            resource: '../../src/DataCollector/'
```

The autoconfigure mechanism detects that the class extends `AbstractDataCollector` (which implements `TemplateAwareDataCollectorInterface`) and automatically adds the `data_collector` tag. The `id` is resolved from `getName()` and the `template` from `static getTemplate()`.

**Requirements for autoconfigure to work:**

- Class must extend `AbstractDataCollector` (or implement `DataCollectorInterface`)
- `autoconfigure: true` must be set in service defaults
- The class must be discoverable via the `resource` path

### Method 2: YAML Tag (Manual)

Register the service explicitly with the `data_collector` tag and its attributes.

```yaml
# config/services/app_profiler.yaml
when@dev:
    services:
        App\DataCollector\TemplateRenderDataCollector:
            tags:
                - name: data_collector
                  template: 'data_collector/template_render.html.twig'
                  id: 'app.template_render'
```

**Tag attributes:**

| Attribute  | Purpose                       | Default                           |
|------------|-------------------------------|-----------------------------------|
| `template` | Path to the Twig template     | Value from `static getTemplate()` |
| `id`       | Unique collector identifier   | Value from `getName()`            |
| `priority` | Load order (higher = earlier) | `0`                               |

When both YAML attributes and class methods (`getName()`, `getTemplate()`) are defined, the YAML tag attributes take precedence and override the values returned by the class methods.

### Method 3: `#[AutoconfigureTag]` Attribute

Apply the tag directly on the PHP class using the Symfony DI attribute.

```php
<?php

declare(strict_types=1);

namespace App\DataCollector;

use Override;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AutoconfigureTag('data_collector', [
    'template' => 'data_collector/template_render.html.twig',
    'id' => 'app.template_render',
])]
final class TemplateRenderDataCollector extends AbstractDataCollector
{
    #[Override]
    public function collect(
        Request $request,
        Response $response,
        ?\Throwable $exception = null,
    ): void {
        $this->data = [
            'render_count' => 0,
            'templates' => [],
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
}
```

When using `#[AutoconfigureTag]` together with extending `AbstractDataCollector`, the tag is applied explicitly via the attribute. The autoconfigure mechanism still runs but the explicit attribute takes precedence for the tag configuration.

### Dev-Only Patterns

Data collectors MUST only be registered in the `dev` environment. Two approaches:

**YAML `when@dev:` block (preferred for YAML registration):**

```yaml
# config/services/app_profiler.yaml
when@dev:
    services:
        App\DataCollector\TemplateRenderDataCollector:
            tags:
                - name: data_collector
                  template: 'data_collector/template_render.html.twig'
                  id: 'app.template_render'
```

**`#[When('dev')]` PHP attribute (preferred for autoconfigure):**

```php
<?php

declare(strict_types=1);

namespace App\DataCollector;

use Override;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[When('dev')]
final class TemplateRenderDataCollector extends AbstractDataCollector
{
    #[Override]
    public function collect(
        Request $request,
        Response $response,
        ?\Throwable $exception = null,
    ): void {
        $this->data = [
            'render_count' => 0,
            'templates' => [],
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
}
```

The `#[When('dev')]` attribute excludes the class from the service container entirely in non-dev environments. This is the cleanest approach when using autoconfigure because it requires no YAML configuration at all.

## Key Elements Explained

### 1. Comparison Table

| Method                    | Configuration Location              | Dev-Only Mechanism        | Best For                                   |
|---------------------------|-------------------------------------|---------------------------|--------------------------------------------|
| **Autoconfigure**         | None (auto-detected)                | `#[When('dev')]` on class | Standard collectors with no special config |
| **YAML Tag**              | `config/services/app_profiler.yaml` | `when@dev:` YAML block    | Overriding `id`/`template`, complex DI     |
| **`#[AutoconfigureTag]`** | PHP attribute on class              | `#[When('dev')]` on class | Explicit tag control in source code        |

### 2. Priority and Override Behavior

When multiple registration methods are combined, Symfony resolves conflicts as follows:

| Conflict                               | Resolution               |
|----------------------------------------|--------------------------|
| YAML `id` vs `getName()`               | YAML wins                |
| YAML `template` vs `getTemplate()`     | YAML wins                |
| `#[AutoconfigureTag]` vs autoconfigure | Explicit attribute wins  |
| YAML tag vs `#[AutoconfigureTag]`      | YAML wins (applied last) |
| Multiple `#[AutoconfigureTag]`         | All tags applied         |

**Recommendation**: Avoid mixing methods for the same collector. Pick one approach and use it consistently.

### 3. Autoconfigure Detection

Symfony's autoconfigure works by checking the class hierarchy:

- If the class implements `DataCollectorInterface`, it gets the `data_collector` tag
- If it also implements `TemplateAwareDataCollectorInterface`, the `template` attribute is resolved from `static getTemplate()`
- `AbstractDataCollector` implements both interfaces, so extending it is sufficient

### 4. Service File Loading

The service configuration file must be loaded by the Symfony kernel. Service files in `config/services/` are auto-loaded by Symfony. When using `when@dev:`, the entire block is ignored in `prod` and `test` environments.

```yaml
# config/services.yaml (main service file)
imports:
    - { resource: 'services/' }
```

### 5. Why Dev-Only Is Mandatory

Data collectors add overhead to every request:

- `collect()` is called after every response
- Profiler serializes all collected data to storage
- Memory usage increases with complex `cloneVar()` calls
- Toolbar rendering adds HTML to the response

In production, the profiler is disabled and collectors are never called, but the services are still instantiated and injected if registered. Using `when@dev:` or `#[When('dev')]` prevents even the instantiation cost.

## Validation Checklist

- [ ] Collector registered in `dev` environment only
- [ ] One registration method chosen (autoconfigure, YAML, or attribute)
- [ ] `id` in tag matches `getName()` return value
- [ ] `template` in tag matches `static getTemplate()` return value
- [ ] Service configuration file is loaded by the kernel
- [ ] `autowire: true` and `autoconfigure: true` set when using autoconfigure
- [ ] No duplicate registrations across methods

## Related Examples

- [`01-basic-data-collector.md`](01-basic-data-collector.md) - The collector class being registered
- [`03-toolbar-template.md`](03-toolbar-template.md) - The Twig template referenced by the registration
- [`06-event-subscriber-collector.md`](06-event-subscriber-collector.md) - Registration of dual-interface collectors
- [`08-domain-metrics-collector.md`](08-domain-metrics-collector.md) - Collector with injected dependencies
