# Yanked Releases

## Overview

A yanked release is a version that has been withdrawn after publication due to a critical bug, security issue, or accidental release. Keep a Changelog uses the `[YANKED]` suffix to mark these versions. The version entry is never deleted -- it remains in the changelog with the yanked marker.

## When to Use

- **Critical Bug**: A release introduced a data-loss bug or corruption issue
- **Security Vulnerability**: A release contains an exploitable vulnerability
- **Accidental Release**: A version was published prematurely or from the wrong branch
- **Broken Package**: The release artifact is corrupt or missing files

## Implementation

### Marking a Version as Yanked

```markdown
## [Unreleased]

### Security

- Fixed critical SQL injection in search endpoint (regression from 1.4.0)

## [1.4.1] - 2024-07-20

### Security

- Fixed critical SQL injection in search endpoint (regression from 1.4.0)

## [1.4.0] - 2024-07-15 [YANKED]

### Added

- Full-text search endpoint with SQL-based query builder
- Search result caching with configurable TTL

### Fixed

- Improved query performance for large datasets

## [1.3.0] - 2024-06-01

### Added

- Previous version content...

[Unreleased]: https://github.com/acme/order-service/compare/v1.4.1...HEAD
[1.4.1]: https://github.com/acme/order-service/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/acme/order-service/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/acme/order-service/compare/v1.2.0...v1.3.0
```

## Key Elements Explained

### 1. The `[YANKED]` Suffix

```markdown
## [1.4.0] - 2024-07-15 [YANKED]
```

- Appended after the date on the version heading
- Uppercase: `[YANKED]`, not `[yanked]` or `[Yanked]`
- Square brackets around the word
- Separated from the date by a space

### 2. Content Preserved

The entries under a yanked version remain intact:

```markdown
## [1.4.0] - 2024-07-15 [YANKED]

### Added

- Full-text search endpoint with SQL-based query builder
```

This documents what was in the yanked release so users who installed it can understand what they had.

### 3. Never Delete Yanked Versions

```markdown
<!-- FORBIDDEN - deleting the version entry -->
## [1.4.1] - 2024-07-20
## [1.3.0] - 2024-06-01    <-- 1.4.0 is missing!

<!-- CORRECT - mark as yanked, keep content -->
## [1.4.1] - 2024-07-20
## [1.4.0] - 2024-07-15 [YANKED]
## [1.3.0] - 2024-06-01
```

### 4. Comparison Links Remain

The comparison link for the yanked version stays in place. The fix version compares against the yanked version:

```markdown
[1.4.1]: https://github.com/acme/order-service/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/acme/order-service/compare/v1.3.0...v1.4.0
```

This allows users to see exactly what changed between the yanked version and the fix.

### 5. Typical Yank-and-Fix Flow

1. **Discover issue** in released version 1.4.0
2. **Mark** 1.4.0 as `[YANKED]` in CHANGELOG.md
3. **Create fix** and release as 1.4.1
4. **Add** 1.4.1 entry with the fix described
5. **Update** comparison links

## Validation Checklist

- [ ] `[YANKED]` suffix is uppercase and in square brackets
- [ ] Yanked version entry content is preserved (not deleted)
- [ ] A follow-up fix version exists after the yanked version
- [ ] Comparison links remain intact for the yanked version
- [ ] The fix version's changelog entry describes what was fixed

## Related Examples

- [`03-version-entry.md`](03-version-entry.md) - Standard version entry format
- [`06-comparison-links.md`](06-comparison-links.md) - How comparison links work with yanked versions
- [`07-real-world-changelog.md`](07-real-world-changelog.md) - Yanked version in context of a full changelog
