---
name: writing-changelogs
description: Creates and maintains CHANGELOG.md files following the Keep a Changelog 1.1.0 specification. Use when creating a new changelog, adding entries for new releases, documenting changes in Unreleased section, tagging versions, writing release notes, or reviewing existing changelogs for compliance. Also use when working with SemVer version bumps, yanked releases, or comparison links between versions.
---

# Writing Changelogs (Keep a Changelog 1.1.0)

## Purpose

Creates and maintains `CHANGELOG.md` files that follow the [Keep a Changelog 1.1.0](https://keepachangelog.com/en/1.1.0/) specification. Ensures changelogs are human-readable, consistent, and useful for both maintainers and consumers of the project.

## When to Use

- **New Project Setup**: Creating the initial `CHANGELOG.md`
- **Release Preparation**: Adding a new version entry from the Unreleased section
- **Feature/Fix Documentation**: Recording changes in the Unreleased section
- **Yanked Release**: Marking a version as withdrawn
- **Changelog Review**: Verifying an existing changelog follows the specification
- **Migration**: Converting a non-standard changelog to Keep a Changelog format

## When NOT to Use

- **Commit Messages**: Changelogs are not commit logs -- write human-readable summaries
- **Git Log Dumps**: Never copy `git log` output into a changelog
- **Internal Documentation**: Use project docs or wikis, not the changelog
- **Automated Release Notes**: GitHub release notes are separate from `CHANGELOG.md`

## Core Principles

1. **Changelogs are for humans, not machines** -- Write clear, readable entries that help users understand what changed and why it matters
2. **There should be an entry for every single version** -- Every released version gets its own section
3. **The same types of changes should be grouped** -- Use the 6 standard change types consistently
4. **Versions and sections should be linkable** -- Use Markdown heading anchors and comparison links
5. **The latest version comes first** -- Reverse chronological order, newest at the top
6. **The release date of each version is displayed** -- ISO 8601 format (YYYY-MM-DD), no regional formats
7. **Mention whether you follow Semantic Versioning** -- Reference [SemVer](https://semver.org/) in the preamble

## File Convention

- **Filename**: `CHANGELOG.md` (uppercase, in project root)
- **Format**: Markdown
- **Encoding**: UTF-8
- **Location**: Repository root, next to `README.md` and `composer.json`/`package.json`

## Structure

A changelog follows this structure from top to bottom:

```markdown
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- New feature description

## [1.1.0] - 2024-03-15

### Added

- Feature A
- Feature B

### Fixed

- Bug fix description

## [1.0.0] - 2024-01-10

### Added

- Initial release

[Unreleased]: https://github.com/user/repo/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/user/repo/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/user/repo/releases/tag/v1.0.0
```

### Key Structural Rules

1. **Title**: `# Changelog` as H1
2. **Preamble**: Short paragraph with links to Keep a Changelog and SemVer
3. **Unreleased Section**: Always present as `## [Unreleased]`, collects changes for the next release
4. **Version Sections**: `## [X.Y.Z] - YYYY-MM-DD` in reverse chronological order
5. **Change Type Headings**: `### Added`, `### Changed`, etc. -- only include types that have entries
6. **Comparison Links**: Reference-style links at the bottom of the file

## Change Types

Six standard types, always in this order when present:

| Type           | Purpose                                           | Example                                        |
|----------------|---------------------------------------------------|------------------------------------------------|
| `### Added`    | New features                                      | New CLI command, API endpoint, configuration   |
| `### Changed`  | Changes in existing functionality                 | Updated dependency, changed default behavior   |
| `### Deprecated` | Soon-to-be removed features                     | Method marked for removal in next major        |
| `### Removed`  | Now removed features                              | Dropped PHP 8.1 support, removed legacy API    |
| `### Fixed`    | Bug fixes                                         | Fixed null pointer, corrected validation logic |
| `### Security` | Vulnerability fixes                               | Patched XSS in form handler, updated dependency|

### Rules for Change Types

- **Only include types that have entries** -- do not add empty `### Added` sections
- **Order matters** -- follow the order above for consistency across versions
- **Each entry is a list item** -- use `- ` prefix (Markdown unordered list)
- **Write for humans** -- explain what changed and why it matters, not implementation details

## Date Format

- **Required**: ISO 8601 (`YYYY-MM-DD`)
- **Examples**: `2024-03-15`, `2026-01-01`
- **Forbidden**: Regional formats like `March 15, 2024`, `15/03/2024`, `15.03.2024`

## Versioning

- Follow [Semantic Versioning 2.0.0](https://semver.org/spec/v2.0.0.html)
- **MAJOR**: Incompatible API changes
- **MINOR**: Added functionality in a backward-compatible manner
- **PATCH**: Backward-compatible bug fixes
- Pre-release versions are valid: `1.0.0-alpha`, `1.0.0-beta.1`, `1.0.0-rc.1`
- Version numbers in headings use brackets: `## [1.2.3] - 2024-03-15`

## Yanked Releases

Mark withdrawn versions with `[YANKED]` suffix:

```markdown
## [0.3.1] - 2024-02-10 [YANKED]
```

- Used when a critical bug or security issue makes a release unsuitable for use
- The version entry remains in the changelog -- it is NOT deleted
- Entries under the yanked version remain intact to document what was in that release

See [`examples/05-yanked-releases.md`](examples/05-yanked-releases.md) for complete example.

## Comparison Links

Reference-style links at the bottom of the file enable clickable version diffs:

```markdown
[Unreleased]: https://github.com/user/repo/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/user/repo/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/user/repo/releases/tag/v1.0.0
```

### Rules

- `[Unreleased]` always compares the latest release tag to `HEAD`
- Each version compares to its predecessor: `vPREVIOUS...vCURRENT`
- The first (oldest) version links to the release tag, not a comparison
- Tag format must match project convention (e.g., `v1.0.0` vs `1.0.0`)
- Update `[Unreleased]` link every time a new version is released

See [`examples/06-comparison-links.md`](examples/06-comparison-links.md) for detailed examples.

## Anti-Patterns (FORBIDDEN)

### Commit Log Dumps

```markdown
<!-- FORBIDDEN -->
## [1.2.0] - 2024-03-15

- fix typo
- wip
- merge branch 'feature/foo'
- updated deps
- code review fixes
```

Changelogs are curated summaries, not `git log --oneline` output.

### Missing Change Types

```markdown
<!-- FORBIDDEN -->
## [1.2.0] - 2024-03-15

- Added new search endpoint
- Fixed pagination bug
- Removed legacy API
```

Always group changes under `### Added`, `### Fixed`, `### Removed` headings.

### Regional Date Formats

```markdown
<!-- FORBIDDEN -->
## [1.2.0] - March 15, 2024
## [1.2.0] - 15/03/2024
## [1.2.0] - 15.03.2024

<!-- CORRECT -->
## [1.2.0] - 2024-03-15
```

### Inconsistent Formatting

```markdown
<!-- FORBIDDEN - mixing styles -->
## 1.2.0 (2024-03-15)
## [1.1.0] - 2024-02-01
## v1.0.0 -- 2024-01-10

<!-- CORRECT - consistent format -->
## [1.2.0] - 2024-03-15
## [1.1.0] - 2024-02-01
## [1.0.0] - 2024-01-10
```

### Missing Unreleased Section

```markdown
<!-- FORBIDDEN - no Unreleased section -->
# Changelog

## [1.0.0] - 2024-01-10

### Added

- Initial release
```

Always maintain an `## [Unreleased]` section, even if empty.

### Deleting Yanked Versions

Yanked versions are marked, never deleted. The history must remain intact.

## Validation Checklist

### File Structure

- [ ] File named `CHANGELOG.md` in project root
- [ ] Title is `# Changelog`
- [ ] Preamble references Keep a Changelog and SemVer
- [ ] `## [Unreleased]` section exists
- [ ] Versions in reverse chronological order (newest first)

### Version Entries

- [ ] Format: `## [X.Y.Z] - YYYY-MM-DD`
- [ ] Version in square brackets
- [ ] Date in ISO 8601 format
- [ ] Hyphen separator between version and date
- [ ] Changes grouped under correct `### Type` headings
- [ ] Change types in standard order (Added, Changed, Deprecated, Removed, Fixed, Security)
- [ ] Only non-empty change types included
- [ ] Each change is a `- ` list item

### Comparison Links

- [ ] Reference-style links at bottom of file
- [ ] `[Unreleased]` compares latest tag to HEAD
- [ ] Each version compares to predecessor
- [ ] First version links to release tag
- [ ] Tag format matches project convention

### Content Quality

- [ ] Entries written for humans (not commit messages)
- [ ] No `git log` dumps
- [ ] Security fixes documented under `### Security`
- [ ] Breaking changes clearly noted under `### Changed` or `### Removed`
- [ ] Deprecations documented before removal

## Examples

- **[Examples README](examples/README.md)** - Navigation guide and index
- [`01-initial-changelog.md`](examples/01-initial-changelog.md) - Creating a changelog for a new project
- [`02-unreleased-section.md`](examples/02-unreleased-section.md) - Working with the Unreleased section
- [`03-version-entry.md`](examples/03-version-entry.md) - Adding a new version (release)
- [`04-change-types.md`](examples/04-change-types.md) - All 6 change types with examples
- [`05-yanked-releases.md`](examples/05-yanked-releases.md) - Marking withdrawn versions
- [`06-comparison-links.md`](examples/06-comparison-links.md) - Diff links between versions
- [`07-real-world-changelog.md`](examples/07-real-world-changelog.md) - Complete real-world changelog

## Related Skills

- **[implementing-symfony-profiler-data-collectors](../implementing-symfony-profiler-data-collectors/SKILL.md)** - Data collector skill in this project

## External References

- [Keep a Changelog 1.1.0](https://keepachangelog.com/en/1.1.0/)
- [Semantic Versioning 2.0.0](https://semver.org/spec/v2.0.0.html)
- [Common Changelog](https://common-changelog.org/) - Stricter convention built on Keep a Changelog

---

**Key Principle**: A changelog is a curated, human-readable summary of notable changes per version. It uses 6 standard change types, ISO 8601 dates, reverse chronological order, and comparison links. Every version gets an entry, the Unreleased section is always present, and entries are written for humans -- not machines.
