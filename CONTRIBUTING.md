# Contributing

Contributions are welcome! Please follow these guidelines.

## Requirements

- PHP >= 8.3
- Composer

## Setup

```bash
git clone https://github.com/martinkup/symfony-profiler-optimization-advisor-bundle.git
cd symfony-profiler-optimization-advisor-bundle
composer install
```

## Quality Gates

Before submitting a PR, ensure all quality gates pass:

1. Auto-fix coding style: `composer fix`
2. Coding standards pass: `composer cs`
3. Static analysis passes: `composer stan`
4. All tests pass: `composer test`
5. Add tests for new detection rules or analyzers

Or simply run `composer check` to execute all gates at once.

## Coding Standards

- PSR-12 base + Slevomat Coding Standard (see `phpcs.xml.dist`)
- `declare(strict_types=1)` required in all PHP files
- PHPStan level max — no `@phpstan-ignore` annotations allowed
- Test methods use `test` prefix naming convention

## Pull Requests

- Keep PRs focused on a single change
- Include tests for new features or bug fixes
- Update documentation if behavior changes
- Follow the existing code style and conventions
