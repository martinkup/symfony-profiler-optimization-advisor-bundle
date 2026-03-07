# Version Entry

## Overview

When it is time to release, the content from the `## [Unreleased]` section is promoted to a new version entry. This example shows the step-by-step process: choosing the version number, adding the date, updating comparison links, and leaving Unreleased empty for the next cycle.

## When to Use

- **Release Preparation**: Cutting a new version from accumulated Unreleased changes
- **Version Bump**: Deciding between MAJOR, MINOR, or PATCH based on change types
- **Post-Release Cleanup**: Ensuring Unreleased section and comparison links are updated

## Implementation

### Before Release

```markdown
## [Unreleased]

### Added

- Bulk import endpoint for processing CSV uploads
- Webhook notification system for order status changes

### Changed

- Increased default connection pool size from 5 to 10

### Fixed

- Fixed memory leak in long-running queue worker
- Corrected timezone handling in scheduled reports

## [1.2.0] - 2024-06-15

### Added

- Previous release content...

[Unreleased]: https://github.com/acme/order-service/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/acme/order-service/compare/v1.1.0...v1.2.0
```

### After Release (v1.3.0)

```markdown
## [Unreleased]

## [1.3.0] - 2024-08-20

### Added

- Bulk import endpoint for processing CSV uploads
- Webhook notification system for order status changes

### Changed

- Increased default connection pool size from 5 to 10

### Fixed

- Fixed memory leak in long-running queue worker
- Corrected timezone handling in scheduled reports

## [1.2.0] - 2024-06-15

### Added

- Previous release content...

[Unreleased]: https://github.com/acme/order-service/compare/v1.3.0...HEAD
[1.3.0]: https://github.com/acme/order-service/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/acme/order-service/compare/v1.1.0...v1.2.0
```

## Key Elements Explained

### 1. Step-by-Step Release Process

1. **Determine version number** based on changes (see table below)
2. **Replace** `## [Unreleased]` content with `## [X.Y.Z] - YYYY-MM-DD`
3. **Add empty** `## [Unreleased]` section above the new version
4. **Add comparison link** for the new version
5. **Update** `[Unreleased]` link to compare from the new tag

### 2. Version Number Selection

| Change Types Present           | Version Bump | Example         |
|--------------------------------|--------------|-----------------|
| `Removed` or breaking `Changed`| MAJOR        | `1.2.0` -> `2.0.0` |
| `Added` or non-breaking `Changed` | MINOR    | `1.2.0` -> `1.3.0` |
| `Fixed` or `Security` only    | PATCH        | `1.2.0` -> `1.2.1` |
| `Deprecated` only             | MINOR        | `1.2.0` -> `1.3.0` |

### 3. Version Heading Format

```markdown
## [1.3.0] - 2024-08-20
```

- Version in square brackets: `[1.3.0]`
- Space-hyphen-space separator: ` - `
- ISO 8601 date: `2024-08-20`
- The date is the day the version is released, not when development started

### 4. Comparison Link Updates

Two links must be updated on every release:

```markdown
<!-- Update [Unreleased] to compare from new tag -->
[Unreleased]: https://github.com/acme/order-service/compare/v1.3.0...HEAD

<!-- Add new version link comparing to previous -->
[1.3.0]: https://github.com/acme/order-service/compare/v1.2.0...v1.3.0
```

### 5. Pre-Release Versions

For alpha, beta, or release candidate versions:

```markdown
## [2.0.0-beta.1] - 2024-09-01

### Added

- New authentication system (breaking change from v1)

[2.0.0-beta.1]: https://github.com/acme/order-service/compare/v1.3.0...v2.0.0-beta.1
```

Pre-release versions follow SemVer pre-release conventions and are valid changelog entries.

## Validation Checklist

- [ ] Version number follows SemVer based on change types
- [ ] Date is today's date in ISO 8601 format
- [ ] Format: `## [X.Y.Z] - YYYY-MM-DD`
- [ ] Empty `## [Unreleased]` section added above new version
- [ ] All Unreleased content moved to the new version (nothing left behind)
- [ ] New comparison link added for this version
- [ ] `[Unreleased]` link updated to compare from new tag
- [ ] Change types preserved in standard order
- [ ] No new entries added -- only promotion of existing Unreleased content

## Related Examples

- [`02-unreleased-section.md`](02-unreleased-section.md) - Accumulating changes before release
- [`04-change-types.md`](04-change-types.md) - Understanding change types for version bump decisions
- [`06-comparison-links.md`](06-comparison-links.md) - Detailed comparison link management
