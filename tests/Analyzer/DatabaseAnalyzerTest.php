<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Analyzer;

use MartinKup\OptimizationAdvisorBundle\Analyzer\DatabaseAnalyzer;
use MartinKup\OptimizationAdvisorBundle\Sql\SqlNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DatabaseAnalyzer query grouping, timing aggregation, and origin classification.
 *
 * Test Coverage:
 * - Grouping queries by fingerprint via SqlNormalizer
 * - Computing timing statistics (total_ms, max_ms, min_ms, avg_ms)
 * - Handling multiple connections with separate counting
 * - Sorting query groups by total_ms descending
 * - Extracting SQL kind (SELECT/INSERT/UPDATE/DELETE) and tables
 * - Returning empty groups for empty input data
 * - Handling a single query correctly
 * - Classifying queries as APP or INFRA by table prefix, SQL pattern, and configured table
 * - Kind counts only count APP-origin query groups
 * - Profiler zero-value fields (profiler_queries, profiler_db_ms, profiler_patterns) always 0
 *
 * @see DatabaseAnalyzer
 */
#[CoversClass(DatabaseAnalyzer::class)]
#[Group('unit')]
final class DatabaseAnalyzerTest extends TestCase
{
    private DatabaseAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new DatabaseAnalyzer(new SqlNormalizer());
    }

    public function testAnalyzeGroupsByFingerprint(): void
    {
        $connectionData = [
            'default' => [
                $this->buildQuery("SELECT * FROM users WHERE id = 1", 0.005),
                $this->buildQuery("SELECT * FROM users WHERE id = 42", 0.003),
                $this->buildQuery("SELECT * FROM orders WHERE status = 'active'", 0.010),
            ],
        ];

        $result = $this->analyzer->analyze($connectionData);

        self::assertCount(2, $result['query_groups']);
        self::assertSame(3, $result['total_queries']);
        self::assertSame(3, $result['app_queries']);
        self::assertSame(0, $result['infra_queries']);
        self::assertSame(2, $result['app_patterns']);
        self::assertSame(0, $result['infra_patterns']);
        self::assertSame(0, $result['profiler_queries']);
        self::assertSame(0.0, $result['profiler_db_ms']);
        self::assertSame(0, $result['profiler_patterns']);
    }

    public function testAnalyzeComputesTimingStats(): void
    {
        $connectionData = [
            'default' => [
                $this->buildQuery('SELECT * FROM users WHERE id = 1', 0.010),
                $this->buildQuery('SELECT * FROM users WHERE id = 2', 0.030),
                $this->buildQuery('SELECT * FROM users WHERE id = 3', 0.020),
            ],
        ];

        $result = $this->analyzer->analyze($connectionData);

        self::assertCount(1, $result['query_groups']);

        $group = $result['query_groups'][0];

        self::assertSame(3, $group['count']);
        self::assertEqualsWithDelta(60.0, $group['total_ms'], 0.01);
        self::assertEqualsWithDelta(30.0, $group['max_ms'], 0.01);
        self::assertEqualsWithDelta(10.0, $group['min_ms'], 0.01);
        self::assertEqualsWithDelta(20.0, $group['avg_ms'], 0.01);
        self::assertSame('SELECT * FROM users WHERE id = 1', $group['example_sql']);
        self::assertSame('app', $group['origin']);
    }

    public function testAnalyzeMultipleConnections(): void
    {
        $connectionData = [
            'default' => [
                $this->buildQuery('SELECT * FROM users WHERE id = 1', 0.005),
            ],
            'replica' => [
                $this->buildQuery('SELECT * FROM users WHERE id = 2', 0.003),
            ],
        ];

        $result = $this->analyzer->analyze($connectionData);

        self::assertSame(2, $result['connections']);
        self::assertSame(2, $result['total_queries']);
        self::assertCount(2, $result['query_groups']);
        self::assertSame('default', $result['query_groups'][0]['connection']);
        self::assertSame('replica', $result['query_groups'][1]['connection']);
    }

    public function testAnalyzeSortsByTotalMsDescending(): void
    {
        $connectionData = [
            'default' => [
                $this->buildQuery('SELECT * FROM users WHERE id = 1', 0.001),
                $this->buildQuery('INSERT INTO logs (msg) VALUES (?)', 0.050),
                $this->buildQuery('UPDATE settings SET value = ? WHERE key = ?', 0.020),
            ],
        ];

        $result = $this->analyzer->analyze($connectionData);

        self::assertCount(3, $result['query_groups']);
        self::assertGreaterThanOrEqual($result['query_groups'][1]['total_ms'], $result['query_groups'][0]['total_ms']);
        self::assertGreaterThanOrEqual($result['query_groups'][2]['total_ms'], $result['query_groups'][1]['total_ms']);

        self::assertSame(3, $result['unique_patterns']);
        self::assertSame(1, $result['select_count']);
        self::assertSame(1, $result['insert_count']);
        self::assertSame(1, $result['update_count']);
        self::assertSame(0, $result['delete_count']);
    }

    public function testAnalyzeExtractsKindAndTables(): void
    {
        $connectionData = [
            'default' => [
                $this->buildQuery('SELECT u.name FROM users u JOIN roles r ON u.role_id = r.id', 0.005),
            ],
        ];

        $result = $this->analyzer->analyze($connectionData);

        self::assertCount(1, $result['query_groups']);

        $group = $result['query_groups'][0];

        self::assertSame('SELECT', $group['kind']);
        self::assertContains('users', $group['tables']);
        self::assertContains('roles', $group['tables']);
    }

    public function testAnalyzeEmptyData(): void
    {
        $result = $this->analyzer->analyze([]);

        self::assertSame([], $result['query_groups']);
        self::assertSame(0, $result['total_queries']);
        self::assertSame(0.0, $result['total_db_ms']);
        self::assertSame(0, $result['app_queries']);
        self::assertSame(0.0, $result['app_db_ms']);
        self::assertSame(0, $result['app_patterns']);
        self::assertSame(0, $result['infra_queries']);
        self::assertSame(0.0, $result['infra_db_ms']);
        self::assertSame(0, $result['infra_patterns']);
        self::assertSame(0, $result['profiler_queries']);
        self::assertSame(0.0, $result['profiler_db_ms']);
        self::assertSame(0, $result['profiler_patterns']);
        self::assertSame(0, $result['connections']);
        self::assertSame(0, $result['unique_patterns']);
        self::assertSame(0, $result['select_count']);
        self::assertSame(0, $result['insert_count']);
        self::assertSame(0, $result['update_count']);
        self::assertSame(0, $result['delete_count']);
        self::assertSame(0, $result['other_count']);
    }

    public function testAnalyzeSingleQuery(): void
    {
        $connectionData = [
            'default' => [
                $this->buildQuery('DELETE FROM sessions WHERE expired_at < NOW()', 0.015),
            ],
        ];

        $result = $this->analyzer->analyze($connectionData);

        self::assertCount(1, $result['query_groups']);
        self::assertSame(1, $result['total_queries']);
        self::assertSame(1, $result['connections']);

        $group = $result['query_groups'][0];

        self::assertSame(1, $group['count']);
        self::assertSame('DELETE', $group['kind']);
        self::assertEqualsWithDelta(15.0, $group['total_ms'], 0.01);
        self::assertEqualsWithDelta($group['total_ms'], $group['max_ms'], 0.01);
        self::assertEqualsWithDelta($group['total_ms'], $group['min_ms'], 0.01);
        self::assertEqualsWithDelta($group['total_ms'], $group['avg_ms'], 0.01);
    }

    public function testClassifiesInfraQueriesByTablePrefix(): void
    {
        $connectionData = [
            'default' => [
                $this->buildQuery("SELECT * FROM pg_class WHERE relname = 'users'", 0.002),
                $this->buildQuery('SELECT * FROM information_schema.tables', 0.001),
                $this->buildQuery('SELECT * FROM users WHERE id = 1', 0.005),
            ],
        ];

        $result = $this->analyzer->analyze($connectionData);

        self::assertSame(3, $result['total_queries']);
        self::assertSame(1, $result['app_queries']);
        self::assertSame(2, $result['infra_queries']);
        self::assertSame(1, $result['app_patterns']);
        self::assertSame(2, $result['infra_patterns']);
    }

    public function testClassifiesInfraQueriesBySqlPattern(): void
    {
        $connectionData = [
            'default' => [
                $this->buildQuery('SELECT CURRENT_DATABASE()', 0.001),
                $this->buildQuery('SELECT CURRENT_SCHEMA()', 0.001),
                $this->buildQuery('SELECT * FROM users WHERE id = 1', 0.005),
            ],
        ];

        $result = $this->analyzer->analyze($connectionData);

        self::assertSame(1, $result['app_queries']);
        self::assertSame(2, $result['infra_queries']);
    }

    public function testClassifiesInfraQueriesByConfiguredTable(): void
    {
        $connectionData = [
            'default' => [
                $this->buildQuery('SELECT * FROM doctrine_migration_versions', 0.003),
                $this->buildQuery('SELECT * FROM users WHERE id = 1', 0.005),
            ],
        ];

        $result = $this->analyzer->analyze($connectionData);

        self::assertSame(1, $result['app_queries']);
        self::assertSame(1, $result['infra_queries']);
    }

    public function testKindCountsOnlyCountAppOriginGroups(): void
    {
        $connectionData = [
            'default' => [
                $this->buildQuery('SELECT * FROM pg_class', 0.002),
                $this->buildQuery('SELECT * FROM pg_attribute', 0.001),
                $this->buildQuery('SELECT * FROM users WHERE id = 1', 0.005),
                $this->buildQuery("INSERT INTO logs (msg) VALUES ('test')", 0.003),
            ],
        ];

        $result = $this->analyzer->analyze($connectionData);

        self::assertSame(1, $result['select_count']);
        self::assertSame(1, $result['insert_count']);
        self::assertSame(0, $result['update_count']);
        self::assertSame(0, $result['delete_count']);
    }

    public function testAnalyzeStoresExampleParamsFromFirstOccurrence(): void
    {
        $connectionData = [
            'default' => [
                $this->buildQuery('SELECT * FROM users WHERE id = ?', 0.005, [1], ['integer']),
                $this->buildQuery('SELECT * FROM users WHERE id = ?', 0.003, [42], ['integer']),
            ],
        ];

        $result = $this->analyzer->analyze($connectionData);

        self::assertCount(1, $result['query_groups']);

        $group = $result['query_groups'][0];

        self::assertSame([1], $group['example_params']);
        self::assertSame(['integer'], $group['example_types']);
    }

    public function testAnalyzeStoresEmptyParamsWhenNone(): void
    {
        $connectionData = [
            'default' => [
                $this->buildQuery('SELECT 1', 0.001),
            ],
        ];

        $result = $this->analyzer->analyze($connectionData);

        self::assertCount(1, $result['query_groups']);

        $group = $result['query_groups'][0];

        self::assertSame([], $group['example_params']);
        self::assertSame([], $group['example_types']);
    }

    /**
     * @param array<array-key, mixed> $params
     * @param array<array-key, mixed> $types
     *
     * @return array{sql: string, executionMS: float, params: array<array-key, mixed>, types: array<array-key, mixed>}
     */
    private function buildQuery(string $sql, float $executionSeconds, array $params = [], array $types = []): array
    {
        return [
            'sql'         => $sql,
            'executionMS' => $executionSeconds,
            'params'      => $params,
            'types'       => $types,
        ];
    }
}
