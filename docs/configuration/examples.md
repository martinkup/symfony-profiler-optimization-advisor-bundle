# Configuration Examples

[Docs Hub](../README.md) / [Configuration](index.md) / **Examples**

Practical YAML configuration scenarios. All examples go in `config/packages/optimization_advisor.yaml`.

## Stricter Thresholds

Flag queries above 15 ms, suspect N+1 at 5 repetitions, and slow listeners at 5 ms:

```yaml
optimization_advisor:
    thresholds:
        slow_query_ms: 15.0
        n_plus_one_count: 5
        slow_listener_ms: 5.0
```

## Custom Namespace

For a project using `Acme\` as the root namespace:

```yaml
optimization_advisor:
    app_namespace_prefix: 'Acme\'
```

## Additional Infrastructure Tables

Exclude your own migration or config tables from app signal analysis:

```yaml
optimization_advisor:
    infra_db_tables:
        - doctrine_migration_versions
        - app_migrations
        - schema_version
```

## Multi-Tenant Cache Pools

Classify additional cache pools as app pools:

```yaml
optimization_advisor:
    app_cache_pool_prefixes:
        - cache.app
        - cache.doctrine.result
        - cache.doctrine.orm
        - cache.tenant
        - cache.api_response
```

## Custom Profiler Exclusions

Exclude additional profiler-related templates and listeners:

```yaml
optimization_advisor:
    profiler_template_prefixes:
        - '@WebProfiler/'
        - '@Debug/'

    profiler_event_namespace_prefixes:
        - 'Symfony\Bundle\WebProfilerBundle\'
        - 'Symfony\Bundle\DebugBundle\'

    profiler_event_classes:
        - 'Symfony\Component\HttpKernel\EventListener\ProfilerListener'
        - 'Symfony\Component\HttpKernel\EventListener\DumpListener'
```

## Custom Security Patterns

Add phone number regex and custom parameter patterns for MCP output redaction:

```yaml
optimization_advisor:
    sensitive_param_patterns:
        - email
        - password
        - passwd
        - token
        - secret
        - auth
        - credential
        - phone
        - address
        - ssn
        - card
        - iban
        - national_id
        - birth_date

    sensitive_value_patterns:
        - '[^@\s]+@[^@\s]+\.[^@\s]+'     # email addresses
        - '\d{3}-\d{2}-\d{4}'             # US SSN format
        - '\+?\d[\d\s\-]{8,}'             # phone numbers

    sensitive_query_params:
        - token
        - api_key
        - apikey
        - secret
        - password
        - auth
        - access_token
        - refresh_token
        - session_id
        - _token
        - email
        - session
        - cookie
        - x-api-key
```

> **Important:** Setting any array replaces defaults entirely. Always include the default values you still need.

## Disabling Redaction

> **Warning:** Only disable redaction in isolated local development environments. Never disable in shared or team environments where profiler data may contain real user data.

```yaml
optimization_advisor:
    redact_sensitive_data: false
```

## Environment Notes

The bundle should only be registered in `dev` and `test` environments:

```php
// config/bundles.php
return [
    // ...
    MartinKup\OptimizationAdvisorBundle\OptimizationAdvisorBundle::class => ['dev' => true, 'test' => true],
];
```

If accidentally loaded in `prod`, the bundle emits an `E_USER_WARNING`:

```
Using OptimizationAdvisorBundle in production is not supported and puts your project at risk, disable it.
```

---

[&larr; Configuration Reference](index.md) | [Next: Installation &rarr;](../integration/index.md)
