# Change Types

## Overview

Keep a Changelog defines 6 standard change types. Each type groups a specific kind of change, making it easy for users to find what matters to them. This example demonstrates all 6 types with real-world entries and guidance on choosing the correct type.

## When to Use

- **Unsure About Classification**: Deciding which change type an entry belongs under
- **New Contributor Onboarding**: Learning the conventions for changelog entries
- **Changelog Review**: Verifying entries are under the correct headings

## Implementation

### All 6 Change Types in a Single Version

```markdown
## [2.0.0] - 2024-06-01

### Added

- GraphQL API alongside existing REST endpoints
- Role-based access control with `admin`, `editor`, and `viewer` roles
- Database migration command (`bin/console app:migrate`)
- Health check endpoint at `GET /healthz`

### Changed

- **BREAKING**: Authentication now requires OAuth 2.0 tokens instead of API keys
- Default response format changed from XML to JSON
- Minimum PHP version raised from 8.2 to 8.3

### Deprecated

- `GET /api/v1/users` endpoint (use `GET /api/v2/users` instead, removal in v3.0)
- `Config::getLegacyOption()` method (use `Config::getOption()` instead)

### Removed

- XML response format support (deprecated in v1.5.0)
- PHP 8.1 and 8.2 support
- `GET /api/v1/legacy-search` endpoint (deprecated in v1.8.0)

### Fixed

- Fixed pagination returning duplicate results when items are added during traversal
- Corrected decimal precision loss in currency calculations
- Fixed `Content-Type` header missing on error responses

### Security

- Updated `guzzlehttp/guzzle` to 7.8.1 to address CVE-2024-XXXX (SSRF vulnerability)
- Added CSRF token validation to all state-changing form submissions
- Fixed open redirect vulnerability in OAuth callback handler
```

## Key Elements Explained

### 1. Added -- New Features

```markdown
### Added

- GraphQL API alongside existing REST endpoints
- Role-based access control with `admin`, `editor`, and `viewer` roles
```

**Use for**: Anything entirely new -- endpoints, commands, configuration options, integrations, UI features.

**Writing tips**:
- Start with a noun or the thing that was added
- Include enough context to understand what it does
- Reference specific endpoints, commands, or config keys

### 2. Changed -- Modifications to Existing Functionality

```markdown
### Changed

- **BREAKING**: Authentication now requires OAuth 2.0 tokens instead of API keys
- Default response format changed from XML to JSON
```

**Use for**: Modifications to behavior, API changes, dependency updates, configuration defaults.

**Writing tips**:
- Prefix breaking changes with `**BREAKING**:` to make them immediately visible
- Describe both the old and new behavior when the change may surprise users
- Dependency version bumps go here if they change behavior

### 3. Deprecated -- Features Marked for Future Removal

```markdown
### Deprecated

- `GET /api/v1/users` endpoint (use `GET /api/v2/users` instead, removal in v3.0)
- `Config::getLegacyOption()` method (use `Config::getOption()` instead)
```

**Use for**: Features that still work but will be removed in a future version.

**Writing tips**:
- Always include the replacement: "(use X instead)"
- Include the planned removal version if known: "(removal in vX.0)"
- Deprecation entries should appear BEFORE the removal entry in a later version

### 4. Removed -- Features That No Longer Exist

```markdown
### Removed

- XML response format support (deprecated in v1.5.0)
- PHP 8.1 and 8.2 support
```

**Use for**: Features that were previously deprecated and are now gone, or dropped platform support.

**Writing tips**:
- Reference when it was deprecated: "(deprecated in vX.Y.Z)"
- Removals are always breaking changes and warrant a MAJOR version bump
- Every Removed entry should have had a corresponding Deprecated entry in an earlier version

### 5. Fixed -- Bug Fixes

```markdown
### Fixed

- Fixed pagination returning duplicate results when items are added during traversal
- Corrected decimal precision loss in currency calculations
```

**Use for**: Bug fixes, corrections to incorrect behavior, regression fixes.

**Writing tips**:
- Start with "Fixed" or "Corrected" for consistency
- Describe the symptom, not the implementation: "Fixed pagination returning duplicates" not "Added DISTINCT to SQL query"
- Be specific enough that affected users can recognize their issue

### 6. Security -- Vulnerability Fixes

```markdown
### Security

- Updated `guzzlehttp/guzzle` to 7.8.1 to address CVE-2024-XXXX (SSRF vulnerability)
- Added CSRF token validation to all state-changing form submissions
```

**Use for**: Fixes for security vulnerabilities, security improvements, CVE patches.

**Writing tips**:
- Reference CVE numbers when available
- Describe the vulnerability type (XSS, SSRF, SQL injection, open redirect)
- Security entries should be in PATCH or MINOR releases, shipped quickly
- Users monitor this section to know when to upgrade urgently

## Decision Guide

When unsure which type to use:

```text
Is it entirely new functionality?
  --> Added

Does it change how existing functionality works?
  --> Changed (prefix with **BREAKING**: if it breaks existing usage)

Does it still work but will be removed later?
  --> Deprecated

Was it removed entirely?
  --> Removed

Did it fix incorrect behavior?
  --> Fixed

Does it address a security vulnerability?
  --> Security (even if it is technically a "fix")
```

**Note**: Security fixes go under `### Security`, not `### Fixed`, even though they are technically bug fixes. This separation helps users identify urgent updates.

## Validation Checklist

- [ ] Only non-empty change types are present (no empty headings)
- [ ] Change types appear in standard order: Added, Changed, Deprecated, Removed, Fixed, Security
- [ ] Breaking changes prefixed with `**BREAKING**:` under Changed or Removed
- [ ] Deprecated entries include replacement and planned removal version
- [ ] Removed entries reference when the feature was deprecated
- [ ] Security entries include CVE numbers where applicable
- [ ] Entries are human-readable, not commit messages

## Related Examples

- [`02-unreleased-section.md`](02-unreleased-section.md) - Using change types in the Unreleased section
- [`03-version-entry.md`](03-version-entry.md) - How change types influence version number selection
- [`07-real-world-changelog.md`](07-real-world-changelog.md) - Change types in a multi-version changelog
