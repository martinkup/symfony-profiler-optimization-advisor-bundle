<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Analyzer;

use MartinKup\OptimizationAdvisorBundle\Enum\DataOrigin;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class CacheAnalyzer
{
    /**
     * @param array<int, string> $appPoolPrefixes
     * @param array<int, string> $profilerPoolPrefixes
     */
    public function __construct(
        #[Autowire('%optimization_advisor.app_cache_pool_prefixes%')]
        private array $appPoolPrefixes = ['cache.app', 'cache.doctrine.result', 'cache.doctrine.orm'],
        #[Autowire('%optimization_advisor.profiler_cache_pool_prefixes%')]
        private array $profilerPoolPrefixes = ['cache.profiler'],
    ) {
    }

    /**
     * Analyze cache pool statistics and compute per-pool and global metrics.
     *
     * @param array<string, array{
     *     calls: int,
     *     reads: int,
     *     writes: int,
     *     deletes: int,
     *     hits: int,
     *     misses: int,
     *     time: float,
     * }> $poolStatistics
     *
     * @return array{
     *     pools: array<int, array{
     *         pool: string,
     *         origin: string,
     *         calls: int,
     *         reads: int,
     *         writes: int,
     *         deletes: int,
     *         hits: int,
     *         misses: int,
     *         hit_rate: ?float,
     *         total_ms: float,
     *     }>,
     *     total_cache_calls: int,
     *     total_cache_ms: float,
     *     total_hits: int,
     *     total_misses: int,
     *     total_reads: int,
     *     total_writes: int,
     *     total_deletes: int,
     *     pool_count: int,
     *     global_hit_rate: ?float,
     *     app_cache_calls: int,
     *     app_cache_ms: float,
     *     app_hits: int,
     *     app_reads: int,
     *     app_misses: int,
     *     app_writes: int,
     *     app_deletes: int,
     *     infra_cache_calls: int,
     *     infra_cache_ms: float,
     *     profiler_cache_calls: int,
     *     profiler_cache_ms: float,
     * }
     */
    public function analyze(array $poolStatistics): array
    {
        $pools = [];
        $totalHits = 0;
        $totalMisses = 0;
        $totalReads = 0;
        $totalWrites = 0;
        $totalDeletes = 0;
        $appCacheCalls = 0;
        $appCacheMs = 0.0;
        $infraCacheCalls = 0;
        $infraCacheMs = 0.0;
        $profilerCacheCalls = 0;
        $profilerCacheMs = 0.0;
        $appHits = 0;
        $appReads = 0;
        $appMisses = 0;
        $appWrites = 0;
        $appDeletes = 0;

        foreach ($poolStatistics as $poolName => $stats) {
            $reads = $stats['reads'];
            $writes = $stats['writes'];
            $deletes = $stats['deletes'];
            $calls = $reads + $writes + $deletes;
            $hits = $stats['hits'];
            $misses = $stats['misses'];
            $totalMs = $stats['time'] * 1000.0;
            $origin = $this->classifyPool($poolName);

            $hitRate = $reads > 0 ? $hits / $reads * 100.0 : null;

            $pools[] = [
                'pool'     => $poolName,
                'origin'   => $origin->value,
                'calls'    => $calls,
                'reads'    => $reads,
                'writes'   => $writes,
                'deletes'  => $deletes,
                'hits'     => $hits,
                'misses'   => $misses,
                'hit_rate' => $hitRate,
                'total_ms' => $totalMs,
            ];

            if ($origin === DataOrigin::APP) {
                $appCacheCalls += $calls;
                $appCacheMs += $totalMs;
                $appHits += $hits;
                $appReads += $reads;
                $appMisses += $misses;
                $appWrites += $writes;
                $appDeletes += $deletes;
                $totalHits += $hits;
                $totalMisses += $misses;
                $totalReads += $reads;
                $totalWrites += $writes;
                $totalDeletes += $deletes;
            } elseif ($origin === DataOrigin::PROFILER) {
                $profilerCacheCalls += $calls;
                $profilerCacheMs += $totalMs;
            } else {
                $infraCacheCalls += $calls;
                $infraCacheMs += $totalMs;
                $totalHits += $hits;
                $totalMisses += $misses;
                $totalReads += $reads;
                $totalWrites += $writes;
                $totalDeletes += $deletes;
            }
        }

        $globalHitRate = $appReads > 0 ? $appHits / $appReads * 100.0 : null;

        return [
            'pools'                => $pools,
            'total_cache_calls'    => $appCacheCalls + $infraCacheCalls,
            'total_cache_ms'       => $appCacheMs + $infraCacheMs,
            'total_hits'           => $totalHits,
            'total_misses'         => $totalMisses,
            'total_reads'          => $totalReads,
            'total_writes'         => $totalWrites,
            'total_deletes'        => $totalDeletes,
            'pool_count'           => count($pools),
            'global_hit_rate'      => $globalHitRate,
            'app_cache_calls'      => $appCacheCalls,
            'app_cache_ms'         => $appCacheMs,
            'app_hits'             => $appHits,
            'app_reads'            => $appReads,
            'app_misses'           => $appMisses,
            'app_writes'           => $appWrites,
            'app_deletes'          => $appDeletes,
            'infra_cache_calls'    => $infraCacheCalls,
            'infra_cache_ms'       => $infraCacheMs,
            'profiler_cache_calls' => $profilerCacheCalls,
            'profiler_cache_ms'    => $profilerCacheMs,
        ];
    }

    private function classifyPool(string $poolName): DataOrigin
    {
        foreach ($this->profilerPoolPrefixes as $prefix) {
            if (str_starts_with($poolName, $prefix)) {
                return DataOrigin::PROFILER;
            }
        }

        foreach ($this->appPoolPrefixes as $prefix) {
            if (str_starts_with($poolName, $prefix)) {
                return DataOrigin::APP;
            }
        }

        return DataOrigin::INFRA;
    }
}
