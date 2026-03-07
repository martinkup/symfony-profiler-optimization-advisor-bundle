# Bundle Documentation Examples

Templates and examples for generating Symfony bundle documentation.

## Files

| File | Description |
|------|-------------|
| [01-bundle-readme.md](01-bundle-readme.md) | Main documentation hub with navigation, metrics, and tech stack |
| [02-section-index.md](02-section-index.md) | Section index with contents tables and reading path |
| [03-component-overview.md](03-component-overview.md) | Component documentation with class diagram and data flow |
| [04-adr-template.md](04-adr-template.md) | Architecture Decision Record template |
| [05-configuration-reference.md](05-configuration-reference.md) | Configuration reference with parameter catalog |
| [06-class-catalog.md](06-class-catalog.md) | Class catalog grouped by namespace |

## When to Use Each Example

| I need to...                                  | Use                          |
| --------------------------------------------- | ---------------------------- |
| Create the main docs hub for a bundle         | `01-bundle-readme.md`        |
| Add an index page for a docs subsection       | `02-section-index.md`        |
| Document a specific component (analyzer, engine, etc.) | `03-component-overview.md` |
| Record an architecture decision               | `04-adr-template.md`        |
| Document all configurable parameters          | `05-configuration-reference.md` |
| List all classes and enums by namespace       | `06-class-catalog.md`        |

## Notes

- All templates use `{placeholder}` syntax for values that should be replaced
- Templates are designed for Symfony bundles and reusable Composer packages
- Diagrams use Mermaid syntax (mindmap, pie, flowchart, sequenceDiagram, classDiagram)

## Related

- [SKILL.md](../SKILL.md) - Full skill specification
- [writing-changelogs](../../writing-changelogs/SKILL.md) - Changelog documentation
- [implementing-symfony-profiler-data-collectors](../../implementing-symfony-profiler-data-collectors/SKILL.md) - Data collector implementation
