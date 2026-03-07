# Testing Data Collectors

## Overview

Data collectors are infrastructure components that benefit from focused unit tests. Because collectors depend on simple Symfony HTTP objects (`Request`, `Response`) and store data in a serializable `$data` property, they are straightforward to test without a framework kernel or container. This document demonstrates unit testing patterns for basic collectors, event subscriber collectors, and identity methods.

## When to Use

- **After creating a new data collector**: Verify `collect()` populates data correctly and typed getters return expected values
- **After adding event listener methods**: Confirm accumulators capture events and `collect()` transfers them to `$data`
- **After modifying threshold logic**: Ensure `getErrorCount()`, status getters, and boundary values behave correctly
- **After implementing `reset()`**: Validate that both `$data` and private accumulators are cleared

## Implementation

### Unit Test for a Basic Data Collector

This test covers the core lifecycle: create the collector, call `collect()` with real Symfony HTTP objects, and assert data through typed getters.

**Test file placement**: `tests/DataCollector/ExampleDataCollectorTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\DataCollector;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use App\DataCollector\ExampleDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ExampleDataCollector::class)]
#[Group('unit')]
final class ExampleDataCollectorTest extends TestCase
{
    private ExampleDataCollector $collector;

    #[Override]
    protected function setUp(): void
    {
        $this->collector = new ExampleDataCollector();
    }

    #[Test]
    public function itCollectsMetricCountFromResponse(): void
    {
        // Arrange
        $request = Request::create('/test-page', 'GET');
        $response = new Response('OK', Response::HTTP_OK);

        // Act
        $this->collector->collect($request, $response);

        // Assert
        self::assertSame(0, $this->collector->getMetricCount());
    }

    #[Test]
    public function itCollectsItemsAsEmptyArrayByDefault(): void
    {
        // Arrange
        $request = Request::create('/test-page', 'GET');
        $response = new Response('OK', Response::HTTP_OK);

        // Act
        $this->collector->collect($request, $response);

        // Assert
        self::assertSame([], $this->collector->getItems());
    }

    #[Test]
    public function itPassesExceptionWithoutAffectingBasicMetrics(): void
    {
        // Arrange
        $request = Request::create('/error-page', 'GET');
        $response = new Response('Error', Response::HTTP_INTERNAL_SERVER_ERROR);
        $exception = new \RuntimeException('Something went wrong', 500);

        // Act
        $this->collector->collect($request, $response, $exception);

        // Assert - ExampleDataCollector does not vary by exception
        self::assertSame(0, $this->collector->getMetricCount());
        self::assertSame([], $this->collector->getItems());
    }

    #[Test]
    public function itResetsDataToEmptyState(): void
    {
        // Arrange
        $request = Request::create('/test-page', 'GET');
        $response = new Response('OK', Response::HTTP_OK);
        $this->collector->collect($request, $response);

        // Act
        $this->collector->reset();

        // Assert
        self::assertSame([], $this->collector->getItems());
    }

    #[Test]
    public function itReturnsCorrectCollectorName(): void
    {
        // Act
        $name = $this->collector->getName();

        // Assert
        self::assertSame('app.example', $name);
    }

    #[Test]
    public function itReturnsCorrectTemplatePath(): void
    {
        // Act
        $template = ExampleDataCollector::getTemplate();

        // Assert
        self::assertSame('data_collector/example.html.twig', $template);
    }
}
```

### Unit Test for Event Subscriber Collector

When a collector implements `EventSubscriberInterface`, test the event listener methods independently from `collect()`. Verify that listeners populate the private accumulator, `collect()` transfers accumulated data to getters, and `reset()` clears both.

```php
<?php

declare(strict_types=1);

namespace App\Tests\DataCollector;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use App\DataCollector\ErrorTrackingDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ErrorTrackingDataCollector::class)]
#[Group('unit')]
final class ErrorTrackingDataCollectorTest extends TestCase
{
    private ErrorTrackingDataCollector $collector;

    #[Override]
    protected function setUp(): void
    {
        $this->collector = new ErrorTrackingDataCollector();
    }

    // === Event Listener Tests ===

    #[Test]
    public function itAccumulatesTrackedErrors(): void
    {
        // Arrange
        $exception1 = new \RuntimeException('First error', 100);
        $exception2 = new \InvalidArgumentException('Second error', 200);

        // Act
        $this->collector->trackError($exception1);
        $this->collector->trackError($exception2);

        // Trigger collect to transfer accumulator to data
        $request = Request::create('/test', 'GET');
        $response = new Response('', Response::HTTP_OK);
        $this->collector->collect($request, $response);

        // Assert
        self::assertSame(2, $this->collector->getErrorCount());
    }

    #[Test]
    public function itCapturesExceptionDetailsInAccumulator(): void
    {
        // Arrange
        $exception = new \LogicException('Validation failed', 422);

        // Act
        $this->collector->trackError($exception);

        $request = Request::create('/test', 'GET');
        $response = new Response('', Response::HTTP_OK);
        $this->collector->collect($request, $response);

        // Assert
        $errors = $this->collector->getErrors();
        self::assertCount(1, $errors);
        self::assertSame(\LogicException::class, $errors[0]['class']);
        self::assertSame('Validation failed', $errors[0]['message']);
        self::assertSame(422, $errors[0]['code']);
    }

    // === Collect Transfer Tests ===

    #[Test]
    public function itTransfersAccumulatedDataToGettersOnCollect(): void
    {
        // Arrange
        $this->collector->trackError(new \RuntimeException('Error A'));
        $this->collector->trackError(new \RuntimeException('Error B'));
        $this->collector->trackError(new \RuntimeException('Error C'));

        $request = Request::create('/test', 'GET');
        $response = new Response('', Response::HTTP_OK);

        // Act
        $this->collector->collect($request, $response);

        // Assert
        self::assertSame(3, $this->collector->getErrorCount());
        self::assertCount(3, $this->collector->getErrors());
    }

    #[Test]
    public function itAlsoCapturesExceptionParameterDuringCollect(): void
    {
        // Arrange
        $request = Request::create('/test', 'GET');
        $response = new Response('', Response::HTTP_INTERNAL_SERVER_ERROR);
        $exception = new \RuntimeException('Uncaught error');

        // Act - no prior trackError calls, only the collect() exception
        $this->collector->collect($request, $response, $exception);

        // Assert
        self::assertSame(1, $this->collector->getErrorCount());
        self::assertSame(
            \RuntimeException::class,
            $this->collector->getErrors()[0]['class'],
        );
    }

    // === Reset Tests ===

    #[Test]
    public function itResetsAccumulatorAndDataOnReset(): void
    {
        // Arrange - populate both accumulator and data
        $this->collector->trackError(new \RuntimeException('Error'));

        $request = Request::create('/test', 'GET');
        $response = new Response('', Response::HTTP_OK);
        $this->collector->collect($request, $response);

        self::assertSame(1, $this->collector->getErrorCount());

        // Act
        $this->collector->reset();

        // Re-collect to verify accumulator was also cleared
        $this->collector->collect($request, $response);

        // Assert
        self::assertSame(0, $this->collector->getErrorCount());
        self::assertSame([], $this->collector->getErrors());
    }

    // === Identity Tests ===

    #[Test]
    public function itReturnsExpectedCollectorName(): void
    {
        // Act & Assert
        self::assertSame('app.error_tracking', $this->collector->getName());
    }

    #[Test]
    public function itReturnsExpectedTemplatePath(): void
    {
        // Act & Assert
        self::assertSame(
            'data_collector/error_tracking.html.twig',
            ErrorTrackingDataCollector::getTemplate(),
        );
    }

    // === Zero-Data Tests ===

    #[Test]
    public function itReportsZeroErrorsWhenNoneTracked(): void
    {
        // Arrange
        $request = Request::create('/test', 'GET');
        $response = new Response('OK', Response::HTTP_OK);

        // Act
        $this->collector->collect($request, $response);

        // Assert
        self::assertSame(0, $this->collector->getErrorCount());
        self::assertSame([], $this->collector->getErrors());
    }
}
```

### Testing getName() and getTemplate()

These identity methods are simple but critical -- they connect the collector to its service tag and Twig template. Test them explicitly to catch typos and refactoring regressions.

```php
#[Test]
public function itReturnsExpectedCollectorName(): void
{
    // Act
    $name = $this->collector->getName();

    // Assert
    self::assertSame('app.example', $name);
}

#[Test]
public function itReturnsExpectedTemplatePath(): void
{
    // Act - getTemplate() is static
    $template = ExampleDataCollector::getTemplate();

    // Assert
    self::assertSame('data_collector/example.html.twig', $template);
}
```

Note that `getTemplate()` is a `static` method on `AbstractDataCollector`, so call it on the class rather than the instance. Both calling conventions work in PHP, but using `ClassName::getTemplate()` makes the static nature explicit.

## Key Elements Explained

### Test File Placement

Test files mirror the source namespace exactly:

| Source                                       | Test                                               |
|----------------------------------------------|----------------------------------------------------|
| `src/DataCollector/ExampleDataCollector.php` | `tests/DataCollector/ExampleDataCollectorTest.php` |

This follows the Mirror Principle where test namespace structure replicates source namespace structure. Test type (`unit`, `integration`, `functional`) is declared via `#[Group]` attributes, not folder structure.

### Creating Mock Request and Response

Use Symfony's built-in factory methods rather than PHPUnit mocks for HTTP objects:

```php
// Request::create() builds a fully populated Request from a URI
$request = Request::create('/test-page', 'GET');

// Response constructor accepts content, status code, and headers
$response = new Response('OK', Response::HTTP_OK);

// With custom request attributes (e.g., route name)
$request = Request::create('/admin/users', 'GET');
$request->attributes->set('_route', 'app.user.list');
```

These are real objects, not mocks, which makes tests more realistic and avoids brittle mock configurations. The `Request::create()` method handles populating server variables, query parameters, and other internals that would be tedious to mock.

### AAA Pattern

Every test method follows Arrange-Act-Assert with explicit comments:

- **Arrange**: Set up the collector, create request/response, prepare any preconditions
- **Act**: Call the method under test (`collect()`, `reset()`, `trackError()`)
- **Assert**: Verify outcomes through typed getters using `self::assert*()` methods

### PHPUnit Attributes

```php
#[CoversClass(ExampleDataCollector::class)]  // Links test to production class
#[Group('unit')]                              // Test type
```

The `#[Test]` attribute on each method replaces the `test` method name prefix.

### Testing the Accumulator-Collect-Reset Lifecycle

For event subscriber collectors, the test sequence is:

1. **Call listener methods** (`trackError()`) to populate the private accumulator
2. **Call `collect()`** to transfer accumulated data into `$this->data`
3. **Assert via getters** that data was transferred correctly
4. **Call `reset()`** to clear both accumulator and data
5. **Call `collect()` again** to verify the accumulator was truly emptied

This lifecycle test catches a common bug where `reset()` clears `$this->data` but forgets to clear the private accumulator property.

## Validation Checklist

- [ ] Test class extends `TestCase` (unit tests do not need the kernel)
- [ ] `#[CoversClass]` attribute references the collector class under test
- [ ] `#[Group('unit')]` attribute marks the test type
- [ ] `#[Test]` attribute on every test method (no `test` prefix in method name)
- [ ] Method names use `itDoesAction` pattern (e.g., `itCollectsMetricCountFromResponse`)
- [ ] `Request::create()` and `new Response()` used instead of PHPUnit mocks for HTTP objects
- [ ] AAA pattern with explicit `// Arrange`, `// Act`, `// Assert` comments
- [ ] Test covers `collect()` populating data through typed getters
- [ ] Test covers `reset()` clearing `$data` to empty state
- [ ] Test covers `getName()` returning expected string
- [ ] Test covers `getTemplate()` returning expected template path (called statically)
- [ ] Event subscriber test covers listener method populating accumulator
- [ ] Event subscriber test covers `reset()` clearing both accumulator and data
- [ ] Test file path mirrors source path: `tests/DataCollector/`

## Related Examples

- [`01-basic-data-collector.md`](01-basic-data-collector.md) - The collector class being tested
- [`06-event-subscriber-collector.md`](06-event-subscriber-collector.md) - Event subscriber pattern tested here
- [`09-conditional-collection.md`](09-conditional-collection.md) - Conditional patterns with `ErrorTrackingDataCollector`
