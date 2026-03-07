# Writing Changelogs - Examples

## Overview

This directory contains detailed examples for creating and maintaining changelogs following the Keep a Changelog 1.1.0 specification. Each example demonstrates a specific aspect of changelog management, from initial setup to complex real-world scenarios.

## Example Files

### Getting Started

| File                                                       | Description                                                       |
|------------------------------------------------------------|-------------------------------------------------------------------|
| [`01-initial-changelog.md`](01-initial-changelog.md)       | Creating `CHANGELOG.md` for a new project with preamble and first version |
| [`02-unreleased-section.md`](02-unreleased-section.md)     | Accumulating changes in the Unreleased section during development |

### Core Patterns

| File                                                       | Description                                                       |
|------------------------------------------------------------|-------------------------------------------------------------------|
| [`03-version-entry.md`](03-version-entry.md)               | Promoting Unreleased to a versioned release with date and links   |
| [`04-change-types.md`](04-change-types.md)                 | All 6 change types (Added, Changed, Deprecated, Removed, Fixed, Security) with examples |
| [`05-yanked-releases.md`](05-yanked-releases.md)           | Marking withdrawn versions with `[YANKED]` suffix                 |
| [`06-comparison-links.md`](06-comparison-links.md)         | Reference-style diff links between versions for GitHub/GitLab     |

### Real-World

| File                                                       | Description                                                       |
|------------------------------------------------------------|-------------------------------------------------------------------|
| [`07-real-world-changelog.md`](07-real-world-changelog.md) | Complete changelog spanning multiple versions with all patterns   |

## Quick Reference

### Changelog Lifecycle

```text
New project
  --> 01-initial-changelog: Create CHANGELOG.md with first version
      --> 02-unreleased-section: Add changes as development progresses
          --> 03-version-entry: Promote Unreleased to a new version
              --> 06-comparison-links: Add/update diff links
```

### When to Use Each Example

| Scenario                        | Example                    |
|---------------------------------|----------------------------|
| Starting a new project          | 01-initial-changelog       |
| Recording day-to-day changes    | 02-unreleased-section      |
| Preparing a release             | 03-version-entry           |
| Unsure which change type to use | 04-change-types            |
| Withdrawing a broken release    | 05-yanked-releases         |
| Setting up version diff links   | 06-comparison-links        |
| Full reference example          | 07-real-world-changelog    |

## Navigation

- **Main Skill**: [`SKILL.md`](../SKILL.md)
- **Related Skills**: [`implementing-symfony-profiler-data-collectors`](../../implementing-symfony-profiler-data-collectors/SKILL.md)
