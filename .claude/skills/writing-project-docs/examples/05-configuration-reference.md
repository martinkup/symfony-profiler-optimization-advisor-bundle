[Home](../README.md) > [Configuration](index.md) > **Configuration Reference**

---

# Configuration Reference

## Parameter Overview

```mermaid
pie title Parameters by Category
    "Thresholds": {N}
    "Classification": {N}
    "Filtering": {N}
    "Display": {N}
```

**Total: {N} configurable parameters**

## Full Configuration Tree

```yaml
{bundle_name}:
    # {Category 1}
    {param1}: {default}              # {Brief description}
    {param2}: {default}              # {Brief description}

    # {Category 2}
    {param3}: '{default}'            # {Brief description}
    {param4}:                        # {Brief description}
        - '{value1}'
        - '{value2}'

    # {Category 3}
    {param5}: {default}              # {Brief description}
```

## Parameter Catalog

### {Category 1}

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `{param1}` | `{type}` | `{default}` | {Detailed description of what this parameter controls} |
| `{param2}` | `{type}` | `{default}` | {Detailed description of what this parameter controls} |

### {Category 2}

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `{param3}` | `{type}` | `{default}` | {Detailed description of what this parameter controls} |
| `{param4}` | `{type}` | `{default}` | {Detailed description of what this parameter controls} |

### {Category 3}

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `{param5}` | `{type}` | `{default}` | {Detailed description of what this parameter controls} |

## Common Scenarios

### {Scenario 1: e.g., Stricter Thresholds}

{Brief description of the use case.}

```yaml
{bundle_name}:
    {param1}: {value}
    {param2}: {value}
```

### {Scenario 2: e.g., Custom Namespace}

{Brief description of the use case.}

```yaml
{bundle_name}:
    {param3}: '{value}'
```

### {Scenario 3: e.g., Filtering Infrastructure}

{Brief description of the use case.}

```yaml
{bundle_name}:
    {param4}:
        - '{value1}'
        - '{value2}'
        - '{value3}'
```

## Environment Notes

{Notes about which environments the bundle is active in (e.g., dev/test only), and any environment-specific considerations.}

---

## In This Section

| Document | Description |
|----------|-------------|
| **Configuration Reference** | All parameters with types and defaults (this page) |
| [Examples](examples.md) | Common configuration scenarios |

---

## Navigation

| Previous | Up | Next |
|:---------|:--:|-----:|
| [{Previous Section}](../{prev}/index.md) | [Home](../README.md) | [{Next Section}](../{next}/index.md) |
