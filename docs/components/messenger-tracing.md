# Messenger Tracing

[Docs Hub](../README.md) / [Components](index.md) / **Messenger Tracing**

Three classes that enable automatic Messenger handler tracing without manual configuration.

## Responsibilities

```mermaid
mindmap
  root((Messenger Tracing))
    TraceRegistry
      In-memory store
      Append records
      Update records
      Dispatch stack tracking
      MAX_RECORDS = 200
      Truncation handling
      ResetInterface
    MessageTracingMiddleware
      Auto-registered
      Measures handler duration
      Detects sync handling
      Extracts handler class
      Populates TraceRegistry
    MessageTracingMiddlewarePass
      CompilerPass
      TYPE_BEFORE_OPTIMIZATION priority 1
      Finds all messenger.bus tags
      Inserts after traceable middleware
      Idempotency check
```

## TraceRegistry

`src/Messenger/TraceRegistry.php`

### Public API

| Method                | Signature                                | Description                                                      |
|-----------------------|------------------------------------------|------------------------------------------------------------------|
| `append`              | `append(array $record): ?int`            | Appends a record, returns sequence number or `null` if truncated |
| `update`              | `update(int $seq, array $updates): void` | Merges updates into existing record                              |
| `pushDispatchStack`   | `pushDispatchStack(int $seq): void`      | Pushes sequence to dispatch stack (nested dispatch tracking)     |
| `popDispatchStack`    | `popDispatchStack(): void`               | Pops from dispatch stack                                         |
| `getCurrentParentSeq` | `getCurrentParentSeq(): ?int`            | Returns current parent sequence or `null`                        |
| `getCurrentDepth`     | `getCurrentDepth(): int`                 | Returns dispatch nesting depth                                   |
| `getRecords`          | `getRecords(): array`                    | Returns all stored records                                       |
| `getRecordCount`      | `getRecordCount(): int`                  | Returns number of stored records                                 |
| `isTruncated`         | `isTruncated(): bool`                    | Whether max records limit was reached                            |
| `getDroppedCount`     | `getDroppedCount(): int`                 | Number of dropped records after truncation                       |
| `reset`               | `reset(): void`                          | Clears all state                                                 |

### Constants

| Constant      | Value | Description                              |
|---------------|-------|------------------------------------------|
| `MAX_RECORDS` | `200` | Maximum stored trace records per request |

---

## MessageTracingMiddleware

`src/Messenger/Middleware/MessageTracingMiddleware.php`

### Public API

| Method   | Signature                                                     | Description                                        |
|----------|---------------------------------------------------------------|----------------------------------------------------|
| `handle` | `handle(Envelope $envelope, StackInterface $stack): Envelope` | Passes to next middleware, then records trace data |

### Recorded Fields

| Field                 | Type      | Source                                  |
|-----------------------|-----------|-----------------------------------------|
| `is_handled_sync`     | `bool`    | `HandledStamp` present on envelope      |
| `handler_class`       | `?string` | `HandledStamp::getHandlerName()` (FQCN) |
| `handler_duration_ms` | `float`   | `(microtime(true) - start) * 1000.0`    |
| `message_short`       | `string`  | Short class name of dispatched message  |

---

## MessageTracingMiddlewarePass

`src/DependencyInjection/Compiler/MessageTracingMiddlewarePass.php`

### Registration

Registered in `OptimizationAdvisorBundle::build()` with:

- Pass type: `PassConfig::TYPE_BEFORE_OPTIMIZATION`
- Priority: `1`
- Condition: Only when `Symfony\Component\Messenger\Middleware\MiddlewareInterface` exists

### Logic

```mermaid
flowchart TD
    START[process] --> FIND[Find all services tagged messenger.bus]
    FIND --> LOOP{For each bus}
    LOOP --> PARAM{"Has {busId}.middleware parameter?"}
    PARAM -->|No| SKIP[Skip bus]
    PARAM -->|Yes| CHECK{Already registered?}
    CHECK -->|Yes| SKIP
    CHECK -->|No| POS[Find insert position]
    POS --> TRACEABLE{"traceable middleware found?"}
    TRACEABLE -->|Yes| AFTER[Insert after traceable]
    TRACEABLE -->|No| FIRST[Insert at position 0]
    AFTER --> SET[Set parameter]
    FIRST --> SET
    SET --> LOOP
    SKIP --> LOOP
```

### Idempotency

The pass checks if `MessageTracingMiddleware::class` already exists in the middleware list. If found, the bus is skipped to prevent duplicate registration.

---

## Class Diagram

```mermaid
classDiagram
    class ResetInterface {
        <<interface>>
        +reset() void
    }
    class TraceRegistry {
        <<final>>
        -array records
        -int nextSeq
        -bool truncated
        -int droppedCount
        -array dispatchStack
        +append(record) int?
        +update(seq, updates) void
        +getRecords() array
        +reset() void
    }
    ResetInterface <|.. TraceRegistry

    class MiddlewareInterface {
        <<interface>>
        +handle(Envelope, StackInterface) Envelope
    }
    class MessageTracingMiddleware {
        <<final>>
        -TraceRegistry traceRegistry
        +handle(Envelope, StackInterface) Envelope
    }
    MiddlewareInterface <|.. MessageTracingMiddleware
    MessageTracingMiddleware --> TraceRegistry

    class CompilerPassInterface {
        <<interface>>
        +process(ContainerBuilder) void
    }
    class MessageTracingMiddlewarePass {
        <<final>>
        +process(ContainerBuilder) void
    }
    CompilerPassInterface <|.. MessageTracingMiddlewarePass
```

## Dispatch Flow

```mermaid
sequenceDiagram
    participant C as Controller/Service
    participant B as MessageBus
    participant M as MessageTracingMiddleware
    participant N as Next Middleware + Handler
    participant TR as TraceRegistry
    participant DC as DataCollector

    C->>B: dispatch(message)
    B->>M: handle(envelope, stack)
    Note over M: start = microtime(true)
    M->>N: stack.next().handle(envelope, stack)
    N-->>M: envelope with HandledStamp
    M->>TR: append(is_handled_sync, handler_class, duration_ms, message_short)
    M-->>B: envelope
    Note over DC: Later in lateCollect()
    DC->>TR: getRecords()
    TR-->>DC: trace records
```

---

[&larr; SQL Utilities](sql-utilities.md) | [Next: Configuration &rarr;](../configuration/index.md)
