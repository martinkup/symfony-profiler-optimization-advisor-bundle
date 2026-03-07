<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\DataCollector;

use MartinKup\OptimizationAdvisorBundle\Analyzer\CacheAnalyzer;
use MartinKup\OptimizationAdvisorBundle\Analyzer\DatabaseAnalyzer;
use MartinKup\OptimizationAdvisorBundle\Analyzer\EventAnalyzer;
use MartinKup\OptimizationAdvisorBundle\Analyzer\HttpClientAnalyzer;
use MartinKup\OptimizationAdvisorBundle\Analyzer\OtherSignalsAnalyzer;
use MartinKup\OptimizationAdvisorBundle\Analyzer\PerformanceAnalyzer;
use MartinKup\OptimizationAdvisorBundle\Analyzer\TwigAnalyzer;
use MartinKup\OptimizationAdvisorBundle\Engine\AdvisorEngine;
use MartinKup\OptimizationAdvisorBundle\Enum\OpportunityCode;
use MartinKup\OptimizationAdvisorBundle\Enum\Risk;
use MartinKup\OptimizationAdvisorBundle\Messenger\TraceRegistry;
use MartinKup\OptimizationAdvisorBundle\Sql\QueryParamSanitizer;
use Override;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\Cache\DataCollector\CacheDataCollector;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;
use Symfony\Component\VarDumper\Cloner\Data;
use Throwable;
use Traversable;
use Twig\Profiler\Profile;

/**
 * Collects profiling signals from multiple sources and produces optimization opportunities.
 *
 * Runs as a late data collector (priority -100) to ensure all other collectors
 * have finished before analysis. Signals from 7 categories are fed through
 * the AdvisorEngine to produce scored, actionable optimization recommendations.
 */
#[AutoconfigureTag('data_collector', [
    'id'       => 'optimization_advisor',
    'priority' => -100,
])]
final class OptimizationAdvisorDataCollector extends AbstractDataCollector implements LateDataCollectorInterface
{
    private ?Request $currentRequest = null;

    public function __construct(
        private readonly DatabaseAnalyzer $databaseAnalyzer,
        private readonly QueryParamSanitizer $queryParamSanitizer,
        private readonly CacheAnalyzer $cacheAnalyzer,
        private readonly TwigAnalyzer $twigAnalyzer,
        private readonly EventAnalyzer $eventAnalyzer,
        private readonly HttpClientAnalyzer $httpClientAnalyzer,
        private readonly OtherSignalsAnalyzer $otherSignalsAnalyzer,
        private readonly PerformanceAnalyzer $performanceAnalyzer,
        private readonly AdvisorEngine $advisorEngine,
        private readonly Profile $twigProfile,
        private readonly CacheDataCollector $cacheDataCollector,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TraceRegistry $traceRegistry,
        private readonly ?DebugDataHolder $debugDataHolder = null,
        private readonly ?HttpClientDataCollector $httpClientDataCollector = null,
        private readonly ?Stopwatch $stopwatch = null,
    ) {
    }

    #[Override]
    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $this->currentRequest = $request;

        $route = $request->attributes->get('_route');
        $controller = $request->attributes->get('_controller');

        $this->data = [
            'origin'          => [
                'route'      => is_string($route) ? $route : '',
                'controller' => is_string($controller) ? $controller : '',
                'method'     => $request->getMethod(),
                'uri'        => $request->getUri(),
            ],
            'correlation_id'  => '',
            'stopwatch_token' => $request->attributes->get('_stopwatch_token'),
            'signals'         => [],
            'opportunities'   => [],
            'summary'         => [],
        ];
    }

    #[Override]
    public function lateCollect(): void
    {
        $connectionData = [];

        if ($this->debugDataHolder instanceof DebugDataHolder) {
            /** @var array<string, array<int, array<string, mixed>>> $connectionData */
            $connectionData = $this->debugDataHolder->getData();
        }

        $dbSignals = $this->databaseAnalyzer->analyze($connectionData);
        $dbSignals = $this->processQueryGroupParams($dbSignals);

        /**
         * @var array<string, array{
         *     calls: int, reads: int, writes: int,
         *     deletes: int, hits: int, misses: int, time: float,
         * }> $poolStats
         */
        $poolStats = $this->cacheDataCollector->getStatistics();
        $cacheSignals = $this->cacheAnalyzer->analyze($poolStats);

        $twigSignals = $this->twigAnalyzer->analyze($this->twigProfile);
        $eventSignals = $this->analyzeEvents();
        $httpSignals = $this->analyzeHttpClients();
        $otherResult = $this->otherSignalsAnalyzer->analyze(
            $this->traceRegistry->getRecords(),
        );

        // AdvisorEngine expects sync_handlers at root level
        $messengerSignals = $otherResult['messenger'];

        $opportunities = $this->advisorEngine->evaluate(
            $dbSignals,
            $cacheSignals,
            $twigSignals,
            $eventSignals,
            $httpSignals,
            $messengerSignals,
        );

        $performanceSignals = $this->analyzePerformance();

        $this->data['signals'] = [
            'db'          => array_merge($dbSignals, ['_available' => $this->debugDataHolder !== null]),
            'cache'       => $cacheSignals,
            'twig'        => $twigSignals,
            'events'      => $eventSignals,
            'http'        => array_merge($httpSignals, ['_available' => $this->httpClientDataCollector !== null]),
            'other'       => $otherResult,
            'performance' => array_merge($performanceSignals, ['_available' => $this->stopwatch !== null]),
        ];
        unset($this->data['stopwatch_token']);
        $this->data['opportunities'] = $opportunities;
        $this->data['summary'] = $this->buildSummary(
            $opportunities,
            $dbSignals,
            $twigSignals,
            $httpSignals,
            $messengerSignals,
        );

        $this->currentRequest = null;
    }

    #[Override]
    public function getName(): string
    {
        return 'optimization_advisor';
    }

    #[Override]
    public static function getTemplate(): string
    {
        return '@OptimizationAdvisor/data_collector/optimization_advisor.html.twig';
    }

    #[Override]
    public function reset(): void
    {
        parent::reset();

        $this->traceRegistry->reset();
        $this->currentRequest = null;
    }

    public function getCorrelationId(): string
    {
        $value = $this->data['correlation_id'] ?? '';

        return is_string($value) ? $value : '';
    }

    /** @return array{route: string, controller: string, method: string, uri: string} */
    public function getOrigin(): array
    {
        /** @var array<string, mixed> $origin */
        $origin = is_array($this->data['origin'] ?? null)
            ? $this->data['origin']
            : [];

        return [
            'route'      => is_string($origin['route'] ?? null)
                ? $origin['route'] : '',
            'controller' => is_string($origin['controller'] ?? null)
                ? $origin['controller'] : '',
            'method'     => is_string($origin['method'] ?? null)
                ? $origin['method'] : '',
            'uri'        => is_string($origin['uri'] ?? null)
                ? $origin['uri'] : '',
        ];
    }

    /** @return array<string, mixed> */
    public function getSignals(): array
    {
        /** @var array<string, mixed> $signals */
        $signals = is_array($this->data['signals'] ?? null)
            ? $this->data['signals']
            : [];

        return $signals;
    }

    /** @return array<int, array<string, mixed>> */
    public function getOpportunities(): array
    {
        /** @var array<int, array<string, mixed>> $opportunities */
        $opportunities = is_array($this->data['opportunities'] ?? null)
            ? $this->data['opportunities']
            : [];

        return $opportunities;
    }

    /** @return array<string, mixed> */
    public function getSummary(): array
    {
        /** @var array<string, mixed> $summary */
        $summary = is_array($this->data['summary'] ?? null)
            ? $this->data['summary']
            : [];

        return $summary;
    }

    /** @return array<string, mixed> */
    public function getDbSignals(): array
    {
        $signals = $this->getSignals();

        /** @var array<string, mixed> $db */
        $db = is_array($signals['db'] ?? null) ? $signals['db'] : [];

        return $db;
    }

    /** @return array<string, mixed> */
    public function getCacheSignals(): array
    {
        $signals = $this->getSignals();

        /** @var array<string, mixed> $cache */
        $cache = is_array($signals['cache'] ?? null)
            ? $signals['cache']
            : [];

        return $cache;
    }

    /** @return array<string, mixed> */
    public function getTwigSignals(): array
    {
        $signals = $this->getSignals();

        /** @var array<string, mixed> $twig */
        $twig = is_array($signals['twig'] ?? null)
            ? $signals['twig']
            : [];

        return $twig;
    }

    /** @return array<string, mixed> */
    public function getEventSignals(): array
    {
        $signals = $this->getSignals();

        /** @var array<string, mixed> $events */
        $events = is_array($signals['events'] ?? null)
            ? $signals['events']
            : [];

        return $events;
    }

    /** @return array<string, mixed> */
    public function getHttpSignals(): array
    {
        $signals = $this->getSignals();

        /** @var array<string, mixed> $http */
        $http = is_array($signals['http'] ?? null)
            ? $signals['http']
            : [];

        return $http;
    }

    /** @return array<string, mixed> */
    public function getOtherSignals(): array
    {
        $signals = $this->getSignals();

        /** @var array<string, mixed> $other */
        $other = is_array($signals['other'] ?? null)
            ? $signals['other']
            : [];

        return $other;
    }

    /** @return array<string, mixed> */
    public function getPerformanceSignals(): array
    {
        $signals = $this->getSignals();

        /** @var array<string, mixed> $performance */
        $performance = is_array($signals['performance'] ?? null)
            ? $signals['performance']
            : [];

        return $performance;
    }

    /** @return array<int, array<string, mixed>> */
    public function getQuickWins(): array
    {
        $quickWins = array_filter(
            $this->getOpportunities(),
            static fn (array $o): bool => ($o['is_quick_win'] ?? false) === true,
        );

        return $this->deduplicateByCode($quickWins);
    }

    /** @return array<int, array<string, mixed>> */
    public function getHighImpactOpportunities(): array
    {
        return array_values(
            array_filter(
                $this->getOpportunities(),
                static fn (array $o): bool => ($o['is_high_impact'] ?? false) === true,
            ),
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function getTopOpportunities(int $limit = 5): array
    {
        return array_slice($this->getOpportunities(), 0, $limit);
    }

    /** @return array<int, array<string, mixed>> */
    public function getRiskyChanges(): array
    {
        return array_values(
            array_filter(
                $this->getOpportunities(),
                static fn (array $o): bool => ($o['risk'] ?? '') === Risk::HIGH->value,
            ),
        );
    }

    /**
     * Sanitize query params and convert to VarDumper Data objects for profiler display.
     *
     * @param array<string, mixed> $dbSignals
     *
     * @return array<string, mixed>
     */
    private function processQueryGroupParams(array $dbSignals): array
    {
        if (!is_array($dbSignals['query_groups'] ?? null)) {
            return $dbSignals;
        }

        /** @var array<int, array<string, mixed>> $groups */
        $groups = $dbSignals['query_groups'];

        foreach ($groups as $index => $group) {
            /** @var array<array-key, mixed> $params */
            $params = is_array($group['example_params'] ?? null)
                ? $group['example_params']
                : [];

            $result = $this->queryParamSanitizer->sanitize($params);

            $groups[$index]['example_params'] = $this->cloneVar($result['params']);
            $groups[$index]['runnable'] = $result['runnable'];
            unset($groups[$index]['example_types']);
        }

        $dbSignals['query_groups'] = $groups;

        return $dbSignals;
    }

    /** @return array<string, mixed> */
    private function analyzeEvents(): array
    {
        $emptyResult = [
            'listeners'            => [],
            'total_listener_calls' => 0,
            'total_events_ms'      => 0.0,
            'not_called_count'     => 0,
        ];

        if (!$this->eventDispatcher instanceof TraceableEventDispatcher) {
            return $emptyResult;
        }

        $calledListeners = $this->eventDispatcher->getCalledListeners($this->currentRequest);
        $notCalledListeners = $this->eventDispatcher->getNotCalledListeners($this->currentRequest);

        $transformed = [];

        foreach ($calledListeners as $listener) {
            if (!is_array($listener)) {
                continue;
            }

            $event = $listener['event'] ?? '';
            $pretty = $listener['pretty'] ?? '';

            $transformed[] = [
                'event'  => is_string($event) ? $event : '',
                'pretty' => is_string($pretty) ? $pretty : '',
                'time'   => 0.0,
            ];
        }

        $notCalled = [];

        foreach ($notCalledListeners as $listener) {
            if (!is_array($listener)) {
                continue;
            }

            $event = $listener['event'] ?? '';
            $pretty = $listener['pretty'] ?? '';

            $notCalled[] = [
                'event'  => is_string($event) ? $event : '',
                'pretty' => is_string($pretty) ? $pretty : '',
            ];
        }

        return $this->eventAnalyzer->analyze($transformed, $notCalled);
    }

    /** @return array<string, mixed> */
    private function analyzeHttpClients(): array
    {
        if ($this->httpClientDataCollector === null) {
            return $this->emptyHttpResult();
        }

        try {
            return $this->extractHttpSignals($this->httpClientDataCollector);
        } catch (Throwable $e) {
            return $this->emptyHttpResult($e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function analyzePerformance(): array
    {
        $emptyResult = [
            'events'           => [],
            'request_duration' => 0.0,
            'event_count'      => 0,
            'top_three'        => [],
        ];

        if ($this->stopwatch === null) {
            return $emptyResult;
        }

        $token = $this->data['stopwatch_token'] ?? null;

        if (!is_string($token) || $token === '') {
            return $emptyResult;
        }

        /** @var array<string, StopwatchEvent> $sectionEvents */
        $sectionEvents = $this->stopwatch->getSectionEvents($token);

        $requestDuration = 0.0;
        $serialized = [];

        foreach ($sectionEvents as $name => $event) {
            $event->ensureStopped();

            $duration = $event->getDuration();
            $memory = $event->getMemory();
            $startTime = $event->getStartTime();
            $endTime = $event->getEndTime();
            $periods = $event->getPeriods();

            if ($name === '__section__') {
                $requestDuration = (float) $duration;

                continue;
            }

            $serialized[] = [
                'name'         => $name,
                'category'     => $event->getCategory(),
                'duration'     => (float) $duration,
                'memory'       => $memory,
                'start_time'   => (float) $startTime,
                'end_time'     => (float) $endTime,
                'period_count' => count($periods),
            ];
        }

        return $this->performanceAnalyzer->analyze($serialized, $requestDuration);
    }

    /** @return array<string, mixed> */
    private function emptyHttpResult(string $error = ''): array
    {
        $result = [
            'calls'            => [],
            'total_http_calls' => 0,
            'total_http_ms'    => 0.0,
        ];

        if ($error !== '') {
            $result['error'] = $error;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function extractHttpSignals(HttpClientDataCollector $collector): array
    {
        /**
         * @var array<string, array<int, array{
         *     method: string, url: string,
         *     http_code: int, duration: float,
         * }>> $clientsData
         */
        $clientsData = [];

        /** @var array<string, array<string, mixed>> $clients */
        $clients = $collector->getClients();

        foreach ($clients as $name => $clientInfo) {
            $traces = $clientInfo['traces'] ?? [];

            if (!is_array($traces) && !$traces instanceof Traversable) {
                continue;
            }

            $processed = [];

            foreach ($traces as $trace) {
                if (!is_array($trace)) {
                    continue;
                }

                /** @var array<string, mixed> $typedTrace */
                $typedTrace = $trace;

                $method = is_string($typedTrace['method'] ?? null)
                    ? $typedTrace['method'] : 'GET';
                $url = is_string($typedTrace['url'] ?? null)
                    ? $typedTrace['url'] : '';
                $httpCode = is_int($typedTrace['http_code'] ?? null)
                    ? $typedTrace['http_code'] : 0;

                $duration = $this->extractHttpDuration($typedTrace);

                $processed[] = [
                    'method'    => $method,
                    'url'       => $url,
                    'http_code' => $httpCode,
                    'duration'  => $duration,
                ];
            }

            if ($processed === []) {
                continue;
            }

            $clientsData[$name] = $processed;
        }

        if ($clientsData === []) {
            return $this->emptyHttpResult();
        }

        return $this->httpClientAnalyzer->analyze($clientsData);
    }

    /** @param array<string, mixed> $trace */
    private function extractHttpDuration(array $trace): float
    {
        $info = $trace['info'] ?? null;

        if ($info instanceof Data) {
            $rawInfo = $info->getValue(true);

            if (is_array($rawInfo)) {
                $totalTime = $rawInfo['total_time']
                    ?? $rawInfo['info']['total_time']
                    ?? 0.0;

                if (is_float($totalTime) || is_int($totalTime)) {
                    return (float) $totalTime;
                }
            }
        }

        return 0.0;
    }

    /**
     * @param array<int, array<string, mixed>> $opportunities
     * @param array<string, mixed>             $dbSignals
     * @param array<string, mixed>             $twigSignals
     * @param array<string, mixed>             $httpSignals
     * @param array<string, mixed>             $messengerSignals
     *
     * @return array<string, mixed>
     */
    private function buildSummary(
        array $opportunities,
        array $dbSignals,
        array $twigSignals,
        array $httpSignals,
        array $messengerSignals,
    ): array {
        $quickWinCount = 0;
        $highImpactCount = 0;

        foreach ($opportunities as $opportunity) {
            if (($opportunity['is_quick_win'] ?? false) === true) {
                $quickWinCount += 1;
            }

            if (!(($opportunity['is_high_impact'] ?? false) === true)) {
                continue;
            }

            $highImpactCount += 1;
        }

        $totalDb = $this->extractFloat($dbSignals, 'total_db_ms');
        $totalTwig = $this->extractFloat($twigSignals, 'total_twig_ms');
        $totalHttp = $this->extractFloat($httpSignals, 'total_http_ms');
        $totalMessenger = $this->extractFloat($messengerSignals, 'total_sync_ms');
        $optimizationScore = $this->computeOptimizationScore($opportunities);

        return [
            'opportunity_count'  => count($opportunities),
            'quick_win_count'    => $quickWinCount,
            'high_impact_count'  => $highImpactCount,
            'optimization_score' => $optimizationScore,
            'total_db_ms'        => $totalDb,
            'total_twig_ms'      => $totalTwig,
            'total_http_ms'      => $totalHttp,
            'total_messenger_ms' => $totalMessenger,
        ];
    }

    /** @param array<string, mixed> $data */
    private function extractFloat(array $data, string $key): float
    {
        $value = $data[$key] ?? 0.0;

        return is_float($value) || is_int($value) ? (float) $value : 0.0;
    }

    /**
     * Compute a 0-100 optimization score based on detected opportunities.
     *
     * Deducts points per opportunity weighted by impact.
     *
     * @param array<int, array<string, mixed>> $opportunities
     */
    private function computeOptimizationScore(array $opportunities): int
    {
        $penalty = 0.0;

        foreach ($opportunities as $opportunity) {
            $impact = is_int($opportunity['impact'] ?? null) ? $opportunity['impact'] : 1;
            $confidence = is_int($opportunity['confidence'] ?? null) ? $opportunity['confidence'] : 1;
            $penalty += $impact * $confidence * 0.5;
        }

        return max(0, (int) round(100 - $penalty));
    }

    /**
     * Deduplicate opportunities by code, keeping the highest-ROI entry per code.
     *
     * @param array<int|string, array<string, mixed>> $opportunities
     *
     * @return array<int, array<string, mixed>>
     */
    private function deduplicateByCode(array $opportunities): array
    {
        $byCode = [];
        $counts = [];

        foreach ($opportunities as $opportunity) {
            $code = is_string($opportunity['code'] ?? null) ? $opportunity['code'] : '';
            $roi = is_float($opportunity['roi'] ?? null) || is_int($opportunity['roi'] ?? null)
                ? (float) $opportunity['roi']
                : 0.0;

            $counts[$code] = ($counts[$code] ?? 0) + 1;

            $existingRoi = $byCode[$code]['roi'] ?? 0.0;
            $existingRoi = is_float($existingRoi) || is_int($existingRoi) ? (float) $existingRoi : 0.0;

            if (isset($byCode[$code]) && $roi <= $existingRoi) {
                continue;
            }

            $byCode[$code] = $opportunity;
        }

        foreach ($byCode as $code => $opportunity) {
            $byCode[$code]['occurrence_count'] = $counts[$code];
            $genericLabel = OpportunityCode::tryFrom($code)?->label();

            if ($genericLabel === null) {
                continue;
            }

            $byCode[$code]['title'] = $genericLabel;
        }

        return array_values($byCode);
    }
}
