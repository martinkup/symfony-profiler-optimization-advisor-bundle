<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Enum;

use MartinKup\OptimizationAdvisorBundle\Enum\DataOrigin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataOrigin::class)]
#[Group('unit')]
final class DataOriginTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function casesProvider(): iterable
    {
        yield 'APP' => ['APP', 'app'];
        yield 'INFRA' => ['INFRA', 'infra'];
        yield 'PROFILER' => ['PROFILER', 'profiler'];
    }

    #[DataProvider('casesProvider')]
    public function testCaseNameAndValue(string $expectedName, string $expectedValue): void
    {
        $case = DataOrigin::from($expectedValue);

        self::assertSame($expectedName, $case->name);
        self::assertSame($expectedValue, $case->value);
    }

    public function testCaseCountMatchesProvider(): void
    {
        $expected = iterator_count(self::casesProvider());

        self::assertCount($expected, DataOrigin::cases());
    }

    public function testTryFromInvalidValueReturnsNull(): void
    {
        self::assertNull(DataOrigin::tryFrom(uniqid('invalid_')));
    }
}
