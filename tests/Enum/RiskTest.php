<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Enum;

use MartinKup\OptimizationAdvisorBundle\Enum\Risk;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(Risk::class)]
#[Group('unit')]
final class RiskTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function casesProvider(): iterable
    {
        yield 'LOW' => ['LOW', 'low'];
        yield 'MED' => ['MED', 'med'];
        yield 'HIGH' => ['HIGH', 'high'];
    }

    #[DataProvider('casesProvider')]
    public function testCaseNameAndValue(string $expectedName, string $expectedValue): void
    {
        $case = Risk::from($expectedValue);

        self::assertSame($expectedName, $case->name);
        self::assertSame($expectedValue, $case->value);
    }

    public function testCaseCountMatchesProvider(): void
    {
        $expected = iterator_count(self::casesProvider());

        self::assertCount($expected, Risk::cases());
    }

    public function testTryFromInvalidValueReturnsNull(): void
    {
        self::assertNull(Risk::tryFrom(uniqid('invalid_')));
    }
}
