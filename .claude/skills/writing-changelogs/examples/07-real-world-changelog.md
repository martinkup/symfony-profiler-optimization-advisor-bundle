# Real-World Changelog

## Overview

This example presents a complete, realistic changelog spanning multiple versions with all patterns: initial release, multiple change types, deprecation-then-removal lifecycle, a yanked version, pre-release versions, and comparison links. Use this as a comprehensive reference.

## When to Use

- **Full Reference**: When you need to see all patterns together in one file
- **Template**: Starting point for a new project's changelog
- **Review Benchmark**: Comparing an existing changelog against a well-structured example

## Implementation

### Complete Changelog

```markdown
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Batch processing endpoint for bulk order updates (`POST /api/v2/orders/batch`)

## [2.0.1] - 2024-10-05

### Security

- Updated `symfony/http-kernel` to 7.1.5 to address CVE-2024-50340 (request parameter injection)

## [2.0.0] - 2024-09-01

### Added

- GraphQL API alongside REST endpoints
- Role-based access control with `admin`, `editor`, and `viewer` roles
- Database migration command (`bin/console app:migrate`)
- OpenTelemetry tracing integration

### Changed

- **BREAKING**: Authentication requires OAuth 2.0 tokens instead of API keys
- **BREAKING**: Minimum PHP version raised from 8.2 to 8.3
- Default response format changed from XML to JSON
- Order status transitions now enforce state machine rules

### Removed

- XML response format (deprecated in 1.5.0)
- PHP 8.1 and 8.2 support (deprecated in 1.6.0)
- `GET /api/v1/legacy-search` endpoint (deprecated in 1.4.0)
- `Config::getLegacyOption()` method (deprecated in 1.5.0)

## [2.0.0-rc.1] - 2024-08-15

### Added

- Release candidate for 2.0.0 -- all features from 2.0.0-beta.1 plus:
- OpenTelemetry tracing integration

### Fixed

- Fixed OAuth token refresh failing silently when refresh token expired

## [2.0.0-beta.1] - 2024-08-01

### Added

- GraphQL API alongside REST endpoints
- Role-based access control with `admin`, `editor`, and `viewer` roles

### Changed

- **BREAKING**: Authentication requires OAuth 2.0 tokens instead of API keys

## [1.6.0] - 2024-07-15

### Added

- Health check endpoint at `GET /healthz`
- Structured logging with JSON output format

### Deprecated

- PHP 8.1 and 8.2 support (removal in v2.0.0)

### Fixed

- Fixed race condition in concurrent order processing
- Corrected decimal precision loss in currency calculations

## [1.5.1] - 2024-07-02

### Security

- Fixed SQL injection vulnerability in search query builder (reported by security audit)
- Updated `guzzlehttp/guzzle` to 7.8.1 to address CVE-2024-XXXX (SSRF)

## [1.5.0] - 2024-06-20 [YANKED]

### Added

- Full-text search endpoint with SQL-based query builder
- Search result caching with configurable TTL

### Deprecated

- XML response format (removal in v2.0.0, use JSON instead)
- `Config::getLegacyOption()` method (use `Config::getOption()` instead, removal in v2.0.0)

## [1.4.0] - 2024-05-10

### Added

- Webhook notification system for order status changes
- Retry mechanism for failed webhook deliveries (exponential backoff)

### Changed

- Increased default connection pool size from 5 to 10

### Deprecated

- `GET /api/v1/legacy-search` endpoint (use `GET /api/v1/search` instead, removal in v2.0.0)

### Fixed

- Fixed `Content-Type` header missing on 404 error responses

## [1.3.0] - 2024-03-20

### Added

- Pagination support for all list endpoints with `page` and `per_page` parameters
- `X-Total-Count` response header for paginated responses
- Rate limiting middleware with configurable thresholds per API key

### Fixed

- Fixed order total calculation ignoring discount codes

## [1.2.0] - 2024-02-15

### Added

- CSV export for order reports (`GET /api/v1/orders/export`)
- Configurable date range filters on reporting endpoints

### Changed

- Upgraded `doctrine/orm` from 2.x to 3.0
- Renamed `OrderRepository::findByStatus()` to `OrderRepository::findAllByStatus()`

## [1.1.0] - 2024-01-25

### Added

- Order item notes field (max 500 characters)
- Soft delete support for orders (archived instead of deleted)

### Fixed

- Fixed timezone handling in scheduled report generation
- Corrected HTTP 500 when request body exceeds configured limit

## [1.0.0] - 2024-01-10

### Added

- REST API for order management (`GET`, `POST`, `PUT`, `DELETE`)
- Core domain model with `Order`, `OrderItem`, and `Customer` entities
- PostgreSQL persistence with Doctrine ORM
- JWT authentication for API access
- PHPUnit test suite with unit and integration tests
- CI pipeline with GitHub Actions (lint, test, static analysis)
- Docker Compose development environment
- API documentation with OpenAPI 3.1 specification

[Unreleased]: https://github.com/acme/order-service/compare/v2.0.1...HEAD
[2.0.1]: https://github.com/acme/order-service/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/acme/order-service/compare/v2.0.0-rc.1...v2.0.0
[2.0.0-rc.1]: https://github.com/acme/order-service/compare/v2.0.0-beta.1...v2.0.0-rc.1
[2.0.0-beta.1]: https://github.com/acme/order-service/compare/v1.6.0...v2.0.0-beta.1
[1.6.0]: https://github.com/acme/order-service/compare/v1.5.1...v1.6.0
[1.5.1]: https://github.com/acme/order-service/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/acme/order-service/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/acme/order-service/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/acme/order-service/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/acme/order-service/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/acme/order-service/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/acme/order-service/releases/tag/v1.0.0
```

## Key Elements Explained

### 1. Deprecation-then-Removal Lifecycle

This changelog demonstrates the full lifecycle for three items:

| Feature                      | Deprecated In | Removed In |
|------------------------------|---------------|------------|
| XML response format          | 1.5.0         | 2.0.0      |
| `Config::getLegacyOption()`  | 1.5.0         | 2.0.0      |
| `GET /api/v1/legacy-search`  | 1.4.0         | 2.0.0      |

The pattern:
1. **Deprecate** in a MINOR version with migration path and planned removal version
2. **Remove** in the next MAJOR version, referencing when it was deprecated

### 2. Yanked Version (1.5.0)

Version 1.5.0 introduced a SQL injection vulnerability in the search feature. It was yanked, and 1.5.1 was released with the security fix. The yanked version retains its content and comparison links.

### 3. Pre-Release Versions

The 2.0.0 release went through a beta and release candidate:
- `2.0.0-beta.1` -- initial breaking changes for testing
- `2.0.0-rc.1` -- feature-complete candidate
- `2.0.0` -- final release

Each has its own entry and comparison link, forming a chain.

### 4. Security Patches

Two patterns for security releases:
- **1.5.1**: Emergency patch for a vulnerability in the yanked 1.5.0
- **2.0.1**: Dependency update to address an upstream CVE

Both use `### Security` and include CVE references where available.

### 5. Breaking Changes

Breaking changes in 2.0.0 are marked with `**BREAKING**:` prefix under `### Changed`. The corresponding removals are under `### Removed` with references to when they were deprecated.

### 6. Comparison Link Chain

The links form an unbroken chain from the first version to Unreleased:
```
1.0.0 -> 1.1.0 -> 1.2.0 -> 1.3.0 -> 1.4.0 -> 1.5.0 -> 1.5.1 -> 1.6.0
  -> 2.0.0-beta.1 -> 2.0.0-rc.1 -> 2.0.0 -> 2.0.1 -> Unreleased
```

Note that the yanked 1.5.0 remains in the chain -- 1.5.1 compares against 1.5.0.

## Validation Checklist

- [ ] All versions in reverse chronological order
- [ ] Every version has a date in ISO 8601 format
- [ ] Unreleased section present (even with entries)
- [ ] Change types in standard order within each version
- [ ] Breaking changes marked with `**BREAKING**:` prefix
- [ ] Deprecated entries include replacement and removal version
- [ ] Removed entries reference deprecation version
- [ ] Yanked version marked with `[YANKED]`, content preserved
- [ ] Pre-release versions follow SemVer pre-release format
- [ ] Security entries reference CVEs where available
- [ ] Comparison links form an unbroken chain
- [ ] First version links to release tag
- [ ] `[Unreleased]` link points to latest tag...HEAD

## Related Examples

- [`01-initial-changelog.md`](01-initial-changelog.md) - How this changelog started
- [`04-change-types.md`](04-change-types.md) - Detailed guide for each change type used here
- [`05-yanked-releases.md`](05-yanked-releases.md) - The yanked 1.5.0 pattern explained
- [`06-comparison-links.md`](06-comparison-links.md) - How the comparison link chain works
