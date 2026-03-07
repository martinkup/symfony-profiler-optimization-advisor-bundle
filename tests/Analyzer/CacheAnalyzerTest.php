<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Analyzer;

use MartinKup\OptimizationAdvisorBundle\Analyzer\CacheAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CacheAnalyzer pool statistics transformation, aggregation, and origin classification.
 *
 * Test Coverage:
 * - Transforming pool statistics into per-pool metrics (calls, reads, writes, deletes, hits, misses, hit_rate)
 * - Converting seconds to milliseconds for pool timing
 * - Computing global hit rate (app pools only) and aggregate totals across all pools
 * - Tracking total hits, misses, reads, writes, deletes, and pool count
 * - Tracking APP-scoped hits, misses, reads, writes, deletes counters
 * - Handling zero reads gracefully (hit_rate = null)
 * - Returning empty pools and zero aggregates for empty input
 * - Classifying pools as APP, INFRA, or PROFILER by configured prefix
 * - Global hit rate only considers APP pools
 * - Profiler-origin pools excluded from total_cache_calls, total_cache_ms, and total_hits/misses/reads/writes/deletes
 *
 * @see CacheAnalyzer
 */
#[CoversClass(CacheAnalyzer::class)]
#[Group('unit')]
final class CacheAnalyzerTest extends TestCase
{
    private CacheAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new CacheAnalyzer();
    }

    public function testAnalyzeTransformsPoolStats(): void
    {
        $poolStatistics = [
            'cache.app' => $this->buildPoolStats(reads: 100, writes: 10, deletes: 5, hits: 80, misses: 20, time: 0.05),
        ];

        $result = $this->analyzer->analyze($poolStatistics);

        self::assertCount(1, $result['pools']);

        $pool = $result['pools'][0];

        self::assertSame('cache.app', $pool['pool']);
        self::assertSame('app', $pool['origin']);
        self::assertSame(115, $pool['calls']);
        self::assertSame(100, $pool['reads']);
        self::assertSame(10, $pool['writes']);
        self::assertSame(5, $pool['deletes']);
        self::assertSame(80, $pool['hits']);
        self::assertSame(20, $pool['misses']);
        self::assertEqualsWithDelta(80.0, $pool['hit_rate'], 0.01);

        self::assertSame(80, $result['app_hits']);
        self::assertSame(100, $result['app_reads']);
        self::assertSame(20, $result['app_misses']);
        self::assertSame(10, $result['app_writes']);
        self::assertSame(5, $result['app_deletes']);
    }

    public function testAnalyzeConvertsSecondsToMs(): void
    {
        $poolStatistics = [
            'cache.system' => $this->buildPoolStats(reads: 10, writes: 0, deletes: 0, hits: 5, misses: 5, time: 0.123),
        ];

        $result = $this->analyzer->analyze($poolStatistics);

        self::assertEqualsWithDelta(123.0, $result['pools'][0]['total_ms'], 0.01);
        self::assertEqualsWithDelta(123.0, $result['total_cache_ms'], 0.01);
        self::assertSame('infra', $result['pools'][0]['origin']);
    }

    public function testAnalyzeComputesGlobalHitRate(): void
    {
        $poolStatistics = [
            'cache.app.pool_a' => $this->buildPoolStats(
                reads: 100,
                writes: 0,
                deletes: 0,
                hits: 90,
                misses: 10,
                time: 0.01,
            ),
            'cache.app.pool_b' => $this->buildPoolStats(
                reads: 50,
                writes: 0,
                deletes: 0,
                hits: 10,
                misses: 40,
                time: 0.02,
            ),
        ];

        $result = $this->analyzer->analyze($poolStatistics);

        self::assertSame(150, $result['total_cache_calls']);
        self::assertSame(100, $result['total_hits']);
        self::assertSame(50, $result['total_misses']);
        self::assertSame(150, $result['total_reads']);
        self::assertSame(0, $result['total_writes']);
        self::assertSame(0, $result['total_deletes']);
        self::assertSame(2, $result['pool_count']);
        self::assertNotNull($result['global_hit_rate']);
        // Global: 100 hits / 150 reads = 66.67% (app pools only)
        self::assertEqualsWithDelta(66.67, $result['global_hit_rate'], 0.01);
    }

    public function testAnalyzeHandlesZeroReads(): void
    {
        $poolStatistics = [
            'cache.app.write_only' => $this->buildPoolStats(
                reads: 0,
                writes: 50,
                deletes: 10,
                hits: 0,
                misses: 0,
                time: 0.01,
            ),
        ];

        $result = $this->analyzer->analyze($poolStatistics);

        self::assertNull($result['pools'][0]['hit_rate']);
        self::assertNull($result['global_hit_rate']);
        self::assertSame(60, $result['total_cache_calls']);
    }

    public function testAnalyzeEmptyPools(): void
    {
        $result = $this->analyzer->analyze([]);

        self::assertSame([], $result['pools']);
        self::assertSame(0, $result['total_cache_calls']);
        self::assertSame(0.0, $result['total_cache_ms']);
        self::assertSame(0, $result['total_hits']);
        self::assertSame(0, $result['total_misses']);
        self::assertSame(0, $result['total_reads']);
        self::assertSame(0, $result['total_writes']);
        self::assertSame(0, $result['total_deletes']);
        self::assertSame(0, $result['pool_count']);
        self::assertNull($result['global_hit_rate']);
        self::assertSame(0, $result['app_cache_calls']);
        self::assertSame(0.0, $result['app_cache_ms']);
        self::assertSame(0, $result['app_hits']);
        self::assertSame(0, $result['app_reads']);
        self::assertSame(0, $result['app_misses']);
        self::assertSame(0, $result['app_writes']);
        self::assertSame(0, $result['app_deletes']);
        self::assertSame(0, $result['infra_cache_calls']);
        self::assertSame(0.0, $result['infra_cache_ms']);
        self::assertSame(0, $result['profiler_cache_calls']);
        self::assertSame(0.0, $result['profiler_cache_ms']);
    }

    public function testClassifiesPoolsByOrigin(): void
    {
        $poolStatistics = [
            'cache.app'             => $this->buildPoolStats(
                reads: 10,
                writes: 5,
                deletes: 0,
                hits: 8,
                misses: 2,
                time: 0.01,
            ),
            'cache.doctrine.result' => $this->buildPoolStats(
                reads: 20,
                writes: 2,
                deletes: 0,
                hits: 15,
                misses: 5,
                time: 0.02,
            ),
            'cache.system'          => $this->buildPoolStats(
                reads: 50,
                writes: 0,
                deletes: 0,
                hits: 45,
                misses: 5,
                time: 0.03,
            ),
            'cache.validator'       => $this->buildPoolStats(
                reads: 30,
                writes: 0,
                deletes: 0,
                hits: 25,
                misses: 5,
                time: 0.01,
            ),
        ];

        $result = $this->analyzer->analyze($poolStatistics);

        self::assertSame('app', $result['pools'][0]['origin']);
        self::assertSame('app', $result['pools'][1]['origin']);
        self::assertSame('infra', $result['pools'][2]['origin']);
        self::assertSame('infra', $result['pools'][3]['origin']);

        self::assertSame(37, $result['app_cache_calls']);
        self::assertSame(80, $result['infra_cache_calls']);
        self::assertSame(0, $result['profiler_cache_calls']);
        self::assertSame(0.0, $result['profiler_cache_ms']);
    }

    public function testClassifiesProfilerPoolsByOrigin(): void
    {
        $poolStatistics = [
            'cache.app'      => $this->buildPoolStats(reads: 10, writes: 0, deletes: 0, hits: 8, misses: 2, time: 0.01),
            'cache.profiler' => $this->buildPoolStats(reads: 5, writes: 3, deletes: 0, hits: 4, misses: 1, time: 0.005),
            'cache.system'   => $this->buildPoolStats(
                reads: 20,
                writes: 0,
                deletes: 0,
                hits: 15,
                misses: 5,
                time: 0.02,
            ),
        ];

        $result = $this->analyzer->analyze($poolStatistics);

        self::assertSame('app', $result['pools'][0]['origin']);
        self::assertSame('profiler', $result['pools'][1]['origin']);
        self::assertSame('infra', $result['pools'][2]['origin']);

        self::assertSame(10, $result['app_cache_calls']);
        self::assertSame(20, $result['infra_cache_calls']);
        self::assertSame(8, $result['profiler_cache_calls']);

        // total_cache_calls = app + infra (profiler excluded)
        self::assertSame(30, $result['total_cache_calls']);

        // total_hits/misses/reads/writes/deletes exclude PROFILER
        // APP: hits=8, misses=2, reads=10, writes=0, deletes=0
        // INFRA: hits=15, misses=5, reads=20, writes=0, deletes=0
        // PROFILER: hits=4, misses=1, reads=5, writes=3, deletes=0 (excluded)
        self::assertSame(23, $result['total_hits']);
        self::assertSame(7, $result['total_misses']);
        self::assertSame(30, $result['total_reads']);
        self::assertSame(0, $result['total_writes']);
        self::assertSame(0, $result['total_deletes']);

        // APP-scoped counters
        self::assertSame(8, $result['app_hits']);
        self::assertSame(10, $result['app_reads']);
        self::assertSame(2, $result['app_misses']);
        self::assertSame(0, $result['app_writes']);
        self::assertSame(0, $result['app_deletes']);
    }

    public function testGlobalHitRateUsesOnlyAppPools(): void
    {
        $poolStatistics = [
            'cache.app'    => $this->buildPoolStats(reads: 10, writes: 0, deletes: 0, hits: 5, misses: 5, time: 0.01),
            'cache.system' => $this->buildPoolStats(reads: 100, writes: 0, deletes: 0, hits: 99, misses: 1, time: 0.05),
        ];

        $result = $this->analyzer->analyze($poolStatistics);

        // Global hit rate should only consider APP pools (cache.app: 5 hits / 10 reads = 50%)
        // NOT include cache.system (INFRA: 99 hits / 100 reads = 99%)
        self::assertNotNull($result['global_hit_rate']);
        self::assertEqualsWithDelta(50.0, $result['global_hit_rate'], 0.01);
    }

    /** @return array{calls: int, reads: int, writes: int, deletes: int, hits: int, misses: int, time: float} */
    private function buildPoolStats(int $reads, int $writes, int $deletes, int $hits, int $misses, float $time): array
    {
        return [
            'calls'   => $reads + $writes + $deletes,
            'reads'   => $reads,
            'writes'  => $writes,
            'deletes' => $deletes,
            'hits'    => $hits,
            'misses'  => $misses,
            'time'    => $time,
        ];
    }
}
