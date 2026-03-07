<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Analyzer;

use MartinKup\OptimizationAdvisorBundle\Enum\DataOrigin;
use MartinKup\OptimizationAdvisorBundle\Sql\SqlNormalizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DatabaseAnalyzer
{
    /** PostgreSQL system catalog prefixes — stable across all PostgreSQL versions. */
    private const array INFRA_TABLE_PREFIXES = ['pg_', 'information_schema'];

    /** PostgreSQL built-in function calls that indicate schema introspection. */
    private const array INFRA_SQL_PATTERNS = ['CURRENT_DATABASE()', 'CURRENT_SCHEMA()'];

    /** @param array<int, string> $infraTables */
    public function __construct(
        private SqlNormalizer $sqlNormalizer,
        #[Autowire('%optimization_advisor.infra_db_tables%')]
        private array $infraTables = ['doctrine_migration_versions'],
    ) {
    }

    /**
     * Analyze database connection data and group queries by fingerprint.
     *
     * @param array<string, array<int, array<string, mixed>>> $connectionData
     *
     * @return array{
     *     query_groups: array<int, array{
     *         pattern: string,
     *         example_sql: string,
     *         example_params: array<array-key, mixed>,
     *         example_types: array<array-key, mixed>,
     *         fingerprint: string,
     *         kind: string,
     *         tables: array<int, string>,
     *         connection: string,
     *         origin: string,
     *         count: int,
     *         total_ms: float,
     *         max_ms: float,
     *         min_ms: float,
     *         avg_ms: float,
     *     }>,
     *     total_queries: int,
     *     total_db_ms: float,
     *     app_queries: int,
     *     app_db_ms: float,
     *     app_patterns: int,
     *     infra_queries: int,
     *     infra_db_ms: float,
     *     infra_patterns: int,
     *     profiler_queries: int,
     *     profiler_db_ms: float,
     *     profiler_patterns: int,
     *     connections: int,
     *     unique_patterns: int,
     *     select_count: int,
     *     insert_count: int,
     *     update_count: int,
     *     delete_count: int,
     *     other_count: int,
     * }
     */
    public function analyze(array $connectionData): array
    {
        /** @var array<string, array{
         *     pattern: string,
         *     example_sql: string,
         *     example_params: array<array-key, mixed>,
         *     example_types: array<array-key, mixed>,
         *     fingerprint: string,
         *     kind: string,
         *     tables: array<int, string>,
         *     connection: string,
         *     count: int,
         *     total_ms: float,
         *     max_ms: float,
         *     min_ms: float,
         * }> $groups
         */
        $groups = [];
        $connections = array_keys($connectionData);

        foreach ($connectionData as $connectionName => $queries) {
            foreach ($queries as $query) {
                $sql = is_string($query['sql'] ?? null) ? $query['sql'] : '';
                $rawMs = $query['executionMS'] ?? 0.0;
                $executionMs = (is_float($rawMs) || is_int($rawMs) ? (float) $rawMs : 0.0) * 1000.0;
                $rawParams = is_array($query['params'] ?? null) ? $query['params'] : [];
                $rawTypes = is_array($query['types'] ?? null) ? $query['types'] : [];

                $fingerprint = $this->sqlNormalizer->fingerprint($sql);
                $groupKey = $connectionName . '::' . $fingerprint;

                if (isset($groups[$groupKey])) {
                    $groups[$groupKey]['count'] += 1;
                    $groups[$groupKey]['total_ms'] += $executionMs;
                    $groups[$groupKey]['max_ms'] = max($groups[$groupKey]['max_ms'], $executionMs);
                    $groups[$groupKey]['min_ms'] = min($groups[$groupKey]['min_ms'], $executionMs);
                } else {
                    $groups[$groupKey] = [
                        'pattern'        => $this->sqlNormalizer->normalize($sql),
                        'example_sql'    => $sql,
                        'example_params' => $rawParams,
                        'example_types'  => $rawTypes,
                        'fingerprint'    => $fingerprint,
                        'kind'           => $this->sqlNormalizer->extractKind($sql),
                        'tables'         => $this->sqlNormalizer->extractTables($sql),
                        'connection'     => $connectionName,
                        'count'          => 1,
                        'total_ms'       => $executionMs,
                        'max_ms'         => $executionMs,
                        'min_ms'         => $executionMs,
                    ];
                }
            }
        }

        $queryGroups = array_values($groups);

        usort(
            $queryGroups,
            static fn (array $a, array $b): int => $b['total_ms'] <=> $a['total_ms'],
        );

        $result = [];
        $appQueries = 0;
        $appDbMs = 0.0;
        $appPatterns = 0;
        $infraQueries = 0;
        $infraDbMs = 0.0;
        $infraPatterns = 0;

        foreach ($queryGroups as $group) {
            $origin = $this->classifyOrigin($group['tables'], $group['example_sql']);

            if ($origin === DataOrigin::APP) {
                $appQueries += $group['count'];
                $appDbMs += $group['total_ms'];
                $appPatterns += 1;
            } else {
                $infraQueries += $group['count'];
                $infraDbMs += $group['total_ms'];
                $infraPatterns += 1;
            }

            $result[] = [
                'pattern'        => $group['pattern'],
                'example_sql'    => $group['example_sql'],
                'example_params' => $group['example_params'],
                'example_types'  => $group['example_types'],
                'fingerprint'    => $group['fingerprint'],
                'kind'           => $group['kind'],
                'tables'         => $group['tables'],
                'connection'     => $group['connection'],
                'origin'         => $origin->value,
                'count'          => $group['count'],
                'total_ms'       => $group['total_ms'],
                'max_ms'         => $group['max_ms'],
                'min_ms'         => $group['min_ms'],
                'avg_ms'         => $group['count'] > 0 ? $group['total_ms'] / $group['count'] : 0.0,
            ];
        }

        $kindCounts = ['SELECT' => 0, 'INSERT' => 0, 'UPDATE' => 0, 'DELETE' => 0, 'OTHER' => 0];

        foreach ($result as $group) {
            if ($group['origin'] !== DataOrigin::APP->value) {
                continue;
            }

            $kind = $group['kind'];
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + $group['count'];
        }

        return [
            'query_groups'      => $result,
            'total_queries'     => $appQueries + $infraQueries,
            'total_db_ms'       => $appDbMs + $infraDbMs,
            'app_queries'       => $appQueries,
            'app_db_ms'         => $appDbMs,
            'app_patterns'      => $appPatterns,
            'infra_queries'     => $infraQueries,
            'infra_db_ms'       => $infraDbMs,
            'infra_patterns'    => $infraPatterns,
            'profiler_queries'  => 0,
            'profiler_db_ms'    => 0.0,
            'profiler_patterns' => 0,
            'connections'       => count($connections),
            'unique_patterns'   => count($result),
            'select_count'      => $kindCounts['SELECT'],
            'insert_count'      => $kindCounts['INSERT'],
            'update_count'      => $kindCounts['UPDATE'],
            'delete_count'      => $kindCounts['DELETE'],
            'other_count'       => $kindCounts['OTHER'],
        ];
    }

    /** @param array<int, string> $tables */
    private function classifyOrigin(array $tables, string $sql): DataOrigin
    {
        $upperSql = strtoupper($sql);

        foreach (self::INFRA_SQL_PATTERNS as $pattern) {
            if (str_contains($upperSql, $pattern)) {
                return DataOrigin::INFRA;
            }
        }

        foreach ($tables as $table) {
            $lowerTable = strtolower($table);

            foreach (self::INFRA_TABLE_PREFIXES as $prefix) {
                if (str_starts_with($lowerTable, $prefix)) {
                    return DataOrigin::INFRA;
                }
            }

            if (in_array($lowerTable, $this->infraTables, true)) {
                return DataOrigin::INFRA;
            }
        }

        return DataOrigin::APP;
    }
}
