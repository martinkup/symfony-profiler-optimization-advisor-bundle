# Unreleased Section

## Overview

The `## [Unreleased]` section is a living area of the changelog where changes are recorded as they happen during development. When it is time to release, the Unreleased content is promoted to a new version entry. This example shows how to accumulate changes correctly.

## When to Use

- **Daily Development**: Recording features, fixes, and changes as they are merged
- **Pre-Release Accumulation**: Building up entries before cutting a release
- **PR/MR Workflow**: Adding a changelog entry as part of each pull request

## Implementation

### Unreleased with Multiple Change Types

```markdown
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Search endpoint with full-text query support (`GET /api/v1/search`)
- Rate limiting middleware with configurable thresholds
- OpenAPI 3.1 specification for all public endpoints

### Changed

- Upgraded `symfony/http-kernel` from 7.1 to 7.2
- Increased default pagination limit from 20 to 50 items

### Deprecated

- `GET /api/v1/users?filter=` query parameter (use `POST /api/v1/users/search` instead)

### Fixed

- Fixed race condition in concurrent order processing
- Corrected HTTP 500 when request body exceeds 10 MB

## [1.0.0] - 2024-01-10

### Added

- Initial release

[Unreleased]: https://github.com/acme/order-service/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/acme/order-service/releases/tag/v1.0.0
```

## Key Elements Explained

### 1. Always Present

The `## [Unreleased]` section must exist even when empty:

```markdown
## [Unreleased]

## [1.0.0] - 2024-01-10
```

An empty Unreleased section is valid. It signals that no unreleased changes exist yet.

### 2. Change Types Appear As Needed

Only add `### Added`, `### Fixed`, etc. when there are entries for that type. Do not include empty headings:

```markdown
<!-- FORBIDDEN - empty heading -->
## [Unreleased]

### Added

### Changed

### Fixed

- Fixed a bug

<!-- CORRECT - only non-empty types -->
## [Unreleased]

### Fixed

- Fixed a bug
```

### 3. Maintain Standard Order

When multiple change types are present, keep them in the standard order:

1. `### Added`
2. `### Changed`
3. `### Deprecated`
4. `### Removed`
5. `### Fixed`
6. `### Security`

### 4. Write Entries Incrementally

Add entries as changes are merged, not all at once before a release:

```markdown
<!-- Day 1: Feature merged -->
## [Unreleased]

### Added

- Search endpoint with full-text query support

<!-- Day 3: Bug fix merged -->
## [Unreleased]

### Added

- Search endpoint with full-text query support

### Fixed

- Fixed race condition in concurrent order processing
```

### 5. Entry Writing Style

Each entry should be a concise, human-readable description:

```markdown
<!-- FORBIDDEN - commit message style -->
- fix: handle null pointer in UserService
- feat(api): add search endpoint

<!-- FORBIDDEN - too vague -->
- Various improvements
- Bug fixes

<!-- CORRECT - clear and specific -->
- Added search endpoint with full-text query support (`GET /api/v1/search`)
- Fixed null pointer exception when user profile has no avatar
```

Tips for good entries:
- Start with the action verb matching the change type (Added X, Fixed Y, Removed Z)
- Include enough context to understand the change without reading the code
- Reference API endpoints, config keys, or class names where helpful
- Do not include PR numbers or commit hashes in the entry text (those belong in comparison links)

## Validation Checklist

- [ ] `## [Unreleased]` section exists after the preamble
- [ ] Only non-empty change types are listed
- [ ] Change types follow standard order
- [ ] Each entry is a `- ` list item with clear description
- [ ] No commit messages, PR numbers, or implementation details
- [ ] `[Unreleased]` comparison link at bottom points to `latest-tag...HEAD`

## Related Examples

- [`01-initial-changelog.md`](01-initial-changelog.md) - Creating the changelog with the first Unreleased section
- [`03-version-entry.md`](03-version-entry.md) - Promoting Unreleased content to a version
- [`04-change-types.md`](04-change-types.md) - Detailed guide to all 6 change types
