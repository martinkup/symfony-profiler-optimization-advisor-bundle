# Toolbar Template

## Overview

The toolbar template defines how a data collector appears in the Symfony Web Debug Toolbar -- the horizontal bar at the bottom of the page during development. It consists of two visual parts: the **icon** (always visible in the toolbar) and the **text** (shown on hover as a tooltip panel). Status color classes allow the icon to change color based on metric thresholds, drawing attention to potential issues.

## When to Use

- **Every Data Collector**: Any collector with a `getTemplate()` returning a non-null path needs a toolbar template
- **At-a-Glance Metrics**: Displaying a key count or timing value directly in the toolbar
- **Threshold Alerts**: Highlighting problematic metrics with yellow or red status colors
- **Drill-Down Link**: Connecting the toolbar icon to a detailed profiler panel

## Implementation

### Complete Toolbar Template

```twig
{# templates/data_collector/template_render.html.twig #}

{% extends '@WebProfiler/Profiler/layout.html.twig' %}

{% block toolbar %}
    {% set icon %}
        {{ include('@WebProfiler/Icon/twig.svg') }}
        <span class="sf-toolbar-value">{{ collector.renderCount }}</span>
        <span class="sf-toolbar-label">renders</span>
    {% endset %}

    {% set text %}
        <div class="sf-toolbar-info-piece">
            <b>Render Count</b>
            <span class="sf-toolbar-status {{ collector.renderCount > 50
                ? 'sf-toolbar-status-red'
                : (collector.renderCount > 20
                    ? 'sf-toolbar-status-yellow'
                    : '') }}">
                {{ collector.renderCount }}
            </span>
        </div>

        {% for template in collector.templates %}
            <div class="sf-toolbar-info-piece">
                <b>Template</b>
                <span>{{ template }}</span>
            </div>
        {% endfor %}

        {% if collector.templates is empty %}
            <div class="sf-toolbar-info-piece">
                <b>Templates</b>
                <span class="sf-toolbar-status">0</span>
            </div>
        {% endif %}
    {% endset %}

    {{ include('@WebProfiler/Profiler/toolbar_item.html.twig', {
        link: profiler_url
    }) }}
{% endblock %}
```

## Key Elements Explained

### 1. Template Inheritance

```twig
{% extends '@WebProfiler/Profiler/layout.html.twig' %}
```

Every data collector template extends the WebProfiler layout. This base template defines four overridable blocks: `toolbar`, `menu`, `panel`, and `head`. The toolbar template typically only overrides the `toolbar` block. The `menu` and `panel` blocks are covered in [`04-profiler-panel-template.md`](04-profiler-panel-template.md).

### 2. The `toolbar` Block Structure

```twig
{% block toolbar %}
    {% set icon %}...{% endset %}
    {% set text %}...{% endset %}
    {{ include('@WebProfiler/Profiler/toolbar_item.html.twig', { link: profiler_url }) }}
{% endblock %}
```

The `toolbar` block follows a strict three-part pattern:

1. **`{% set icon %}`** -- Content displayed directly in the toolbar bar
2. **`{% set text %}`** -- Content displayed in the hover tooltip panel
3. **`{{ include(...toolbar_item...) }}`** -- Renders both parts into the toolbar HTML

These three parts must appear in this exact order. The `icon` and `text` variables are consumed by the `toolbar_item.html.twig` include.

### 3. The `icon` Block

```twig
{% set icon %}
    {{ include('@WebProfiler/Icon/twig.svg') }}
    <span class="sf-toolbar-value">{{ collector.renderCount }}</span>
    <span class="sf-toolbar-label">renders</span>
{% endset %}
```

The icon block has three typical elements:

**SVG Icon**: An inline SVG from the WebProfiler icon set. Available icons include:

| Icon Path                         | Visual         | Use For                    |
|-----------------------------------|----------------|----------------------------|
| `@WebProfiler/Icon/twig.svg`      | Template icon  | Template/rendering metrics |
| `@WebProfiler/Icon/event.svg`     | Lightning bolt | Events, dispatches         |
| `@WebProfiler/Icon/time.svg`      | Stopwatch      | Timing, performance        |
| `@WebProfiler/Icon/memory.svg`    | Chip           | Memory usage               |
| `@WebProfiler/Icon/logger.svg`    | Document       | Logging, messages          |
| `@WebProfiler/Icon/request.svg`   | Arrow          | HTTP, requests             |
| `@WebProfiler/Icon/cache.svg`     | Database       | Cache hits/misses          |
| `@WebProfiler/Icon/messenger.svg` | Envelope       | Message bus, queues        |
| `@WebProfiler/Icon/form.svg`      | Input fields   | Form submissions           |
| `@WebProfiler/Icon/validator.svg` | Checkmark      | Validation                 |
| `@WebProfiler/Icon/exception.svg` | Warning        | Errors, exceptions         |
| `@WebProfiler/Icon/router.svg`    | Signpost       | Routing                    |

**`sf-toolbar-value`**: The primary metric value displayed prominently next to the icon. Keep this short -- a single number or brief text.

**`sf-toolbar-label`**: An optional label after the value providing context (e.g., "renders", "ms", "MB").

### 4. The `text` Block (Hover Panel)

```twig
{% set text %}
    <div class="sf-toolbar-info-piece">
        <b>Render Count</b>
        <span>{{ collector.renderCount }}</span>
    </div>
{% endset %}
```

The hover panel uses `sf-toolbar-info-piece` divs, each containing:

- **`<b>`**: The metric label (left column)
- **`<span>`**: The metric value (right column)

Multiple `sf-toolbar-info-piece` divs stack vertically to form a table-like layout. This is the standard pattern used by all built-in Symfony collectors.

### 5. Status Color Classes

```twig
<span class="sf-toolbar-status {{ collector.renderCount > 50
    ? 'sf-toolbar-status-red'
    : (collector.renderCount > 20
        ? 'sf-toolbar-status-yellow'
        : '') }}">
    {{ collector.renderCount }}
</span>
```

Status colors draw attention to metric values based on thresholds:

| Class                      | Color        | Meaning        | Use When                                |
|----------------------------|--------------|----------------|-----------------------------------------|
| _(none / default)_         | Green        | Normal         | Metrics within acceptable range         |
| `sf-toolbar-status-yellow` | Yellow/Amber | Warning        | Metrics approaching problematic levels  |
| `sf-toolbar-status-red`    | Red          | Error/Critical | Metrics exceeding acceptable thresholds |

Apply these classes to `<span class="sf-toolbar-status ...">` elements. The color appears as a background behind the value, making it stand out visually in both the toolbar icon area and the hover panel.

**Threshold guidelines**:

- Choose thresholds meaningful for the specific metric
- Default (green) should represent the common healthy case
- Yellow indicates "worth investigating"
- Red indicates "likely a problem that needs attention"

### 6. The `toolbar_item.html.twig` Include and `link` Parameter

```twig
{{ include('@WebProfiler/Profiler/toolbar_item.html.twig', {
    link: profiler_url
}) }}
```

This include renders the final toolbar HTML by combining the `icon` and `text` variables. The `link` parameter controls whether the toolbar icon is clickable:

| `link` Value   | Behavior                                                 |
|----------------|----------------------------------------------------------|
| `profiler_url` | Icon links to the profiler panel for this collector      |
| `true`         | Same as `profiler_url` -- links to the collector's panel |
| `false`        | Icon is not clickable (no `<a>` tag rendered)            |
| _(omitted)_    | Icon links to the profiler panel (default behavior)      |

**How linking works**: When `link` is truthy, the `toolbar_item.html.twig` template wraps the icon in an anchor tag pointing to `{{ url('_profiler', {token: token, panel: name}) }}`, where `name` is the value returned by `getName()` (e.g., `app.template_render`). Clicking the toolbar icon opens the profiler panel for this collector, showing the detailed view defined in the `panel` block.

The `profiler_url` variable is automatically available in the template context and contains the URL to the current request's profiler panel for this collector.

### 7. Conditional Toolbar Visibility

To hide the toolbar icon when there is no relevant data:

```twig
{% block toolbar %}
    {% if collector.renderCount > 0 %}
        {% set icon %}...{% endset %}
        {% set text %}...{% endset %}
        {{ include('@WebProfiler/Profiler/toolbar_item.html.twig', {
            link: profiler_url
        }) }}
    {% endif %}
{% endblock %}
```

When the entire `toolbar` block produces no output, the toolbar item is not rendered. This keeps the toolbar clean when a collector has nothing to report for a particular request.

### 8. Accessing Collector Data

In the template, the `collector` variable refers to the data collector instance. Access data through the typed getter methods:

```twig
{# Calls TemplateRenderDataCollector::getRenderCount() #}
{{ collector.renderCount }}

{# Calls TemplateRenderDataCollector::getTemplates() #}
{% for template in collector.templates %}
    {{ template }}
{% endfor %}
```

Twig automatically resolves `collector.renderCount` to `getRenderCount()` following the standard Twig property access convention. Always use getter methods -- never access `collector.data` directly.

## Validation Checklist

- [ ] Template extends `@WebProfiler/Profiler/layout.html.twig`
- [ ] `toolbar` block defined with `{% set icon %}` and `{% set text %}` pattern
- [ ] SVG icon included from `@WebProfiler/Icon/{name}.svg`
- [ ] `sf-toolbar-value` used for the primary metric
- [ ] `sf-toolbar-info-piece` divs used in the hover panel
- [ ] Status color classes applied based on meaningful thresholds
- [ ] `toolbar_item.html.twig` included with `link: profiler_url`
- [ ] Empty state handled (zero data case)
- [ ] Data accessed through typed getter methods (not raw `data`)
- [ ] Template path matches `static getTemplate()` return value

## Related Examples

- [`01-basic-data-collector.md`](01-basic-data-collector.md) - The PHP collector class with getters
- [`02-service-registration.md`](02-service-registration.md) - Registering the collector and linking the template
- [`04-profiler-panel-template.md`](04-profiler-panel-template.md) - `menu` and `panel` blocks for the full profiler page
- [`09-conditional-collection.md`](09-conditional-collection.md) - Conditional toolbar visibility and threshold patterns
