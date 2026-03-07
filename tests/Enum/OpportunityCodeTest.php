<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Enum;

use MartinKup\OptimizationAdvisorBundle\Enum\OpportunityCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpportunityCode::class)]
#[Group('unit')]
final class OpportunityCodeTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function validValuesProvider(): iterable
    {
        yield 'PG_SLOW_QUERY_GROUP' => ['PG_SLOW_QUERY_GROUP'];
        yield 'PG_N_PLUS_ONE_SUSPECTED' => ['PG_N_PLUS_ONE_SUSPECTED'];
        yield 'PG_DUPLICATE_QUERY' => ['PG_DUPLICATE_QUERY'];
        yield 'PG_LARGE_RESULTSET' => ['PG_LARGE_RESULTSET'];
        yield 'CACHE_CANDIDATE_DB_RESULTS' => ['CACHE_CANDIDATE_DB_RESULTS'];
        yield 'CACHE_LOW_HITRATE_POOL' => ['CACHE_LOW_HITRATE_POOL'];
        yield 'DOCTRINE_2LC_OPPORTUNITY' => ['DOCTRINE_2LC_OPPORTUNITY'];
        yield 'TWIG_HOT_TEMPLATE' => ['TWIG_HOT_TEMPLATE'];
        yield 'TWIG_DUP_RENDER' => ['TWIG_DUP_RENDER'];
        yield 'EVENTS_TOO_MANY_LISTENERS' => ['EVENTS_TOO_MANY_LISTENERS'];
        yield 'EVENTS_SLOW_LISTENER' => ['EVENTS_SLOW_LISTENER'];
        yield 'HTTP_SLOW_ENDPOINT' => ['HTTP_SLOW_ENDPOINT'];
        yield 'HTTP_DUP_CALL' => ['HTTP_DUP_CALL'];
        yield 'MESSENGER_SYNC_HEAVY' => ['MESSENGER_SYNC_HEAVY'];
    }

    #[DataProvider('validValuesProvider')]
    public function testFromReturnsMatchingCase(string $value): void
    {
        $code = OpportunityCode::from($value);

        self::assertSame($value, $code->value);
        self::assertSame($value, $code->name);
    }

    public function testCaseCountMatchesProvider(): void
    {
        $expected = iterator_count(self::validValuesProvider());

        self::assertCount($expected, OpportunityCode::cases());
    }

    public function testTryFromInvalidValueReturnsNull(): void
    {
        self::assertNull(OpportunityCode::tryFrom(uniqid('invalid_')));
    }
}
