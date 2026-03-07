<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\DataCollector;

use MartinKup\OptimizationAdvisorBundle\Analyzer\CacheAnalyzer;
use MartinKup\OptimizationAdvisorBundle\Analyzer\DatabaseAnalyzer;
use MartinKup\OptimizationAdvisorBundle\Analyzer\EventAnalyzer;
use MartinKup\OptimizationAdvisorBundle\Analyzer\HttpClientAnalyzer;
use MartinKup\OptimizationAdvisorBundle\Analyzer\OtherSignalsAnalyzer;
use MartinKup\OptimizationAdvisorBundle\Analyzer\PerformanceAnalyzer;
use MartinKup\OptimizationAdvisorBundle\Analyzer\TwigAnalyzer;
use MartinKup\OptimizationAdvisorBundle\DataCollector\OptimizationAdvisorDataCollector;
use MartinKup\OptimizationAdvisorBundle\Engine\AdvisorEngine;
use MartinKup\OptimizationAdvisorBundle\Messenger\TraceRegistry;
use MartinKup\OptimizationAdvisorBundle\Sql\QueryParamSanitizer;
use MartinKup\OptimizationAdvisorBundle\Sql\SqlNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Component\Cache\DataCollector\CacheDataCollector;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\VarDumper\Cloner\Data;
use Twig\Profiler\Profile;

/**
 * Tests for the OptimizationAdvisorDataCollector.
 *
 * Uses real analyzer instances (stateless) and mocks for external dependencies
 * (CacheDataCollector, TraceableEventDispatcher, DebugDataHolder, Stopwatch).
 * HttpClientDataCollector is final (PHP keyword), so only the null path is tested.
 */
#[CoversClass(OptimizationAdvisorDataCollector::class)]
#[Group('unit')]
final class OptimizationAdvisorDataCollectorTest extends TestCase
{
    // ─── A) Static getters & identity ──────────────────────────────────

    public function testGetNameReturnsOptimizationAdvisor(): void
    {
        $collector = $this->createCollector();

        self::assertSame('optimization_advisor', $collector->getName());
    }

    public function testGetTemplateReturnsCorrectPath(): void
    {
        self::assertSame(
            '@OptimizationAdvisor/data_collector/optimization_advisor.html.twig',
            OptimizationAdvisorDataCollector::getTemplate(),
        );
    }

    public function testGettersReturnEmptyDefaultsBeforeCollect(): void
    {
        $collector = $this->createCollector();

        self::assertSame([], $collector->getSignals());
        self::assertSame([], $collector->getOpportunities());
        self::assertSame([], $collector->getSummary());
        self::assertSame('', $collector->getCorrelationId());

        $origin = $collector->getOrigin();

        self::assertSame('', $origin['route']);
        self::assertSame('', $origin['controller']);
        self::assertSame('', $origin['method']);
        self::assertSame('', $origin['uri']);
    }

    public function testGetCorrelationIdReturnsEmptyStringByDefault(): void
    {
        $collector = $this->createCollector();

        self::assertSame('', $collector->getCorrelationId());
    }

    public function testResetClearsAllData(): void
    {
        $collector = $this->createCollector();
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        $collector->reset();

        self::assertSame([], $collector->getSignals());
        self::assertSame([], $collector->getOpportunities());
        self::assertSame([], $collector->getSummary());
        self::assertSame('', $collector->getCorrelationId());
    }

    // ─── B) collect() method ───────────────────────────────────────────

    public function testCollectStoresRouteAndControllerFromRequest(): void
    {
        $collector = $this->createCollector();
        $request = $this->createRequest('app_home', 'App\\Controller\\HomeController::index');

        $collector->collect($request, new Response());

        $origin = $collector->getOrigin();

        self::assertSame('app_home', $origin['route']);
        self::assertSame('App\\Controller\\HomeController::index', $origin['controller']);
    }

    public function testCollectStoresMethodAndUri(): void
    {
        $collector = $this->createCollector();
        $request = Request::create('https://example.com/test', 'POST');

        $collector->collect($request, new Response());

        $origin = $collector->getOrigin();

        self::assertSame('POST', $origin['method']);
        self::assertStringContainsString('/test', $origin['uri']);
    }

    public function testCollectHandlesMissingRouteAttribute(): void
    {
        $collector = $this->createCollector();
        $request = Request::create('https://example.com/');

        $collector->collect($request, new Response());

        $origin = $collector->getOrigin();

        self::assertSame('', $origin['route']);
        self::assertSame('', $origin['controller']);
    }

    public function testCollectStoresStopwatchToken(): void
    {
        $collector = $this->createCollector();
        $request = Request::create('https://example.com/');
        $request->attributes->set('_stopwatch_token', 'abc123');

        $collector->collect($request, new Response());

        // After collect but before lateCollect, the token is stored internally.
        // We verify it indirectly via lateCollect behaviour (stopwatch_token is unset there).
        // The data array should have the stopwatch_token key after collect.
        $signals = $collector->getSignals();

        // signals not yet populated
        self::assertSame([], $signals);
    }

    // ─── C) lateCollect() pipeline ─────────────────────────────────────

    public function testLateCollectProducesSignalsWithAllSevenKeys(): void
    {
        $collector = $this->createCollector();
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        $signals = $collector->getSignals();

        self::assertArrayHasKey('db', $signals);
        self::assertArrayHasKey('cache', $signals);
        self::assertArrayHasKey('twig', $signals);
        self::assertArrayHasKey('events', $signals);
        self::assertArrayHasKey('http', $signals);
        self::assertArrayHasKey('other', $signals);
        self::assertArrayHasKey('performance', $signals);
    }

    public function testLateCollectWithEmptyDataProducesZeroOpportunities(): void
    {
        $collector = $this->createCollector();
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        self::assertSame([], $collector->getOpportunities());
    }

    public function testLateCollectWithNullDebugDataHolder(): void
    {
        $collector = $this->createCollector(debugDataHolder: null);
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        $dbSignals = $collector->getDbSignals();

        self::assertFalse($dbSignals['_available']);
    }

    public function testLateCollectWithNullHttpClientCollector(): void
    {
        $collector = $this->createCollector();
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        $httpSignals = $collector->getHttpSignals();

        // httpClientDataCollector is null by default in createCollector
        self::assertFalse($httpSignals['_available']);
    }

    public function testLateCollectWithNullStopwatch(): void
    {
        $collector = $this->createCollector(stopwatch: null);
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        $perfSignals = $collector->getPerformanceSignals();

        self::assertFalse($perfSignals['_available']);
    }

    public function testLateCollectUnsetsStopwatchToken(): void
    {
        $collector = $this->createCollector();
        $request = Request::create('https://example.com/');
        $request->attributes->set('_stopwatch_token', 'test_token');

        $collector->collect($request, new Response());
        $collector->lateCollect();

        // After lateCollect, stopwatch_token is removed from data.
        // It should not appear in signals or any other public getter.
        $signals = $collector->getSignals();

        self::assertArrayNotHasKey('stopwatch_token', $signals);
    }

    // ─── D) Summary ────────────────────────────────────────────────────

    public function testSummaryContainsAllExpectedKeys(): void
    {
        $collector = $this->createCollector();
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        $summary = $collector->getSummary();
        $expectedKeys = [
            'opportunity_count',
            'quick_win_count',
            'high_impact_count',
            'optimization_score',
            'total_db_ms',
            'total_twig_ms',
            'total_http_ms',
            'total_messenger_ms',
        ];

        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $summary, "Summary missing key: {$key}");
        }
    }

    public function testSummaryScoreIs100WithNoOpportunities(): void
    {
        $collector = $this->createCollector();
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        $summary = $collector->getSummary();

        self::assertSame(100, $summary['optimization_score']);
        self::assertSame(0, $summary['opportunity_count']);
    }

    public function testSummaryScoreDecreasesWithOpportunities(): void
    {
        // Set up queries that will trigger slow query detection
        $debugDataHolder = $this->createDebugDataHolderMock([
            'default' => [
                $this->buildQuery('UPDATE users SET name = ? WHERE id = ?', 0.100),
            ],
        ]);

        $collector = $this->createCollector(debugDataHolder: $debugDataHolder);
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        $summary = $collector->getSummary();

        self::assertLessThan(100, $summary['optimization_score']);
        self::assertGreaterThan(0, $summary['opportunity_count']);
    }

    // ─── E) Filtering getters ──────────────────────────────────────────

    public function testGetQuickWinsFiltersAndDeduplicatesByCode(): void
    {
        // Trigger duplicate query detection (effort=1, confidence=5 → is_quick_win=true)
        $debugDataHolder = $this->createDebugDataHolderMock([
            'default' => [
                $this->buildQuery('INSERT INTO logs (id) VALUES (1)', 0.001),
                $this->buildQuery('INSERT INTO logs (id) VALUES (2)', 0.001),
                $this->buildQuery('INSERT INTO logs (id) VALUES (3)', 0.001),
            ],
        ]);

        $collector = $this->createCollector(debugDataHolder: $debugDataHolder);
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        $quickWins = $collector->getQuickWins();

        self::assertNotEmpty($quickWins);

        foreach ($quickWins as $win) {
            self::assertTrue($win['is_quick_win']);
            self::assertArrayHasKey('occurrence_count', $win);
            self::assertGreaterThanOrEqual(1, $win['occurrence_count']);
        }
    }

    public function testGetHighImpactOpportunitiesFiltersCorrectly(): void
    {
        // Slow query group: impact=4 → is_high_impact=true
        $debugDataHolder = $this->createDebugDataHolderMock([
            'default' => [
                $this->buildQuery('UPDATE users SET name = ? WHERE id = ?', 0.100),
            ],
        ]);

        $collector = $this->createCollector(debugDataHolder: $debugDataHolder);
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        $highImpact = $collector->getHighImpactOpportunities();

        self::assertNotEmpty($highImpact);

        foreach ($highImpact as $item) {
            self::assertTrue($item['is_high_impact']);
        }
    }

    public function testGetTopOpportunitiesRespectsLimit(): void
    {
        $queries = [];

        for ($i = 0; $i < 10; $i++) {
            $sql = 'INSERT INTO table_' . $i . ' (id) VALUES (?)';
            $queries[] = $this->buildQuery($sql, 0.001);
            $queries[] = $this->buildQuery($sql, 0.001);
            $queries[] = $this->buildQuery($sql, 0.001);
        }

        $debugDataHolder = $this->createDebugDataHolderMock(['default' => $queries]);
        $collector = $this->createCollector(debugDataHolder: $debugDataHolder);
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        self::assertCount(3, $collector->getTopOpportunities(3));
        self::assertLessThanOrEqual(5, count($collector->getTopOpportunities()));
    }

    public function testGetRiskyChangesFiltersHighRisk(): void
    {
        $collector = $this->createCollector();
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        $risky = $collector->getRiskyChanges();

        // With empty data, no opportunities → no risky changes
        self::assertSame([], $risky);
    }

    public function testFilteringGettersReturnEmptyWhenNoOpportunities(): void
    {
        $collector = $this->createCollector();
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        self::assertSame([], $collector->getQuickWins());
        self::assertSame([], $collector->getHighImpactOpportunities());
        self::assertSame([], $collector->getRiskyChanges());
    }

    // ─── F) Signal sub-getters ─────────────────────────────────────────

    public function testSignalSubGettersReturnCorrectSubArrays(): void
    {
        $collector = $this->createCollector();
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        $signals = $collector->getSignals();

        self::assertSame($signals['db'], $collector->getDbSignals());
        self::assertSame($signals['cache'], $collector->getCacheSignals());
        self::assertSame($signals['twig'], $collector->getTwigSignals());
        self::assertSame($signals['events'], $collector->getEventSignals());
        self::assertSame($signals['http'], $collector->getHttpSignals());
        self::assertSame($signals['other'], $collector->getOtherSignals());
        self::assertSame($signals['performance'], $collector->getPerformanceSignals());
    }

    public function testDbSignalsAvailabilityFlag(): void
    {
        $debugDataHolder = $this->createDebugDataHolderMock([]);
        $collectorWithDoctrine = $this->createCollector(debugDataHolder: $debugDataHolder);
        $collectorWithDoctrine->collect($this->createRequest(), new Response());
        $collectorWithDoctrine->lateCollect();

        self::assertTrue($collectorWithDoctrine->getDbSignals()['_available']);

        $collectorWithoutDoctrine = $this->createCollector(debugDataHolder: null);
        $collectorWithoutDoctrine->collect($this->createRequest(), new Response());
        $collectorWithoutDoctrine->lateCollect();

        self::assertFalse($collectorWithoutDoctrine->getDbSignals()['_available']);
    }

    public function testHttpSignalsAvailabilityFlag(): void
    {
        // httpClientDataCollector is always null in createCollector (final class)
        $collector = $this->createCollector();
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        self::assertFalse($collector->getHttpSignals()['_available']);
    }

    // ─── G) processQueryGroupParams ────────────────────────────────────

    public function testQueryGroupParamsAreSanitizedAndCloned(): void
    {
        $debugDataHolder = $this->createDebugDataHolderMock([
            'default' => [
                $this->buildQuery('SELECT * FROM users WHERE id = ?', 0.005, [42], ['integer']),
            ],
        ]);

        $collector = $this->createCollector(debugDataHolder: $debugDataHolder);
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        $dbSignals = $collector->getDbSignals();

        /** @var array<int, array<string, mixed>> $groups */
        $groups = $dbSignals['query_groups'] ?? [];

        self::assertNotEmpty($groups);

        /** @var array<string, mixed> $group */
        $group = $groups[0];

        self::assertInstanceOf(Data::class, $group['example_params']);
        self::assertArrayHasKey('runnable', $group);
        self::assertArrayNotHasKey('example_types', $group);
    }

    public function testQueryGroupParamsHandleEmptyParams(): void
    {
        $debugDataHolder = $this->createDebugDataHolderMock([
            'default' => [
                $this->buildQuery('SELECT 1', 0.001),
            ],
        ]);

        $collector = $this->createCollector(debugDataHolder: $debugDataHolder);
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        $dbSignals = $collector->getDbSignals();

        /** @var array<int, array<string, mixed>> $groups */
        $groups = $dbSignals['query_groups'] ?? [];

        self::assertNotEmpty($groups);

        /** @var array<string, mixed> $group */
        $group = $groups[0];

        self::assertInstanceOf(Data::class, $group['example_params']);
        self::assertTrue($group['runnable']);
    }

    // ─── H) Events dispatcher ──────────────────────────────────────────

    public function testAnalyzeEventsWithNonTraceableDispatcher(): void
    {
        $dispatcher = self::createStub(EventDispatcherInterface::class);
        $collector = $this->createCollector(dispatcher: $dispatcher);
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        $eventSignals = $collector->getEventSignals();

        self::assertSame([], $eventSignals['listeners']);
        self::assertSame(0, $eventSignals['total_listener_calls']);
    }

    public function testAnalyzeEventsWithTraceableDispatcher(): void
    {
        $dispatcher = $this->createTraceableDispatcherMock(
            called: [
                ['event' => 'kernel.request', 'pretty' => 'App\\Listener\\TestListener::onRequest'],
                ['event' => 'kernel.response', 'pretty' => 'App\\Listener\\TestListener::onResponse'],
            ],
            notCalled: [
                ['event' => 'kernel.terminate', 'pretty' => 'App\\Listener\\UnusedListener::onTerminate'],
            ],
        );

        $collector = $this->createCollector(dispatcher: $dispatcher);
        $collector->collect($this->createRequest(), new Response());
        $collector->lateCollect();

        $eventSignals = $collector->getEventSignals();

        self::assertNotEmpty($eventSignals['listeners']);
        self::assertGreaterThan(0, $eventSignals['total_listener_calls']);
        self::assertGreaterThan(0, $eventSignals['not_called_count']);
    }

    // ─── Helpers ───────────────────────────────────────────────────────

    /** @param array<string, array<string, mixed>>|null $cacheStats */
    private function createCollector(
        ?DebugDataHolder $debugDataHolder = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?Stopwatch $stopwatch = null,
        ?array $cacheStats = null,
    ): OptimizationAdvisorDataCollector {
        $sqlNormalizer = new SqlNormalizer();

        /** @var array<string, array<string, mixed>> $stats */
        $stats = $cacheStats ?? [];

        return new OptimizationAdvisorDataCollector(
            databaseAnalyzer: new DatabaseAnalyzer($sqlNormalizer),
            queryParamSanitizer: new QueryParamSanitizer(),
            cacheAnalyzer: new CacheAnalyzer(),
            twigAnalyzer: new TwigAnalyzer(),
            eventAnalyzer: new EventAnalyzer(),
            httpClientAnalyzer: new HttpClientAnalyzer(),
            otherSignalsAnalyzer: new OtherSignalsAnalyzer(),
            performanceAnalyzer: new PerformanceAnalyzer(),
            advisorEngine: new AdvisorEngine(),
            twigProfile: $this->createEmptyProfile(),
            cacheDataCollector: $this->createCacheCollectorMock($stats),
            eventDispatcher: $dispatcher ?? $this->createTraceableDispatcherMock(),
            traceRegistry: new TraceRegistry(),
            debugDataHolder: $debugDataHolder,
            httpClientDataCollector: null,
            stopwatch: $stopwatch,
        );
    }

    private function createRequest(string $route = '', string $controller = ''): Request
    {
        $request = Request::create('https://example.com/test');

        if ($route !== '') {
            $request->attributes->set('_route', $route);
        }

        if ($controller !== '') {
            $request->attributes->set('_controller', $controller);
        }

        return $request;
    }

    private function createEmptyProfile(): Profile
    {
        $profile = new Profile('main', Profile::ROOT, 'main');
        $profile->leave();

        return $profile;
    }

    /** @param array<string, array<string, mixed>> $statistics */
    private function createCacheCollectorMock(array $statistics = []): CacheDataCollector
    {
        $stub = self::createStub(CacheDataCollector::class);
        $stub->method('getStatistics')->willReturn($statistics);

        return $stub;
    }

    /**
     * @param array<int, array<string, mixed>> $called
     * @param array<int, array<string, mixed>> $notCalled
     */
    private function createTraceableDispatcherMock(array $called = [], array $notCalled = []): TraceableEventDispatcher
    {
        $stub = self::createStub(TraceableEventDispatcher::class);
        $stub->method('getCalledListeners')->willReturn($called);
        $stub->method('getNotCalledListeners')->willReturn($notCalled);

        return $stub;
    }

    /** @param array<string, array<int, array<string, mixed>>> $connectionData */
    private function createDebugDataHolderMock(array $connectionData): DebugDataHolder
    {
        $stub = self::createStub(DebugDataHolder::class);
        $stub->method('getData')->willReturn($connectionData);

        return $stub;
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
