# Comparison Links

## Overview

Comparison links are reference-style Markdown links at the bottom of the changelog that make version headings clickable. Each link points to a diff between two versions on the hosting platform (GitHub, GitLab, Bitbucket). This example covers all patterns: Unreleased, regular versions, the first version, and platform-specific URL formats.

## When to Use

- **Every Release**: Comparison links must be updated on every version release
- **Initial Setup**: Creating the first links when starting a changelog
- **Platform Migration**: Updating URLs when moving between hosting platforms
- **Link Audit**: Verifying all links are correct and functional

## Implementation

### Complete Link Section

```markdown
## [Unreleased]

## [2.1.0] - 2024-09-15

### Added

- New feature

## [2.0.0] - 2024-08-01

### Changed

- Breaking change

## [1.5.0] - 2024-06-15

### Fixed

- Bug fix

## [1.0.0] - 2024-01-10

### Added

- Initial release

[Unreleased]: https://github.com/acme/order-service/compare/v2.1.0...HEAD
[2.1.0]: https://github.com/acme/order-service/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/acme/order-service/compare/v1.5.0...v2.0.0
[1.5.0]: https://github.com/acme/order-service/compare/v1.0.0...v1.5.0
[1.0.0]: https://github.com/acme/order-service/releases/tag/v1.0.0
```

## Key Elements Explained

### 1. Link Structure

```markdown
[version]: https://platform/user/repo/compare/vPREVIOUS...vCURRENT
```

- **Reference-style**: `[label]: URL` at the bottom, referenced by `## [label]` headings
- **Comparison URL**: Platform-specific diff URL between two tags
- **Three dots**: `...` separates the base and head references

### 2. Unreleased Link

```markdown
[Unreleased]: https://github.com/acme/order-service/compare/v2.1.0...HEAD
```

- Always compares the **latest released tag** to `HEAD`
- **Must be updated** every time a new version is released
- Shows all commits since the last release

### 3. Regular Version Links

```markdown
[2.1.0]: https://github.com/acme/order-service/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/acme/order-service/compare/v1.5.0...v2.0.0
```

- Each version compares to its **immediate predecessor**
- Format: `vOLD...vNEW`
- Note: predecessor is the previous entry in the changelog, not necessarily a sequential number (e.g., `v1.5.0...v2.0.0` is valid)

### 4. First Version Link

```markdown
[1.0.0]: https://github.com/acme/order-service/releases/tag/v1.0.0
```

- The oldest version has no predecessor to compare against
- Links to the **release tag** instead of a comparison
- Uses `/releases/tag/` instead of `/compare/`

### 5. Tag Format Consistency

The tag format in URLs must match the actual Git tags:

```markdown
<!-- If tags use "v" prefix -->
[1.0.0]: https://github.com/acme/repo/compare/v0.9.0...v1.0.0

<!-- If tags do NOT use "v" prefix -->
[1.0.0]: https://github.com/acme/repo/compare/0.9.0...1.0.0
```

Check your project's tag convention with `git tag --list`.

### 6. Platform-Specific URLs

#### GitHub

```markdown
[1.1.0]: https://github.com/user/repo/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/user/repo/releases/tag/v1.0.0
```

#### GitLab

```markdown
[1.1.0]: https://gitlab.com/user/repo/-/compare/v1.0.0...v1.1.0
[1.0.0]: https://gitlab.com/user/repo/-/releases/v1.0.0
```

Note the `/-/` in GitLab URLs.

#### Bitbucket

```markdown
[1.1.0]: https://bitbucket.org/user/repo/branches/compare/v1.1.0..v1.0.0
[1.0.0]: https://bitbucket.org/user/repo/src/v1.0.0
```

Note: Bitbucket uses `..` (two dots) and reverses the order (new..old in the URL path, but the diff shows old-to-new).

### 7. Release Checklist for Links

When releasing version `1.3.0` (previous was `1.2.0`):

1. **Add** new link: `[1.3.0]: .../compare/v1.2.0...v1.3.0`
2. **Update** Unreleased: `[Unreleased]: .../compare/v1.3.0...HEAD`
3. **Verify** the new link is between Unreleased and the previous version link
4. **Keep** all existing links unchanged

### 8. Link Ordering

Links should be in reverse chronological order, matching the version order:

```markdown
[Unreleased]: ...compare/v2.1.0...HEAD
[2.1.0]: ...compare/v2.0.0...v2.1.0
[2.0.0]: ...compare/v1.5.0...v2.0.0
[1.5.0]: ...compare/v1.0.0...v1.5.0
[1.0.0]: ...releases/tag/v1.0.0
```

## Validation Checklist

- [ ] Every version heading has a corresponding comparison link
- [ ] `[Unreleased]` link compares latest tag to HEAD
- [ ] Each version compares to its immediate predecessor
- [ ] First (oldest) version links to release tag, not comparison
- [ ] Tag format in URLs matches actual Git tags (`v1.0.0` vs `1.0.0`)
- [ ] Platform URL format is correct (GitHub vs GitLab vs Bitbucket)
- [ ] Links are in reverse chronological order
- [ ] No broken or orphaned links

## Related Examples

- [`01-initial-changelog.md`](01-initial-changelog.md) - First comparison links when creating a changelog
- [`03-version-entry.md`](03-version-entry.md) - Updating links during a release
- [`05-yanked-releases.md`](05-yanked-releases.md) - Links with yanked versions
- [`07-real-world-changelog.md`](07-real-world-changelog.md) - Full link section in a real changelog
