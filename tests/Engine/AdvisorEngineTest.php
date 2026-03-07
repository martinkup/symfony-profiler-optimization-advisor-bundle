<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Engine;

use MartinKup\OptimizationAdvisorBundle\Engine\AdvisorEngine;
use MartinKup\OptimizationAdvisorBundle\Enum\OpportunityCategory;
use MartinKup\OptimizationAdvisorBundle\Enum\OpportunityCode;
use MartinKup\OptimizationAdvisorBundle\Enum\Risk;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AdvisorEngine optimization opportunity detection.
 *
 * Test Coverage:
 * - Slow query group detection (total_ms >= threshold)
 * - N+1 query pattern detection (many fast SELECTs)
 * - Duplicate query detection (same pattern executed 3+ times)
 * - Large resultset detection (slow SELECT with count 1-3)
 * - Cache candidate detection (repeated SELECTs)
 * - Low hit-rate cache pool detection
 * - Doctrine 2LC opportunity detection (many fast entity loads)
 * - Twig hot template detection (high total render time)
 * - Twig duplicate render detection (many renders)
 * - Too many listener calls detection
 * - Slow event listener detection
 * - HTTP slow endpoint detection
 * - HTTP duplicate call detection
 * - Messenger sync heavy handler detection
 * - AI prompt generation per opportunity
 * - ROI calculation correctness
 * - Fingerprint-based deduplication
 * - Max items truncation
 * - Infra-origin and profiler-origin items filtered out across all detectors
 *
 * @see AdvisorEngine
 */
#[CoversClass(AdvisorEngine::class)]
#[Group('unit')]
final class AdvisorEngineTest extends TestCase
{
    private AdvisorEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new AdvisorEngine();
    }

    public function testDetectsSlowQueryGroup(): void
    {
        $db = $this->buildDbSignals(
            [
                [
                    'fingerprint' => 'fp_slow_1',
                    'pattern'     => 'UPDATE users SET ? = ? WHERE id = ?',
                    'total_ms'    => 35.0,
                    'count'       => 1,
                    'kind'        => 'UPDATE',
                ],
            ],
        );

        $result = $this->engine->evaluate($db, ...self::emptyRemainingSignals());

        self::assertCount(1, $result);
        self::assertSame(OpportunityCode::PG_SLOW_QUERY_GROUP->value, $result[0]['code']);
        self::assertSame(OpportunityCategory::DB->value, $result[0]['category']);
        self::assertSame(4, $result[0]['impact']);
        self::assertSame(3, $result[0]['effort']);
        self::assertSame(4, $result[0]['confidence']);
        self::assertSame(Risk::MED->value, $result[0]['risk']);
        self::assertSame(['UPDATE users SET ? = ? WHERE id = ?'], $result[0]['evidence_refs']);
        self::assertArrayHasKey('ai_prompt', $result[0]);
        $aiPrompt = $result[0]['ai_prompt'];
        self::assertIsString($aiPrompt);
        self::assertStringContainsString('Optimization task:', $aiPrompt);
        self::assertStringContainsString('implement the optimization', $aiPrompt);
    }

    public function testDetectsNPlusOneSuspected(): void
    {
        $db = $this->buildDbSignals(
            [
                [
                    'fingerprint' => 'fp_nplus1',
                    'pattern'     => 'SELECT t0_.id FROM orders t0_ WHERE t0_.user_id = ?',
                    'total_ms'    => 20.0,
                    'count'       => 12,
                    'avg_ms'      => 1.6,
                    'kind'        => 'SELECT',
                ],
            ],
        );

        $result = $this->engine->evaluate($db, ...self::emptyRemainingSignals());

        $nPlusOne = $this->filterByCode($result, OpportunityCode::PG_N_PLUS_ONE_SUSPECTED);
        self::assertNotEmpty($nPlusOne);
        self::assertSame(OpportunityCategory::DB->value, $nPlusOne[0]['category']);
        self::assertSame(5, $nPlusOne[0]['impact']);
        self::assertSame(Risk::MED->value, $nPlusOne[0]['risk']);
    }

    public function testDetectsDuplicateQuery(): void
    {
        $db = $this->buildDbSignals(
            [
                [
                    'fingerprint' => 'fp_dup',
                    'pattern'     => 'INSERT INTO audit_log (id, action) VALUES (?, ?)',
                    'total_ms'    => 5.0,
                    'count'       => 4,
                    'kind'        => 'INSERT',
                ],
            ],
        );

        $result = $this->engine->evaluate($db, ...self::emptyRemainingSignals());

        $duplicates = $this->filterByCode($result, OpportunityCode::PG_DUPLICATE_QUERY);
        self::assertNotEmpty($duplicates);
        self::assertSame(OpportunityCategory::DB->value, $duplicates[0]['category']);
        self::assertSame(5, $duplicates[0]['confidence']);
        self::assertSame(Risk::LOW->value, $duplicates[0]['risk']);
    }

    public function testDetectsLargeResultset(): void
    {
        $db = $this->buildDbSignals(
            [
                [
                    'fingerprint' => 'fp_large',
                    'pattern'     => 'SELECT t0_.* FROM products t0_',
                    'total_ms'    => 150.0,
                    'max_ms'      => 150.0,
                    'count'       => 2,
                    'kind'        => 'SELECT',
                ],
            ],
        );

        $result = $this->engine->evaluate($db, ...self::emptyRemainingSignals());

        $large = $this->filterByCode($result, OpportunityCode::PG_LARGE_RESULTSET);
        self::assertNotEmpty($large);
        self::assertSame(OpportunityCategory::DB->value, $large[0]['category']);
        self::assertSame(2, $large[0]['impact']);
        self::assertSame(Risk::LOW->value, $large[0]['risk']);
    }

    public function testDetectsCacheCandidateDbResults(): void
    {
        $db = $this->buildDbSignals(
            [
                [
                    'fingerprint' => 'fp_cache_cand',
                    'pattern'     => 'SELECT t0_.id FROM settings t0_ WHERE t0_.key = ?',
                    'total_ms'    => 9.0,
                    'count'       => 4,
                    'avg_ms'      => 2.25,
                    'kind'        => 'SELECT',
                ],
            ],
        );

        $result = $this->engine->evaluate($db, ...self::emptyRemainingSignals());

        $cache = $this->filterByCode($result, OpportunityCode::CACHE_CANDIDATE_DB_RESULTS);
        self::assertNotEmpty($cache);
        self::assertSame(OpportunityCategory::CACHE->value, $cache[0]['category']);
        self::assertSame(4, $cache[0]['impact']);
        self::assertSame(Risk::MED->value, $cache[0]['risk']);
    }

    public function testDetectsLowHitratePool(): void
    {
        $cache = $this->buildCacheSignals(
            [
                [
                    'pool'     => 'app.slow_pool',
                    'hit_rate' => 25.0,
                    'calls'    => 15,
                ],
            ],
        );

        $result = $this->engine->evaluate(
            $this->buildDbSignals(),
            $cache,
            $this->buildTwigSignals(),
            $this->buildEventSignals(),
            $this->buildHttpSignals(),
            $this->buildOtherSignals(),
        );

        self::assertCount(1, $result);
        self::assertSame(OpportunityCode::CACHE_LOW_HITRATE_POOL->value, $result[0]['code']);
        self::assertSame(OpportunityCategory::CACHE->value, $result[0]['category']);
        self::assertSame(Risk::LOW->value, $result[0]['risk']);
        self::assertSame(['app.slow_pool'], $result[0]['evidence_refs']);
    }

    public function testDetectsDoctrine2LCOpportunity(): void
    {
        $db = $this->buildDbSignals(
            [
                [
                    'fingerprint' => 'fp_2lc',
                    'pattern'     => 'SELECT t0_.id FROM categories t0_ WHERE t0_.id = ?',
                    'total_ms'    => 5.0,
                    'count'       => 8,
                    'avg_ms'      => 0.6,
                    'kind'        => 'SELECT',
                ],
            ],
        );

        $result = $this->engine->evaluate($db, ...self::emptyRemainingSignals());

        $lc = $this->filterByCode($result, OpportunityCode::DOCTRINE_2LC_OPPORTUNITY);
        self::assertNotEmpty($lc);
        self::assertSame(OpportunityCategory::DB->value, $lc[0]['category']);
        self::assertSame(3, $lc[0]['impact']);
        self::assertSame(Risk::MED->value, $lc[0]['risk']);
    }

    public function testDetectsTwigHotTemplate(): void
    {
        $twig = $this->buildTwigSignals(
            [
                [
                    'template' => 'listing/_row.html.twig',
                    'total_ms' => 75.0,
                    'renders'  => 3,
                ],
            ],
        );

        $result = $this->engine->evaluate(
            $this->buildDbSignals(),
            $this->buildCacheSignals(),
            $twig,
            $this->buildEventSignals(),
            $this->buildHttpSignals(),
            $this->buildOtherSignals(),
        );

        self::assertCount(1, $result);
        self::assertSame(OpportunityCode::TWIG_HOT_TEMPLATE->value, $result[0]['code']);
        self::assertSame(OpportunityCategory::TWIG->value, $result[0]['category']);
        self::assertSame(Risk::LOW->value, $result[0]['risk']);
        self::assertSame(['listing/_row.html.twig'], $result[0]['evidence_refs']);
    }

    public function testDetectsTwigDupRender(): void
    {
        $twig = $this->buildTwigSignals(
            [
                [
                    'template' => 'components/_badge.html.twig',
                    'total_ms' => 5.0,
                    'renders'  => 15,
                ],
            ],
        );

        $result = $this->engine->evaluate(
            $this->buildDbSignals(),
            $this->buildCacheSignals(),
            $twig,
            $this->buildEventSignals(),
            $this->buildHttpSignals(),
            $this->buildOtherSignals(),
        );

        self::assertCount(1, $result);
        self::assertSame(OpportunityCode::TWIG_DUP_RENDER->value, $result[0]['code']);
        self::assertSame(OpportunityCategory::TWIG->value, $result[0]['category']);
        self::assertSame(2, $result[0]['impact']);
    }

    public function testDetectsTooManyListeners(): void
    {
        $events = $this->buildEventSignals(
            [
                [
                    'event'    => 'kernel.request',
                    'listener' => 'App\\Listener\\SecurityListener',
                    'calls'    => 55,
                    'max_ms'   => 2.0,
                    'avg_ms'   => 1.0,
                ],
            ],
        );

        $result = $this->engine->evaluate(
            $this->buildDbSignals(),
            $this->buildCacheSignals(),
            $this->buildTwigSignals(),
            $events,
            $this->buildHttpSignals(),
            $this->buildOtherSignals(),
        );

        self::assertCount(1, $result);
        self::assertSame(OpportunityCode::EVENTS_TOO_MANY_LISTENERS->value, $result[0]['code']);
        self::assertSame(OpportunityCategory::EVENTS->value, $result[0]['category']);
        self::assertSame(Risk::MED->value, $result[0]['risk']);
    }

    public function testDetectsSlowListener(): void
    {
        $events = $this->buildEventSignals(
            [
                [
                    'event'    => 'order.created',
                    'listener' => 'App\\Listener\\SendEmailListener',
                    'calls'    => 1,
                    'max_ms'   => 25.0,
                    'avg_ms'   => 25.0,
                ],
            ],
        );

        $result = $this->engine->evaluate(
            $this->buildDbSignals(),
            $this->buildCacheSignals(),
            $this->buildTwigSignals(),
            $events,
            $this->buildHttpSignals(),
            $this->buildOtherSignals(),
        );

        self::assertCount(1, $result);
        self::assertSame(OpportunityCode::EVENTS_SLOW_LISTENER->value, $result[0]['code']);
        self::assertSame(OpportunityCategory::EVENTS->value, $result[0]['category']);
        self::assertSame(4, $result[0]['impact']);
        self::assertSame(Risk::LOW->value, $result[0]['risk']);
    }

    public function testDetectsHttpSlowEndpoint(): void
    {
        $http = $this->buildHttpSignals(
            [
                [
                    'endpoint_fingerprint' => 'GET /api/external/data',
                    'calls'                => 1,
                    'max_ms'               => 750.0,
                ],
            ],
        );

        $result = $this->engine->evaluate(
            $this->buildDbSignals(),
            $this->buildCacheSignals(),
            $this->buildTwigSignals(),
            $this->buildEventSignals(),
            $http,
            $this->buildOtherSignals(),
        );

        self::assertCount(1, $result);
        self::assertSame(OpportunityCode::HTTP_SLOW_ENDPOINT->value, $result[0]['code']);
        self::assertSame(OpportunityCategory::HTTP->value, $result[0]['category']);
        self::assertSame(4, $result[0]['impact']);
        self::assertSame(Risk::MED->value, $result[0]['risk']);
    }

    public function testDetectsHttpDupCall(): void
    {
        $http = $this->buildHttpSignals(
            [
                [
                    'endpoint_fingerprint' => 'POST /api/notify',
                    'calls'                => 3,
                    'max_ms'               => 100.0,
                ],
            ],
        );

        $result = $this->engine->evaluate(
            $this->buildDbSignals(),
            $this->buildCacheSignals(),
            $this->buildTwigSignals(),
            $this->buildEventSignals(),
            $http,
            $this->buildOtherSignals(),
        );

        self::assertCount(1, $result);
        self::assertSame(OpportunityCode::HTTP_DUP_CALL->value, $result[0]['code']);
        self::assertSame(OpportunityCategory::HTTP->value, $result[0]['category']);
        self::assertSame(5, $result[0]['confidence']);
        self::assertSame(Risk::LOW->value, $result[0]['risk']);
    }

    public function testDetectsMessengerSyncHeavy(): void
    {
        $other = $this->buildOtherSignals(
            [
                [
                    'handler_class' => 'App\\Handler\\HeavySyncHandler',
                    'duration_ms'   => 80.0,
                ],
            ],
        );

        $result = $this->engine->evaluate(
            $this->buildDbSignals(),
            $this->buildCacheSignals(),
            $this->buildTwigSignals(),
            $this->buildEventSignals(),
            $this->buildHttpSignals(),
            $other,
        );

        self::assertCount(1, $result);
        self::assertSame(OpportunityCode::MESSENGER_SYNC_HEAVY->value, $result[0]['code']);
        self::assertSame(OpportunityCategory::MESSENGER->value, $result[0]['category']);
        self::assertSame(4, $result[0]['impact']);
        self::assertSame(Risk::LOW->value, $result[0]['risk']);
    }

    public function testRoiCalculation(): void
    {
        $db = $this->buildDbSignals(
            [
                [
                    'fingerprint' => 'fp_roi_test',
                    'pattern'     => 'UPDATE inventory SET quantity = ? WHERE id = ?',
                    'total_ms'    => 50.0,
                    'count'       => 1,
                    'kind'        => 'UPDATE',
                ],
            ],
        );

        $result = $this->engine->evaluate($db, ...self::emptyRemainingSignals());

        self::assertNotEmpty($result);

        $opportunity = $result[0];
        $impact = $opportunity['impact'];
        $confidence = $opportunity['confidence'];
        $effort = $opportunity['effort'];
        self::assertIsInt($impact);
        self::assertIsInt($confidence);
        self::assertIsInt($effort);
        $expectedRoi = $impact * $confidence / $effort;

        self::assertSame($expectedRoi, $opportunity['roi']);
    }

    public function testDeduplicatesByFingerprint(): void
    {
        $db = $this->buildDbSignals(
            [
                [
                    'fingerprint' => 'fp_same',
                    'pattern'     => 'SELECT t0_.id FROM users t0_',
                    'total_ms'    => 40.0,
                    'count'       => 5,
                    'avg_ms'      => 8.0,
                    'kind'        => 'SELECT',
                ],
                [
                    'fingerprint' => 'fp_same',
                    'pattern'     => 'SELECT t0_.id FROM users t0_',
                    'total_ms'    => 60.0,
                    'count'       => 5,
                    'avg_ms'      => 12.0,
                    'kind'        => 'SELECT',
                ],
            ],
        );

        $result = $this->engine->evaluate($db, ...self::emptyRemainingSignals());

        $slowGroups = $this->filterByCode($result, OpportunityCode::PG_SLOW_QUERY_GROUP);
        $duplicates = $this->filterByCode($result, OpportunityCode::PG_DUPLICATE_QUERY);
        $cacheCandidates = $this->filterByCode($result, OpportunityCode::CACHE_CANDIDATE_DB_RESULTS);

        // Each rule type uses fingerprint for dedup, so identical fingerprints produce 1 each
        self::assertCount(1, $slowGroups);
        self::assertCount(1, $duplicates);
        self::assertCount(1, $cacheCandidates);
    }

    public function testTruncatesToMaxItems(): void
    {
        $engine = new AdvisorEngine(maxItems: 3);

        // Create many query groups that each trigger duplicate detection (count >= 3)
        $groups = [];

        for ($i = 0; $i < 10; $i++) {
            $groups[] = [
                'fingerprint' => 'fp_trunc_' . $i,
                'pattern'     => 'INSERT INTO log_' . $i . ' (id) VALUES (?)',
                'total_ms'    => 5.0,
                'count'       => 3,
                'kind'        => 'INSERT',
            ];
        }

        $db = $this->buildDbSignals($groups);
        $result = $engine->evaluate($db, ...self::emptyRemainingSignals());

        self::assertCount(3, $result);
    }

    public function testInfraAndProfilerOriginItemsDoNotTriggerOpportunities(): void
    {
        $db = $this->buildDbSignals(
            [
                [
                    'fingerprint' => 'fp_infra_slow',
                    'pattern'     => 'SELECT t0_.id FROM cache_items t0_',
                    'total_ms'    => 100.0,
                    'count'       => 15,
                    'avg_ms'      => 6.7,
                    'max_ms'      => 100.0,
                    'kind'        => 'SELECT',
                    'origin'      => 'infra',
                ],
                [
                    'fingerprint' => 'fp_profiler_slow',
                    'pattern'     => 'SELECT t0_.id FROM sf_profiler t0_',
                    'total_ms'    => 120.0,
                    'count'       => 20,
                    'avg_ms'      => 6.0,
                    'max_ms'      => 120.0,
                    'kind'        => 'SELECT',
                    'origin'      => 'profiler',
                ],
            ],
        );

        $cache = $this->buildCacheSignals(
            [
                [
                    'pool'     => 'cache.system',
                    'hit_rate' => 10.0,
                    'calls'    => 100,
                    'origin'   => 'infra',
                ],
                [
                    'pool'     => 'cache.profiler',
                    'hit_rate' => 5.0,
                    'calls'    => 50,
                    'origin'   => 'profiler',
                ],
            ],
        );

        $twig = $this->buildTwigSignals(
            [
                [
                    'template' => '@WebProfiler/Profiler/toolbar.html.twig',
                    'total_ms' => 200.0,
                    'renders'  => 50,
                    'origin'   => 'infra',
                ],
                [
                    'template' => '@WebProfiler/Collector/data.html.twig',
                    'total_ms' => 150.0,
                    'renders'  => 30,
                    'origin'   => 'profiler',
                ],
            ],
        );

        $events = $this->buildEventSignals(
            [
                [
                    'event'    => 'kernel.request',
                    'listener' => 'SessionListener',
                    'calls'    => 100,
                    'max_ms'   => 50.0,
                    'avg_ms'   => 25.0,
                    'origin'   => 'infra',
                ],
                [
                    'event'    => 'kernel.response',
                    'listener' => 'ProfilerListener',
                    'calls'    => 80,
                    'max_ms'   => 40.0,
                    'avg_ms'   => 20.0,
                    'origin'   => 'profiler',
                ],
            ],
        );

        $other = $this->buildOtherSignals(
            [
                [
                    'handler_class' => 'Symfony\\Handler\\InfraHandler',
                    'duration_ms'   => 200.0,
                    'origin'        => 'infra',
                ],
                [
                    'handler_class' => 'Symfony\\Profiler\\Handler\\ProfilerHandler',
                    'duration_ms'   => 150.0,
                    'origin'        => 'profiler',
                ],
            ],
        );

        $result = $this->engine->evaluate(
            $db,
            $cache,
            $twig,
            $events,
            $this->buildHttpSignals(),
            $other,
        );

        self::assertCount(0, $result);
    }

    /**
     * @param array<int, array<string, mixed>> $queryGroups
     *
     * @return array<string, mixed>
     */
    private function buildDbSignals(array $queryGroups = []): array
    {
        return [
            'query_groups'  => $queryGroups,
            'total_queries' => array_sum(array_column($queryGroups, 'count')),
            'total_db_ms'   => array_sum(array_column($queryGroups, 'total_ms')),
            'connections'   => 1,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $pools
     *
     * @return array<string, mixed>
     */
    private function buildCacheSignals(array $pools = []): array
    {
        return [
            'pools'          => $pools,
            'total_calls'    => array_sum(array_column($pools, 'calls')),
            'total_cache_ms' => 0.0,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $templates
     *
     * @return array<string, mixed>
     */
    private function buildTwigSignals(array $templates = []): array
    {
        return [
            'templates'     => $templates,
            'total_renders' => array_sum(array_column($templates, 'renders')),
            'total_twig_ms' => array_sum(array_column($templates, 'total_ms')),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $listeners
     *
     * @return array<string, mixed>
     */
    private function buildEventSignals(array $listeners = []): array
    {
        return [
            'listeners'       => $listeners,
            'total_calls'     => array_sum(array_column($listeners, 'calls')),
            'total_events_ms' => 0.0,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $calls
     *
     * @return array<string, mixed>
     */
    private function buildHttpSignals(array $calls = []): array
    {
        return [
            'calls'            => $calls,
            'total_http_calls' => array_sum(array_column($calls, 'calls')),
            'total_http_ms'    => 0.0,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $syncHandlers
     *
     * @return array<string, mixed>
     */
    private function buildOtherSignals(array $syncHandlers = []): array
    {
        return [
            'sync_handlers' => $syncHandlers,
            'total_sync_ms' => array_sum(array_column($syncHandlers, 'duration_ms')),
        ];
    }

    /**
     * @return array{
     *     0: array<string, mixed>,
     *     1: array<string, mixed>,
     *     2: array<string, mixed>,
     *     3: array<string, mixed>,
     *     4: array<string, mixed>,
     * }
     */
    private static function emptyRemainingSignals(): array
    {
        return [
            ['pools' => [], 'total_calls' => 0, 'total_cache_ms' => 0.0],
            ['templates' => [], 'total_renders' => 0, 'total_twig_ms' => 0.0],
            ['listeners' => [], 'total_calls' => 0, 'total_events_ms' => 0.0],
            ['calls' => [], 'total_http_calls' => 0, 'total_http_ms' => 0.0],
            ['sync_handlers' => [], 'total_sync_ms' => 0.0],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $results
     *
     * @return array<int, array<string, mixed>>
     */
    private function filterByCode(array $results, OpportunityCode $code): array
    {
        return array_values(
            array_filter(
                $results,
                static fn (array $item): bool => $item['code'] === $code->value,
            ),
        );
    }
}
