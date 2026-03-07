---
name: writing-project-docs
description: Generate structured documentation for Symfony bundles in Markdown with Mermaid diagrams. Use when creating bundle READMEs, documenting architecture, component catalogs, configuration references, integration guides, or ADRs. Also use when the project is a reusable Composer package rather than a full application -- even if the user just says 'write docs' or 'document the architecture'.
allowed-tools: Read, Write, Glob, Grep, Bash(tree:*)
---

# Bundle Documentation Generator

Generate comprehensive, navigable documentation for Symfony bundles and reusable Composer packages.

## When to Use

- **Bundle README**: Creating the main documentation hub with installation, configuration, and features
- **Architecture Documentation**: Documenting bundle internals, data flow, component interactions
- **Component Catalog**: Cataloging classes, enums, and services by namespace
- **Configuration Reference**: Documenting all bundle parameters, types, defaults, and examples
- **Integration Guide**: Installation steps, optional dependencies, extension points
- **ADR**: Recording architecture decisions within the bundle

## When NOT to Use

- **Full Applications**: Projects with API endpoints, database entities, security, deployment -- use a different skill or approach
- **Inline PHPDoc**: Use IDE or code-level documentation tools
- **CHANGELOG**: Use the `writing-changelogs` skill instead

## Quick Start

1. **Analyze the bundle** - Explore `src/` structure, identify namespaces, read `composer.json`
2. **Create directory structure** - Use the standard `docs/` layout below
3. **Generate documents** - Start with README.md, then section indexes, then detail pages
4. **Add navigation** - Ensure all documents have breadcrumbs and footer navigation

## Documentation Structure

```
docs/
├── README.md                 # Hub - installation, features, quick start
├── overview/
│   ├── index.md              # Section overview
│   ├── architecture.md       # High-level architecture and data flow
│   ├── tech-stack.md         # PHP version, Symfony version, dependencies
│   └── glossary.md           # Bundle-specific terminology
├── components/
│   ├── index.md              # Component catalog index
│   ├── {component1}.md       # Individual component documentation
│   └── {component2}.md       # Individual component documentation
├── configuration/
│   ├── index.md              # Configuration reference
│   └── examples.md           # Common configuration scenarios
├── integration/
│   ├── index.md              # Installation and setup
│   ├── optional-deps.md      # Optional dependencies and fallbacks
│   └── extending.md          # Extension points for consumers
├── architecture/
│   └── adr/
│       ├── index.md          # ADR list
│       └── 0001-*.md         # Individual decisions
├── testing/
│   └── index.md              # Testing strategy
└── development/
    ├── index.md              # Contributing guide
    └── coding-standards.md   # Code style rules
```

## Document Types

### 1. Bundle README (Hub)

Required sections:
- Quick Navigation (mindmap)
- Getting Started (table)
- Documentation by Role (Bundle User / Contributor)
- Bundle at a Glance (data flow diagram)
- Key Metrics (table)
- Technology Stack (table)

See [examples/01-bundle-readme.md](examples/01-bundle-readme.md)

### 2. Section Index

Required sections:
- Section Contents (tables)
- Reading Path (flowchart)
- Related Sections (links)
- Navigation footer

See [examples/02-section-index.md](examples/02-section-index.md)

### 3. Component Overview

Required sections:
- Overview with mindmap of responsibilities
- Public API (methods table)
- Class diagram
- Configuration affecting the component
- Data flow sequence diagram
- Related Components

See [examples/03-component-overview.md](examples/03-component-overview.md)

### 4. Architecture Decision Record (ADR)

Required sections:
- Status (Proposed/Accepted/Deprecated/Superseded)
- Context (problem statement)
- Decision (solution)
- Consequences (positive and negative)
- References (code paths)

See [examples/04-adr-template.md](examples/04-adr-template.md)

### 5. Configuration Reference

Required sections:
- Parameter distribution (pie chart)
- Full configuration tree (YAML)
- Parameter catalog by category (tables)
- Common scenarios (YAML examples)
- Environment notes

See [examples/05-configuration-reference.md](examples/05-configuration-reference.md)

### 6. Class Catalog

Required sections:
- Class distribution (pie chart)
- Classes grouped by namespace
- Method tables for classes
- Value tables for enums
- Class hierarchy diagram

See [examples/06-class-catalog.md](examples/06-class-catalog.md)

## Navigation Elements

### Breadcrumbs (top of every document)

```markdown
[Home](../README.md) > [Section](index.md) > **Current Page**
```

### Footer Navigation (bottom of every document)

```markdown
---

## Navigation

| Previous | Up | Next |
|:---------|:--:|-----:|
| [Previous Page](prev.md) | [Index](index.md) | [Next Page](next.md) |
```

### In This Section (before footer)

```markdown
## In This Section

| Document | Description |
|----------|-------------|
| **Current Page** | This page description |
| [Next](next.md) | Next page description |
```

## Diagram Types (Mermaid)

| Type | Use Case |
|------|----------|
| `mindmap` | Bundle structure, component responsibilities |
| `pie` | Distribution of classes, parameters |
| `flowchart` | Data flow pipeline, reading path |
| `sequenceDiagram` | DataCollector -> Analyzer -> Engine flow |
| `classDiagram` | Class hierarchies, interfaces, enums |
| `graph` | Bundle architecture |

## Mermaid Syntax Rules

### Escaping Special Characters in Node Labels

When node labels contain special characters, **always wrap the text in double quotes** to prevent parsing errors.

| Characters | Problem | Solution |
|------------|---------|----------|
| `/`, `*` | Path-like patterns break parsing | Use quotes: `A["/api/*"]` |
| `()`, `[]`, `{}` | Conflict with shape syntax | Use quotes: `A["func()"]` |
| Reserved words | `end`, `graph`, etc. break flow | Use quotes: `A["end state"]` |

**Examples:**

```mermaid
%% WRONG - will cause parsing errors
GW[/gw/*]
API[/api/v1/users]

%% CORRECT - special characters escaped with quotes
GW["/gw/*"]
API["/api/v1/users"]
```

**Rule of thumb:** If the label contains anything other than alphanumeric characters and spaces, use double quotes.

## Table Patterns

### Quick Reference

```markdown
| Document | Description |
|----------|-------------|
| [Overview](overview.md) | High-level description |
| [Details](details.md) | Detailed reference |
```

### Class Methods

```markdown
| Method | Return Type | Description |
|--------|-------------|-------------|
| `analyze()` | `array` | Produces signal array |
| `evaluate()` | `array` | Scores opportunities |
```

### Configuration Parameters

```markdown
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `max_items` | int | 20 | Maximum opportunities |
| `app_namespace_prefix` | string | `App\\` | Application namespace |
```

## Formatting Rules

1. **One `#` heading per document** - Main title only
2. **Use `##` for major sections** - Clear hierarchy
3. **Horizontal rules** (`---`) before/after navigation
4. **Code blocks with language** - ` ```php`, ` ```yaml`, etc.
5. **Bold for key terms** - Highlight important concepts
6. **Inline code for paths** - `src/Analyzer/DatabaseAnalyzer.php`
7. **Relative links only** - Never absolute URLs for internal docs

## Bundle Documentation Guidelines

When documenting a Symfony bundle or reusable Composer package:

1. **Library vs. Application** - Document the public API surface, not internal implementation details consumers never interact with
2. **Consumer perspective** - Write for developers integrating the bundle into their application
3. **Configuration-first** - Lead with configuration and setup, not internal architecture
4. **Graceful degradation** - Document behavior when optional dependencies are missing (e.g., `doctrine/sql-formatter` not installed)
5. **Scale appropriately** - Small bundles need fewer sections; do not create empty sections for completeness

## Workflow

1. **Discovery Phase**
   - Run `tree src/` to understand structure
   - Identify main namespaces and components
   - Read `composer.json` for dependencies (required and optional)
   - Count key metrics (source files, components, rules, tests)

2. **Structure Phase**
   - Create `docs/` directory
   - Create section subdirectories
   - Plan document hierarchy based on bundle size

3. **Content Phase**
   - Write README.md first (the hub)
   - Create section indexes
   - Write component pages
   - Write configuration reference
   - Add diagrams (mindmaps, class diagrams, sequence diagrams)

4. **Navigation Phase**
   - Add breadcrumbs to all pages
   - Add footer navigation
   - Cross-link related documents
   - Verify all links work

## Anti-Patterns (FORBIDDEN)

- **Application-centric documentation** - API endpoints, entity schemas, deployment guides, security configuration
- **Framework internals** - Documenting Symfony framework behavior instead of the bundle's public surface
- **Missing installation section** - Bundle README must always include Composer install + basic configuration
- **Missing graceful degradation docs** - If optional dependencies exist, document what happens when they are absent

## Validation Checklist

- [ ] `docs/` directory exists with `README.md` as the hub
- [ ] Section indexes present for each subdirectory
- [ ] Bundle README includes: installation, configuration, features, quick start
- [ ] Component docs: each main component documented with class diagrams and public API
- [ ] Configuration: all parameters listed with types, defaults, descriptions
- [ ] Navigation: breadcrumbs on every page, footer navigation, cross-links
- [ ] Quality: written for bundle consumers, optional dependencies documented

## Examples

See [examples/](examples/) directory:
- [01-bundle-readme.md](examples/01-bundle-readme.md) - Bundle README hub template
- [02-section-index.md](examples/02-section-index.md) - Section index template
- [03-component-overview.md](examples/03-component-overview.md) - Component documentation
- [04-adr-template.md](examples/04-adr-template.md) - ADR format
- [05-configuration-reference.md](examples/05-configuration-reference.md) - Configuration reference
- [06-class-catalog.md](examples/06-class-catalog.md) - Class catalog

## Related Skills

- `writing-changelogs` - Creating and maintaining CHANGELOG.md
- `implementing-symfony-profiler-data-collectors` - Data collector implementation details

## External References

- [Symfony Bundle Best Practices](https://symfony.com/doc/current/bundles/best_practices.html)

## Key Principle

> Bundle documentation is consumer-focused: it tells developers how to install, configure, and extend the bundle. It uses component diagrams, data flow sequences, and configuration references -- not API endpoints, entity schemas, or deployment guides.
