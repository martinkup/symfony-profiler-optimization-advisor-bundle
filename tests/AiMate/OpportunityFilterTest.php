<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\AiMate;

use MartinKup\OptimizationAdvisorBundle\AiMate\OpportunityFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/** @see OpportunityFilter */
#[CoversClass(OpportunityFilter::class)]
#[Group('unit')]
final class OpportunityFilterTest extends TestCase
{
    public function testForMcpOutputStripsExcludedFields(): void
    {
        $opportunities = [
            [
                'code' => 'DB_N_PLUS_ONE',
                'category' => 'db',
                'impact' => 8,
                'recommended_actions' => ['Add eager loading', 'Use batch queries'],
                'ai_prompt' => 'Some AI prompt text',
            ],
        ];

        $result = OpportunityFilter::forMcpOutput($opportunities);

        self::assertCount(1, $result);
        self::assertSame('DB_N_PLUS_ONE', $result[0]['code']);
        self::assertSame('db', $result[0]['category']);
        self::assertSame(8, $result[0]['impact']);
        self::assertArrayNotHasKey('recommended_actions', $result[0]);
        self::assertArrayNotHasKey('ai_prompt', $result[0]);
    }

    public function testForMcpOutputHandlesEmptyArray(): void
    {
        $result = OpportunityFilter::forMcpOutput([]);

        self::assertSame([], $result);
    }

    public function testForMcpOutputHandlesMissingExcludedFields(): void
    {
        $opportunities = [
            ['code' => 'CACHE_LOW_HIT', 'category' => 'cache', 'impact' => 5],
        ];

        $result = OpportunityFilter::forMcpOutput($opportunities);

        self::assertCount(1, $result);
        self::assertSame('CACHE_LOW_HIT', $result[0]['code']);
        self::assertSame('cache', $result[0]['category']);
    }

    public function testForMcpOutputProcessesMultipleOpportunities(): void
    {
        $opportunities = [
            ['code' => 'DB_N_PLUS_ONE', 'recommended_actions' => ['Fix'], 'ai_prompt' => 'Prompt 1'],
            ['code' => 'CACHE_LOW_HIT', 'recommended_actions' => ['Tune'], 'ai_prompt' => 'Prompt 2'],
            ['code' => 'TWIG_DEEP', 'ai_prompt' => 'Prompt 3'],
        ];

        $result = OpportunityFilter::forMcpOutput($opportunities);

        self::assertCount(3, $result);

        foreach ($result as $opportunity) {
            self::assertArrayNotHasKey('recommended_actions', $opportunity);
            self::assertArrayNotHasKey('ai_prompt', $opportunity);
        }

        self::assertSame('DB_N_PLUS_ONE', $result[0]['code']);
        self::assertSame('CACHE_LOW_HIT', $result[1]['code']);
        self::assertSame('TWIG_DEEP', $result[2]['code']);
    }
}
