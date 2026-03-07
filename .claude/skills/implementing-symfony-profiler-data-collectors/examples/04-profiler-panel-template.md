# Profiler Panel Template

A profiler panel template provides the full-page view displayed when clicking a data collector entry in the Symfony Profiler. It uses four Twig blocks to control the toolbar icon, sidebar menu, main panel content, and optional custom styles. This example demonstrates a rich panel with summary metrics, tabular data, tabbed content using `sf-tabs`, empty state handling, and `profiler_dump()` for inspecting `Data` objects produced by `cloneVar()`.

## When to Use

- Displaying detailed metrics and tabular data for a custom data collector
- Organizing collected data into multiple tabs within a single panel
- Showing summary metrics at the top of the panel for quick scanning
- Rendering complex objects captured via `cloneVar()` with `profiler_dump()`
- Adding custom CSS styles scoped to the profiler panel

## Implementation

```twig
{# templates/data_collector/gateway_metrics.html.twig #}
{% extends '@WebProfiler/Profiler/layout.html.twig' %}

{% block head %}
    {{ parent() }}
    <style>
        .gateway-status-ok { color: var(--color-success); }
        .gateway-status-error { color: var(--color-error); font-weight: bold; }
        .gateway-duration-slow { color: var(--color-warning); }
        .gateway-metrics-summary {
            display: flex;
            gap: 2em;
            margin-bottom: 1em;
        }
    </style>
{% endblock %}

{% block toolbar %}
    {% set icon %}
        {{ include('@WebProfiler/Icon/event.svg') }}
        <span class="sf-toolbar-value">{{ collector.totalCalls }}</span>
        <span class="sf-toolbar-info-piece-additional-detail">
            <span class="sf-toolbar-label">in</span>
            <span class="sf-toolbar-value">{{ '%0.2f'|format(collector.totalDuration) }}</span>
            <span class="sf-toolbar-label">ms</span>
        </span>
    {% endset %}

    {% set text %}
        <div class="sf-toolbar-info-piece">
            <b>Gateway Calls</b>
            <span>{{ collector.totalCalls }}</span>
        </div>
        <div class="sf-toolbar-info-piece">
            <b>Total Duration</b>
            <span>{{ '%0.2f'|format(collector.totalDuration) }} ms</span>
        </div>
        <div class="sf-toolbar-info-piece">
            <b>Errors</b>
            <span class="{{ collector.errorCount > 0 ? 'sf-toolbar-status-red' }}">
                {{ collector.errorCount }}
            </span>
        </div>
    {% endset %}

    {% set status_color = collector.errorCount > 0 ? 'red' : '' %}

    {{ include('@WebProfiler/Profiler/toolbar_item.html.twig', {
        link: profiler_url,
        status: status_color,
    }) }}
{% endblock %}

{% block menu %}
    <span class="label {{ collector.totalCalls == 0 ? 'disabled' }}">
        <span class="icon">{{ include('@WebProfiler/Icon/event.svg') }}</span>
        <strong>Gateways</strong>
    </span>
{% endblock %}

{% block panel %}
    <h2>Gateway Metrics</h2>

    {% if collector.totalCalls == 0 %}
        <div class="empty">
            <p>No gateway calls were made during this request.</p>
        </div>
    {% else %}
        {# Summary metrics row #}
        <div class="metrics">
            <div class="metric">
                <span class="value">{{ collector.totalCalls }}</span>
                <span class="label">Total Calls</span>
            </div>
            <div class="metric">
                <span class="value">
                    {{ '%0.2f'|format(collector.totalDuration) }}
                    <span class="unit">ms</span>
                </span>
                <span class="label">Total Duration</span>
            </div>

            <div class="metric-divider"></div>

            <div class="metric-group">
                <div class="metric">
                    <span class="value">{{ collector.successCount }}</span>
                    <span class="label">Successes</span>
                </div>
                <div class="metric">
                    <span class="value">{{ collector.errorCount }}</span>
                    <span class="label">Errors</span>
                </div>
                <div class="metric">
                    <span class="value">
                        {{ '%0.2f'|format(collector.averageDuration) }}
                        <span class="unit">ms</span>
                    </span>
                    <span class="label">Avg Duration</span>
                </div>
            </div>
        </div>

        {# Tabbed content using sf-tabs #}
        <div class="sf-tabs">
            <div class="tab">
                <h3 class="tab-title">
                    All Calls <span class="badge">{{ collector.calls|length }}</span>
                </h3>
                <div class="tab-content">
                    <table>
                        <thead>
                            <tr>
                                <th class="text-right">#</th>
                                <th>Gateway</th>
                                <th>Operation</th>
                                <th class="text-right">Duration</th>
                                <th>Status</th>
                                <th>Request</th>
                            </tr>
                        </thead>
                        <tbody>
                            {% for call in collector.calls %}
                                <tr>
                                    <td class="text-right text-muted text-small">
                                        {{ loop.index }}
                                    </td>
                                    <td class="font-normal">{{ call.gateway }}</td>
                                    <td class="font-normal">{{ call.operation }}</td>
                                    <td class="text-right nowrap {{ call.duration > 100 ? 'gateway-duration-slow' }}">
                                        {{ '%0.2f'|format(call.duration) }} ms
                                    </td>
                                    <td>
                                        <span class="{{ call.success ? 'gateway-status-ok' : 'gateway-status-error' }}">
                                            {{ call.success ? 'OK' : 'ERROR' }}
                                        </span>
                                    </td>
                                    <td>{{ profiler_dump(call.requestData, maxDepth: 2) }}</td>
                                </tr>
                            {% endfor %}
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab">
                <h3 class="tab-title">
                    Errors <span class="badge">{{ collector.errorCalls|length }}</span>
                </h3>
                <div class="tab-content">
                    {% if collector.errorCalls is empty %}
                        <div class="empty">
                            <p>No errors occurred during gateway calls.</p>
                        </div>
                    {% else %}
                        <table>
                            <thead>
                                <tr>
                                    <th>Gateway</th>
                                    <th>Operation</th>
                                    <th>Error</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                {% for call in collector.errorCalls %}
                                    <tr>
                                        <td class="font-normal">{{ call.gateway }}</td>
                                        <td class="font-normal">{{ call.operation }}</td>
                                        <td class="font-normal gateway-status-error">
                                            {{ call.errorMessage }}
                                        </td>
                                        <td>{{ profiler_dump(call.errorDetails) }}</td>
                                    </tr>
                                {% endfor %}
                            </tbody>
                        </table>
                    {% endif %}
                </div>
            </div>

            <div class="tab">
                <h3 class="tab-title">
                    Slow Calls <span class="badge">{{ collector.slowCalls|length }}</span>
                </h3>
                <div class="tab-content">
                    {% if collector.slowCalls is empty %}
                        <div class="empty">
                            <p>No gateway calls exceeded the slow threshold.</p>
                        </div>
                    {% else %}
                        <table>
                            <thead>
                                <tr>
                                    <th>Gateway</th>
                                    <th>Operation</th>
                                    <th class="text-right">Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                {% for call in collector.slowCalls %}
                                    <tr>
                                        <td class="font-normal">{{ call.gateway }}</td>
                                        <td class="font-normal">{{ call.operation }}</td>
                                        <td class="text-right nowrap gateway-duration-slow">
                                            {{ '%0.2f'|format(call.duration) }} ms
                                        </td>
                                    </tr>
                                {% endfor %}
                            </tbody>
                        </table>
                    {% endif %}
                </div>
            </div>
        </div>
    {% endif %}
{% endblock %}
```

## Key Elements Explained

### Block Structure

The profiler template extends `@WebProfiler/Profiler/layout.html.twig` and defines four blocks:

| Block     | Purpose                                                                  | Required                               |
|-----------|--------------------------------------------------------------------------|----------------------------------------|
| `toolbar` | Icon and hover panel in the Web Debug Toolbar at the bottom of the page  | No (omit to hide from toolbar)         |
| `menu`    | Entry in the profiler left sidebar with icon and label                   | Yes (required for profiler navigation) |
| `panel`   | Main content area displayed when the collector is selected               | Yes (the actual profiler page content) |
| `head`    | Custom CSS or JavaScript injected into the `<head>` of the profiler page | No (only when custom styles needed)    |

### head Block

The `head` block adds custom CSS scoped to the profiler panel. Always call `{{ parent() }}` first to preserve the base profiler styles. Use CSS custom properties from the profiler theme (e.g., `var(--color-success)`, `var(--color-error)`, `var(--color-warning)`, `var(--table-background)`, `var(--shadow)`) for consistency with the profiler's light and dark modes.

### menu Block

The `menu` block defines the entry shown in the profiler's left sidebar. It must contain a `<span class="label">` with an icon and a `<strong>` label. Add the `disabled` class when no data was collected to gray out the entry:

```twig
<span class="label {{ collector.totalCalls == 0 ? 'disabled' }}">
```

Icons are loaded via `{{ include('@WebProfiler/Icon/{name}.svg') }}`. Available built-in icons include `event.svg`, `cache.svg`, `router.svg`, `time.svg`, and others in `vendor/symfony/web-profiler-bundle/Resources/views/Icon/`.

### toolbar Block

The `toolbar` block defines two variables -- `icon` (the compact toolbar icon with a summary value) and `text` (the hover panel with detailed info pieces). Each info piece is a `<div class="sf-toolbar-info-piece">` containing a `<b>` label and a `<span>` value. The block ends by including `toolbar_item.html.twig` with the `link` and optional `status` parameters. Valid status colors are `yellow` and `red`.

### panel Block -- Summary Metrics

The `<div class="metrics">` container with `<div class="metric">` children renders a horizontal row of key statistics at the top of the panel. Each metric has a `<span class="value">` and a `<span class="label">`. Use `<div class="metric-divider">` to visually separate metric groups. Use `<div class="metric-group">` to cluster related metrics together.

### panel Block -- Tables

Standard `<table>` elements with `<thead>` and `<tbody>` are automatically styled by the profiler. Use CSS classes `text-right`, `text-muted`, `text-small`, `nowrap`, and `font-normal` for cell formatting. No extra Bootstrap or custom table classes are needed.

### panel Block -- sf-tabs Component

The `sf-tabs` component creates tabbed content within the panel. The HTML structure is:

```twig
<div class="sf-tabs">
    <div class="tab">                           {# first tab #}
        <h3 class="tab-title">Tab Label</h3>    {# tab header #}
        <div class="tab-content">               {# tab body #}
            {# content here #}
        </div>
    </div>
    <div class="tab">                           {# second tab #}
        <h3 class="tab-title">Tab Label</h3>
        <div class="tab-content">
            {# content here #}
        </div>
    </div>
</div>
```

Each tab is a `<div class="tab">` containing exactly one `<h3 class="tab-title">` and one `<div class="tab-content">`. The profiler JavaScript automatically converts this structure into interactive tabs. Add a `<span class="badge">` inside the `<h3>` to show a count next to the tab title. Add the `disabled` class to the tab `<div>` to gray it out.

### Empty State Handling

When no data is available for a section, wrap a `<p>` message inside `<div class="empty">`:

```twig
<div class="empty">
    <p>No data collected.</p>
</div>
```

For the top-level panel empty state (no data at all), use `<div class="empty empty-panel">`.

### profiler_dump() for Data Objects

When a collector stores complex objects via `cloneVar()`, render them in the template with `profiler_dump()`. This produces an interactive, expandable dump view identical to the VarDumper component output. Use the `maxDepth` parameter to limit nesting:

```twig
{{ profiler_dump(collector.someClonedData) }}
{{ profiler_dump(collector.someClonedData, maxDepth: 2) }}
```

## Validation Checklist

- [ ] Template extends `@WebProfiler/Profiler/layout.html.twig`
- [ ] `head` block calls `{{ parent() }}` before adding custom styles
- [ ] `head` block uses CSS custom properties for theme compatibility
- [ ] `menu` block contains `<span class="label">` with icon and `<strong>` label
- [ ] `menu` block adds `disabled` class when no data is collected
- [ ] `toolbar` block defines both `icon` and `text` variables
- [ ] `toolbar` block includes `toolbar_item.html.twig` with `link` parameter
- [ ] `panel` block includes summary metrics row at top
- [ ] `panel` block handles empty state with `<div class="empty">`
- [ ] `sf-tabs` structure uses `div.sf-tabs > div.tab > (h3.tab-title + div.tab-content)`
- [ ] `profiler_dump()` used for rendering `Data` objects from `cloneVar()`
- [ ] Icons loaded via `include('@WebProfiler/Icon/{name}.svg')`
- [ ] All lines within 120 characters

## Related Examples

- [`01-basic-data-collector.md`](01-basic-data-collector.md) -- PHP collector class that pairs with this template
- [`03-toolbar-template.md`](03-toolbar-template.md) -- Focused example of the `toolbar` block
- [`07-clone-var-serialization.md`](07-clone-var-serialization.md) -- How `cloneVar()` and `profiler_dump()` work together
