# Installation & Setup

[Docs Hub](../README.md) / **Integration**

## Install

```bash
composer require --dev martinkup/symfony-profiler-optimization-advisor-bundle
```

## Bundle Registration

### Symfony Flex (automatic)

If you use [Symfony Flex](https://github.com/symfony/flex), the bundle is registered automatically.

### Manual Registration

```php
// config/bundles.php
return [
    // ...
    MartinKup\OptimizationAdvisorBundle\OptimizationAdvisorBundle::class => ['dev' => true, 'test' => true],
];
```

## Environment

Register the bundle **only in `dev` and `test`** environments. It depends on debug-only services (`twig.profile`, `data_collector.cache`) that are not available in production.

If accidentally loaded in `prod`, the bundle emits:

```
E_USER_WARNING: Using OptimizationAdvisorBundle in production is not supported and puts your project at risk, disable it.
```

## Quick Verification

1. Install the bundle
2. Open your application in the browser
3. Click the Symfony Profiler toolbar
4. Look for the "Optimization Advisor" panel
5. The panel shows opportunity count, optimization score, and signal breakdown

## Section Contents

| Document                                  | Description                                              |
|-------------------------------------------|----------------------------------------------------------|
| [Optional Dependencies](optional-deps.md) | What each optional package enables and fallback behavior |
| [Extending](extending.md)                 | Extension points, consuming output, AI Mate integration  |

---

[&larr; Configuration Examples](../configuration/examples.md) | [Next: Optional Dependencies &rarr;](optional-deps.md)
