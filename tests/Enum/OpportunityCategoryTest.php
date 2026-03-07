<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Enum;

use MartinKup\OptimizationAdvisorBundle\Enum\OpportunityCategory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpportunityCategory::class)]
#[Group('unit')]
final class OpportunityCategoryTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function casesProvider(): iterable
    {
        yield 'DB' => ['DB', 'db'];
        yield 'CACHE' => ['CACHE', 'cache'];
        yield 'TWIG' => ['TWIG', 'twig'];
        yield 'EVENTS' => ['EVENTS', 'events'];
        yield 'HTTP' => ['HTTP', 'http'];
        yield 'MESSENGER' => ['MESSENGER', 'messenger'];
    }

    #[DataProvider('casesProvider')]
    public function testCaseNameAndValue(string $expectedName, string $expectedValue): void
    {
        $case = OpportunityCategory::from($expectedValue);

        self::assertSame($expectedName, $case->name);
        self::assertSame($expectedValue, $case->value);
    }

    public function testCaseCountMatchesProvider(): void
    {
        $expected = iterator_count(self::casesProvider());

        self::assertCount($expected, OpportunityCategory::cases());
    }

    public function testTryFromInvalidValueReturnsNull(): void
    {
        self::assertNull(OpportunityCategory::tryFrom(uniqid('invalid_')));
    }
}
