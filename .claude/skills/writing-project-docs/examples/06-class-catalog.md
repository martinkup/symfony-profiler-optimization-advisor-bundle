[Home](../README.md) > [Components](index.md) > **Class Catalog**

---

# Class Catalog

## Class Overview

```mermaid
pie title Classes by Namespace
    "{Namespace1}": {N}
    "{Namespace2}": {N}
    "{Namespace3}": {N}
    "{Namespace4}": {N}
```

**Total: {N} classes**

## {Namespace1}

### {ClassName1}

{Brief class description.}

**File:** `src/{Namespace1}/{ClassName1}.php`

| Method | Return Type | Description |
|--------|-------------|-------------|
| `{method1}()` | `{ReturnType}` | {What the method does} |
| `{method2}()` | `{ReturnType}` | {What the method does} |
| `{method3}()` | `{ReturnType}` | {What the method does} |

### {ClassName2}

{Brief class description.}

**File:** `src/{Namespace1}/{ClassName2}.php`

| Method | Return Type | Description |
|--------|-------------|-------------|
| `{method1}()` | `{ReturnType}` | {What the method does} |
| `{method2}()` | `{ReturnType}` | {What the method does} |

---

## {Namespace2}

### {ClassName3}

{Brief class description.}

**File:** `src/{Namespace2}/{ClassName3}.php`

| Method | Return Type | Description |
|--------|-------------|-------------|
| `{method1}()` | `{ReturnType}` | {What the method does} |

---

## {Namespace3} (Enums)

### {EnumName1}

{Brief enum description.}

**File:** `src/{Namespace3}/{EnumName1}.php`

| Case | Value | Description |
|------|-------|-------------|
| `{Case1}` | `'{value1}'` | {What this case represents} |
| `{Case2}` | `'{value2}'` | {What this case represents} |
| `{Case3}` | `'{value3}'` | {What this case represents} |

### {EnumName2}

{Brief enum description.}

**File:** `src/{Namespace3}/{EnumName2}.php`

| Case | Value | Description |
|------|-------|-------------|
| `{Case1}` | `'{value1}'` | {What this case represents} |
| `{Case2}` | `'{value2}'` | {What this case represents} |

---

## {Namespace4} (Utilities)

### {UtilityClass}

{Brief class description.}

**File:** `src/{Namespace4}/{UtilityClass}.php`

| Method | Return Type | Description |
|--------|-------------|-------------|
| `{method1}()` | `{ReturnType}` | {What the method does} |
| `{method2}()` | `{ReturnType}` | {What the method does} |

---

## Class Hierarchy

```mermaid
classDiagram
    class {InterfaceName} {
        <<interface>>
        +{method1}(): {ReturnType}
    }

    class {AbstractClass} {
        <<abstract>>
        #{sharedMethod}(): {ReturnType}
    }

    class {ConcreteClass1} {
        +{method1}(): {ReturnType}
        +{specificMethod}(): {ReturnType}
    }

    class {ConcreteClass2} {
        +{method1}(): {ReturnType}
    }

    class {EnumName} {
        <<enumeration>>
        {Case1}
        {Case2}
        {Case3}
    }

    {InterfaceName} <|.. {AbstractClass}
    {AbstractClass} <|-- {ConcreteClass1}
    {AbstractClass} <|-- {ConcreteClass2}
    {ConcreteClass1} --> {EnumName}
```

---

## In This Section

| Document | Description |
|----------|-------------|
| [Component Index](index.md) | Component catalog overview |
| **Class Catalog** | All classes by namespace (this page) |

---

## Navigation

| Previous | Up | Next |
|:---------|:--:|-----:|
| [{PreviousComponent}]({prev}.md) | [Components](index.md) | [{NextSection}](../{next}/index.md) |
