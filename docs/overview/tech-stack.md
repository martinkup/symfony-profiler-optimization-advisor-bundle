# Technology Stack

[Docs Hub](../README.md) / [Overview](index.md) / **Tech Stack**

## Required Dependencies

| Package                    | Version      | Purpose                                    |
|----------------------------|--------------|--------------------------------------------|
| `php`                      | >= 8.3       | Language runtime                           |
| `symfony/framework-bundle` | ^7.2 \| ^8.0 | Symfony framework integration              |
| `symfony/twig-bundle`      | ^7.2 \| ^8.0 | Twig template rendering for profiler panel |

## Dev Dependencies

| Package                               | Version    | Purpose                         |
|---------------------------------------|------------|---------------------------------|
| `phpunit/phpunit`                     | ^12.0      | Test framework                  |
| `phpstan/phpstan`                     | ^2.0       | Static analysis (level max)     |
| `phpstan/phpstan-strict-rules`        | ^2.0       | Strict PHPStan rules            |
| `phpstan/phpstan-phpunit`             | ^2.0       | PHPUnit-specific analysis rules |
| `phpstan/phpstan-deprecation-rules`   | ^2.0       | Deprecation detection           |
| `phpstan/extension-installer`         | ^1.4       | Auto-install PHPStan extensions |
| `squizlabs/php_codesniffer`           | ^4.0       | Coding standards checker        |
| `slevomat/coding-standard`            | ^8.4       | Extended Slevomat rules         |
| `php-parallel-lint/php-parallel-lint` | ^1.4       | PHP syntax check                |
| `roave/security-advisories`           | dev-latest | Known vulnerability detection   |

## Optional Dependencies

| Package                             | Version      | What It Enables                               | Fallback                                              |
|-------------------------------------|--------------|-----------------------------------------------|-------------------------------------------------------|
| `symfony/doctrine-bridge`           | ^7.2 \| ^8.0 | Database query analysis via `DebugDataHolder` | Empty DB section in panel                             |
| `doctrine/sql-formatter`            | ^1.1         | SQL syntax highlighting (`oi_prettify_sql`)   | `SqlFormatterFallbackExtension` with basic formatting |
| `symfony/http-client`               | ^7.2 \| ^8.0 | HTTP client call analysis                     | Empty HTTP section in panel                           |
| `symfony/stopwatch`                 | ^7.2 \| ^8.0 | Stopwatch performance breakdown               | Empty performance section                             |
| `symfony/messenger`                 | ^7.2 \| ^8.0 | Messenger sync handler analysis               | Middleware removed, empty Messenger section           |
| `symfony/ai-symfony-mate-extension` | ^0.5         | AI Mate MCP tool + collector formatter        | No MCP integration                                    |
| `symfony/cache`                     | ^7.2 \| ^8.0 | Cache pool analysis (dev dependency)          | Required at runtime via `data_collector.cache`        |

## CI Matrix

| PHP | Symfony | Composer Flags    |
|-----|---------|-------------------|
| 8.3 | 7.2.*   | *(default)*       |
| 8.3 | 7.2.*   | `--prefer-lowest` |
| 8.4 | 7.2.*   | *(default)*       |
| 8.4 | 8.0.*   | *(default)*       |

### CI Jobs

| Job      | Tool             | Description                             |
|----------|------------------|-----------------------------------------|
| lint     | `parallel-lint`  | PHP syntax check                        |
| test     | `phpunit`        | Full test suite (4 matrix combinations) |
| phpcs    | `phpcs`          | PHP_CodeSniffer + Slevomat              |
| phpstan  | `phpstan`        | Static analysis level max               |
| security | `composer audit` | Dependency vulnerability scan           |

---

[&larr; Architecture](architecture.md) | [Next: Glossary &rarr;](glossary.md)
