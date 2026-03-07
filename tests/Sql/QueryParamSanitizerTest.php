<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Sql;

use MartinKup\OptimizationAdvisorBundle\Sql\QueryParamSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

/**
 * Unit tests for QueryParamSanitizer.
 *
 * Test Coverage:
 * - Scalar values (string, int, float, bool) pass through as-is with runnable=true
 * - Null value passes through with runnable=true
 * - Stringable objects are converted to string with runnable=true
 * - Non-Stringable objects become placeholder with runnable=false
 * - Resources become placeholder with runnable=false
 * - Nested arrays are recursively sanitized
 * - Empty params produce empty result with runnable=true
 * - Mixed params with one non-runnable value produce runnable=false
 *
 * @see QueryParamSanitizer
 */
#[CoversClass(QueryParamSanitizer::class)]
#[Group('unit')]
final class QueryParamSanitizerTest extends TestCase
{
    private QueryParamSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new QueryParamSanitizer();
    }

    public function testSanitizeScalarValues(): void
    {
        $result = $this->sanitizer->sanitize(
            [
                'string' => 'hello',
                'int'    => 42,
                'float'  => 3.14,
                'bool'   => true,
            ],
        );

        self::assertSame('hello', $result['params']['string']);
        self::assertSame(42, $result['params']['int']);
        self::assertSame(3.14, $result['params']['float']);
        self::assertTrue($result['params']['bool']);
        self::assertTrue($result['runnable']);
    }

    public function testSanitizeNullValue(): void
    {
        $result = $this->sanitizer->sanitize(['key' => null]);

        self::assertNull($result['params']['key']);
        self::assertTrue($result['runnable']);
    }

    public function testSanitizeStringableObject(): void
    {
        $stringable = new class () implements Stringable {
            public function __toString(): string
            {
                return 'stringable-value';
            }
        };

        $result = $this->sanitizer->sanitize(['obj' => $stringable]);

        self::assertSame('stringable-value', $result['params']['obj']);
        self::assertTrue($result['runnable']);
    }

    public function testSanitizeNonStringableObject(): void
    {
        $object = new stdClass();

        $result = $this->sanitizer->sanitize(['obj' => $object]);

        self::assertSame('{object(stdClass)}', $result['params']['obj']);
        self::assertFalse($result['runnable']);
    }

    public function testSanitizeResource(): void
    {
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);

        $result = $this->sanitizer->sanitize(['res' => $resource]);

        self::assertSame('/* Resource(stream) */', $result['params']['res']);
        self::assertFalse($result['runnable']);

        fclose($resource);
    }

    public function testSanitizeNestedArray(): void
    {
        $result = $this->sanitizer->sanitize(
            [
                'ids' => [1, 2, 3],
            ],
        );

        self::assertSame([1, 2, 3], $result['params']['ids']);
        self::assertTrue($result['runnable']);
    }

    public function testSanitizeNestedArrayWithNonRunnableValue(): void
    {
        $result = $this->sanitizer->sanitize(
            [
                'mixed' => ['ok', new stdClass()],
            ],
        );

        $mixed = $result['params']['mixed'];
        self::assertIsArray($mixed);
        self::assertSame('ok', $mixed[0]);
        self::assertSame('{object(stdClass)}', $mixed[1]);
        self::assertFalse($result['runnable']);
    }

    public function testSanitizeEmptyParams(): void
    {
        $result = $this->sanitizer->sanitize([]);

        self::assertSame([], $result['params']);
        self::assertTrue($result['runnable']);
    }

    public function testSanitizeMixedParamsWithOneNonRunnable(): void
    {
        $result = $this->sanitizer->sanitize(
            [
                'name' => 'Alice',
                'age'  => 30,
                'meta' => new stdClass(),
            ],
        );

        self::assertSame('Alice', $result['params']['name']);
        self::assertSame(30, $result['params']['age']);
        self::assertSame('{object(stdClass)}', $result['params']['meta']);
        self::assertFalse($result['runnable']);
    }
}
