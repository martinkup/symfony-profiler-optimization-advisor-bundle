# Initial Changelog

## Overview

This example shows how to create a `CHANGELOG.md` for a new project. It demonstrates the required file structure: title, preamble with specification links, an Unreleased section, the first version entry, and a comparison link.

## When to Use

- **New Project**: Setting up `CHANGELOG.md` from scratch
- **First Release**: Documenting the initial version (typically `0.1.0` or `1.0.0`)
- **Migration**: Converting from a non-standard format to Keep a Changelog

## Implementation

### Minimal Initial Changelog

```markdown
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2024-01-10

### Added

- Project scaffolding with basic directory structure
- Core domain model with `Order` and `OrderItem` entities
- REST API endpoints for order management (`GET`, `POST`, `PUT`)
- PHPUnit test suite with unit and integration tests
- CI pipeline with GitHub Actions (lint, test, static analysis)
- Docker Compose development environment

[Unreleased]: https://github.com/acme/order-service/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/acme/order-service/releases/tag/v0.1.0
```

## Key Elements Explained

### 1. Title

```markdown
# Changelog
```

- Always `# Changelog` as H1 heading
- Some projects use `# CHANGELOG` -- both are acceptable, but `# Changelog` is the convention from the specification

### 2. Preamble

```markdown
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
```

- Communicates the purpose and format to readers
- Links to both specifications so readers can learn the conventions
- "All notable changes" signals that this is curated, not a raw commit log

### 3. Unreleased Section

```markdown
## [Unreleased]
```

- Always present, even when empty
- Collects changes that will go into the next release
- Square brackets enable it to be a link target (comparison link at bottom)

### 4. First Version Entry

```markdown
## [0.1.0] - 2024-01-10

### Added

- Project scaffolding with basic directory structure
```

- Version in square brackets: `[0.1.0]`
- Date in ISO 8601: `2024-01-10`
- Hyphen separator: ` - ` (space-hyphen-space)
- For the initial release, most entries will be under `### Added`

### 5. Comparison Links

```markdown
[Unreleased]: https://github.com/acme/order-service/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/acme/order-service/releases/tag/v0.1.0
```

- Reference-style links at the bottom of the file
- `[Unreleased]` compares the latest tag to HEAD
- The first version links to the release tag (no predecessor to compare against)

### 6. Choosing the First Version Number

| Version   | When to Use                                              |
|-----------|----------------------------------------------------------|
| `0.1.0`   | Early development, API not yet stable, pre-release       |
| `1.0.0`   | Stable public API, production-ready, commitment to SemVer|

- Start with `0.1.0` if you expect breaking changes before stabilizing
- Start with `1.0.0` if the project is already stable and publicly consumed

## Validation Checklist

- [ ] File named `CHANGELOG.md` in project root
- [ ] Title is `# Changelog`
- [ ] Preamble links to Keep a Changelog and SemVer
- [ ] `## [Unreleased]` section present
- [ ] First version uses `## [X.Y.Z] - YYYY-MM-DD` format
- [ ] Changes grouped under `### Added` (for initial release)
- [ ] Entries written as human-readable summaries
- [ ] Comparison links at bottom of file
- [ ] `[Unreleased]` link compares latest tag to HEAD
- [ ] First version link points to release tag

## Related Examples

- [`02-unreleased-section.md`](02-unreleased-section.md) - Adding changes to Unreleased as development continues
- [`03-version-entry.md`](03-version-entry.md) - Promoting Unreleased to a new versioned release
- [`06-comparison-links.md`](06-comparison-links.md) - Detailed guide to comparison links
