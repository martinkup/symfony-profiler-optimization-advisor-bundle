<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\AiMate\Formatter;

use MartinKup\OptimizationAdvisorBundle\AiMate\Formatter\OptimizationAdvisorCollectorFormatter;
use MartinKup\OptimizationAdvisorBundle\AiMate\SecurityRedactor;
use MartinKup\OptimizationAdvisorBundle\DataCollector\OptimizationAdvisorDataCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\VarDumper\Cloner\VarCloner;

/** @see OptimizationAdvisorCollectorFormatter */
#[CoversClass(OptimizationAdvisorCollectorFormatter::class)]
#[Group('unit')]
final class OptimizationAdvisorCollectorFormatterTest extends TestCase
{
    private OptimizationAdvisorCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new OptimizationAdvisorCollectorFormatter(new SecurityRedactor(
            true,
            [
                'email', 'password', 'passwd', 'token', 'secret', 'auth', 'credential',
                'phone', 'address', 'ssn', 'card', 'iban',
            ],
            ['[^@\\s]+@[^@\\s]+\\.[^@\\s]+'],
            [
                'token', 'api_key', 'apikey', 'secret', 'password', 'auth', 'access_token', 'refresh_token',
                'session_id', '_token', 'email', 'session', 'cookie',
            ],
        ));
    }

    public function testGetNameReturnsOptimizationAdvisor(): void
    {
        self::assertSame('optimization_advisor', $this->formatter->getName());
    }

    public function testFormatReturnsFullCollectorData(): void
    {
        $origin = ['route' => 'app_home', 'controller' => 'HomeController', 'method' => 'GET', 'uri' => '/'];
        $opportunities = [
            [
                'code' => 'DB_N_PLUS_ONE',
                'category' => 'db',
                'impact' => 8,
                'is_quick_win' => true,
                'is_high_impact' => true,
                'recommended_actions' => ['Add eager loading', 'Use batch queries'],
                'ai_prompt' => 'Some prompt',
            ],
        ];
        $summary = [
            'opportunity_count' => 1,
            'quick_win_count' => 1,
            'high_impact_count' => 1,
            'optimization_score' => 96,
        ];
        $signals = ['db' => ['total_db_ms' => 42.5], 'cache' => ['total_cache_ms' => 10.0]];

        $collector = $this->createCollectorWithData([
            'origin' => $origin,
            'opportunities' => $opportunities,
            'summary' => $summary,
            'signals' => $signals,
        ]);

        $result = $this->formatter->format($collector);

        self::assertSame($origin, $result['origin']);
        self::assertSame($summary, $result['summary']);
        self::assertSame($signals, $result['signals']);

        /** @var array<int, array<string, mixed>> $resultOpportunities */
        $resultOpportunities = $result['opportunities'];
        self::assertCount(1, $resultOpportunities);
        self::assertSame('DB_N_PLUS_ONE', $resultOpportunities[0]['code']);
        self::assertArrayNotHasKey('recommended_actions', $resultOpportunities[0]);
        self::assertArrayNotHasKey('ai_prompt', $resultOpportunities[0]);
    }

    public function testGetSummaryReturnsCompactView(): void
    {
        $collector = $this->createCollectorWithData([
            'summary' => [
                'opportunity_count' => 3,
                'quick_win_count' => 2,
                'high_impact_count' => 1,
                'optimization_score' => 85,
                'total_db_ms' => 100.0,
                'total_twig_ms' => 50.0,
            ],
        ]);

        $result = $this->formatter->getSummary($collector);

        self::assertSame(3, $result['opportunity_count']);
        self::assertSame(2, $result['quick_win_count']);
        self::assertSame(1, $result['high_impact_count']);
        self::assertSame(85, $result['optimization_score']);
        self::assertArrayNotHasKey('total_db_ms', $result);
        self::assertArrayNotHasKey('total_twig_ms', $result);
    }

    public function testFormatSanitizesDataObjects(): void
    {
        $cloner = new VarCloner();
        $dataObject = $cloner->cloneVar(['email' => 'admin@example.com', 'count' => 42]);

        $collector = $this->createCollectorWithData([
            'origin' => ['route' => '', 'controller' => '', 'method' => '', 'uri' => ''],
            'opportunities' => [],
            'summary' => [],
            'signals' => [
                'db' => [
                    'query_groups' => [
                        ['sql' => 'SELECT 1', 'example_params' => $dataObject],
                    ],
                ],
            ],
        ]);

        $result = $this->formatter->format($collector);

        /** @var array<string, array<string, mixed>> $signals */
        $signals = $result['signals'];
        /** @var array<string, mixed> $dbSignals */
        $dbSignals = $signals['db'];
        /** @var array<int, array<string, mixed>> $queryGroups */
        $queryGroups = $dbSignals['query_groups'];
        /** @var array<string, mixed> $params */
        $params = $queryGroups[0]['example_params'];
        self::assertSame('***REDACTED***', $params['email']);
        self::assertSame(42, $params['count']);
    }

    public function testFormatRedactsSensitiveQueryParamsFromOriginUri(): void
    {
        $collector = $this->createCollectorWithData([
            'origin' => [
                'route' => 'api_users',
                'controller' => 'UserController',
                'method' => 'GET',
                'uri' => '/api/users?token=secret&page=1',
            ],
            'opportunities' => [],
            'summary' => [],
            'signals' => [],
        ]);

        $result = $this->formatter->format($collector);

        /** @var array<string, mixed> $origin */
        $origin = $result['origin'];
        $uri = $origin['uri'];
        self::assertIsString($uri);
        self::assertStringContainsString('page=1', $uri);
        self::assertStringNotContainsString('token=secret', $uri);
        self::assertSame('api_users', $origin['route']);
    }

    public function testFormatRedactsExampleSqlAndParams(): void
    {
        $collector = $this->createCollectorWithData([
            'origin' => ['route' => '', 'controller' => '', 'method' => '', 'uri' => ''],
            'opportunities' => [],
            'summary' => [],
            'signals' => [
                'db' => [
                    'query_groups' => [
                        [
                            'pattern' => 'SELECT * FROM users WHERE email = ?',
                            'example_sql' => "SELECT * FROM users WHERE email = 'admin@example.com'",
                            'example_params' => ['admin@example.com'],
                            'runnable' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->formatter->format($collector);

        /** @var array<string, array<string, array<int, array<string, mixed>>>> $signals */
        $signals = $result['signals'];
        $group = $signals['db']['query_groups'][0];
        self::assertSame('SELECT * FROM users WHERE email = ?', $group['example_sql']);
        self::assertSame(['***REDACTED***'], $group['example_params']);
        self::assertFalse($group['runnable']);
    }

    public function testFormatWithRedactionDisabled(): void
    {
        $formatter = new OptimizationAdvisorCollectorFormatter(new SecurityRedactor(false));

        $collector = $this->createCollectorWithData([
            'origin' => ['route' => '', 'controller' => '', 'method' => '', 'uri' => '/api?token=secret'],
            'opportunities' => [],
            'summary' => [],
            'signals' => [
                'db' => [
                    'query_groups' => [
                        [
                            'pattern' => 'SELECT ?',
                            'example_sql' => "SELECT 'sensitive'",
                            'example_params' => ['sensitive'],
                            'runnable' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $result = $formatter->format($collector);

        /** @var array<string, mixed> $origin */
        $origin = $result['origin'];
        self::assertSame('/api?token=secret', $origin['uri']);

        /** @var array<string, array<string, array<int, array<string, mixed>>>> $signals */
        $signals = $result['signals'];
        self::assertSame("SELECT 'sensitive'", $signals['db']['query_groups'][0]['example_sql']);
        self::assertSame(['sensitive'], $signals['db']['query_groups'][0]['example_params']);
        self::assertTrue($signals['db']['query_groups'][0]['runnable']);
    }

    public function testFormatHandlesEmptyOpportunities(): void
    {
        $collector = $this->createCollectorWithData([
            'origin' => ['route' => '', 'controller' => '', 'method' => '', 'uri' => ''],
            'opportunities' => [],
            'summary' => [],
            'signals' => [],
        ]);

        $result = $this->formatter->format($collector);

        self::assertSame([], $result['opportunities']);
        self::assertSame([], $result['signals']);
    }

    public function testGetSummaryHandlesEmptySummary(): void
    {
        $collector = $this->createCollectorWithData(['summary' => []]);

        $result = $this->formatter->getSummary($collector);

        self::assertSame(0, $result['opportunity_count']);
        self::assertSame(0, $result['quick_win_count']);
        self::assertSame(0, $result['high_impact_count']);
        self::assertSame(100, $result['optimization_score']);
    }

    /** @param array<string, mixed> $data */
    private function createCollectorWithData(array $data): OptimizationAdvisorDataCollector
    {
        $reflection = new ReflectionClass(OptimizationAdvisorDataCollector::class);
        $collector = $reflection->newInstanceWithoutConstructor();

        $property = (new ReflectionClass(DataCollector::class))->getProperty('data');
        $property->setValue($collector, $data);

        return $collector;
    }
}
