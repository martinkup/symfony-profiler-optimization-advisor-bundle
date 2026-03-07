# Coding Standards

[Docs Hub](../README.md) / [Development](index.md) / **Coding Standards**

## Base Standard

PSR-12 + [Slevomat Coding Standard](https://github.com/slevomat/coding-standard) v8.4 with project-specific exclusions defined in `phpcs.xml.dist`.

## Key Rules

| Rule                      | Requirement                                                                                                                        |
|---------------------------|------------------------------------------------------------------------------------------------------------------------------------|
| `declare(strict_types=1)` | Required in all PHP files                                                                                                          |
| Line length               | 120 characters max (comments and imports excluded)                                                                                 |
| Class member ordering     | uses &rarr; enum cases &rarr; constants &rarr; properties &rarr; constructor &rarr; public &rarr; protected &rarr; private methods |
| Trailing commas           | Required on multi-line, forbidden on single-line calls/declarations                                                                |
| Imports                   | Required (no FQCNs inline), unused imports forbidden                                                                               |
| Test method naming        | `test` prefix required; snake_case allowed only in test files                                                                      |

### Forbidden Functions

The following functions are banned from production code:

- `var_dump`
- `print_r`
- `die`
- `exit`
- `eval`
- `phpinfo`
- `is_null` (use `=== null` instead)

## PHPStan

- **Level:** max (the highest available)
- **No `@phpstan-ignore`** inline comments allowed
- **No `ignoreErrors`** in `phpstan.neon.dist`

All code must be written with proper types so PHPStan passes naturally. If PHPStan reports an error, fix the code — do not add suppression annotations.

### PHPStan Extensions

| Extension                           | Purpose                |
|-------------------------------------|------------------------|
| `phpstan/phpstan-strict-rules`      | Stricter type checking |
| `phpstan/phpstan-phpunit`           | PHPUnit-aware analysis |
| `phpstan/phpstan-deprecation-rules` | Deprecation detection  |

## Running Checks

```bash
# Check coding standards
composer cs

# Auto-fix violations
composer fix

# Static analysis
composer stan

# Everything at once
composer check
```

## Editor Configuration

The project includes `.editorconfig` for consistent editor settings:

- UTF-8 encoding
- LF line endings
- 4-space indentation for PHP
- Final newline required

---

[&larr; Development Guide](index.md)
