<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Engine;

use MartinKup\OptimizationAdvisorBundle\Enum\DataOrigin;
use MartinKup\OptimizationAdvisorBundle\Enum\OpportunityCategory;
use MartinKup\OptimizationAdvisorBundle\Enum\OpportunityCode;
use MartinKup\OptimizationAdvisorBundle\Enum\Risk;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Evaluates profiling signals and produces scored optimization opportunities.
 *
 * Each opportunity carries impact/effort/confidence scores, ROI, risk level,
 * evidence references, recommended actions, and deduplication fingerprint.
 * Results are deduplicated by fingerprint and sorted by ROI descending.
 */
final readonly class AdvisorEngine
{
    public function __construct(
        #[Autowire('%optimization_advisor.slow_query_ms%')]
        private float $slowQueryMs = 30.0,
        #[Autowire('%optimization_advisor.n_plus_one_count%')]
        private int $nPlusOneCount = 10,
        #[Autowire('%optimization_advisor.slow_listener_ms%')]
        private float $slowListenerMs = 10.0,
        #[Autowire('%optimization_advisor.max_items%')]
        private int $maxItems = 200,
    ) {
    }

    /**
     * @param array<string, mixed> $dbSignals
     * @param array<string, mixed> $cacheSignals
     * @param array<string, mixed> $twigSignals
     * @param array<string, mixed> $eventSignals
     * @param array<string, mixed> $httpSignals
     * @param array<string, mixed> $otherSignals
     *
     * @return array<int, array<string, mixed>>
     */
    public function evaluate(
        array $dbSignals,
        array $cacheSignals,
        array $twigSignals,
        array $eventSignals,
        array $httpSignals,
        array $otherSignals,
    ): array {
        $opportunities = array_merge(
            $this->detectSlowQueryGroups($dbSignals),
            $this->detectNPlusOne($dbSignals),
            $this->detectDuplicateQueries($dbSignals),
            $this->detectLargeResultsets($dbSignals),
            $this->detectCacheCandidates($dbSignals),
            $this->detectLowHitratePool($cacheSignals),
            $this->detectDoctrine2LCOpportunity($dbSignals),
            $this->detectTwigHotTemplate($twigSignals),
            $this->detectTwigDupRender($twigSignals),
            $this->detectTooManyListeners($eventSignals),
            $this->detectSlowListener($eventSignals),
            $this->detectHttpSlowEndpoint($httpSignals),
            $this->detectHttpDupCall($httpSignals),
            $this->detectMessengerSyncHeavy($otherSignals),
        );

        $opportunities = $this->deduplicateByFingerprint($opportunities);

        usort(
            $opportunities,
            static fn (array $a, array $b): int => $b['roi'] <=> $a['roi'],
        );

        return array_slice($opportunities, 0, $this->maxItems);
    }

    /**
     * @param array<string, mixed> $dbSignals
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectSlowQueryGroups(array $dbSignals): array
    {
        $opportunities = [];

        foreach ($this->getQueryGroups($dbSignals) as $group) {
            $totalMs = self::floatVal($group, 'total_ms');

            if ($totalMs < $this->slowQueryMs) {
                continue;
            }

            $opportunities[] = $this->buildOpportunity(
                code: OpportunityCode::PG_SLOW_QUERY_GROUP,
                title: 'Slow query group detected (total ' . round($totalMs, 1) . ' ms)',
                category: OpportunityCategory::DB,
                impact: 4,
                effort: 3,
                confidence: 4,
                risk: Risk::MED,
                why: 'This query group accumulates significant execution time. '
                . 'Consider adding indexes or optimizing the query pattern.',
                evidenceRefs: [self::stringVal($group, 'pattern')],
                recommendedActions: [
                    'Run EXPLAIN ANALYZE on representative query',
                    'Check for missing indexes on filtered columns',
                    'Consider query result caching if data is stable',
                ],
                expectedGain: 'Reduced DB load and faster response time',
                safetyNotes: 'Index changes require migration; test on staging first',
            );
        }

        return $opportunities;
    }

    /**
     * @param array<string, mixed> $dbSignals
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectNPlusOne(array $dbSignals): array
    {
        $opportunities = [];

        foreach ($this->getQueryGroups($dbSignals) as $group) {
            $count = self::intVal($group, 'count');
            $avgMs = self::floatVal($group, 'avg_ms');
            $kind = self::stringVal($group, 'kind');

            if ($kind !== 'SELECT' || $count < $this->nPlusOneCount || $avgMs >= 5.0) {
                continue;
            }

            $opportunities[] = $this->buildOpportunity(
                code: OpportunityCode::PG_N_PLUS_ONE_SUSPECTED,
                title: 'N+1 query pattern suspected (' . $count . ' fast SELECTs)',
                category: OpportunityCategory::DB,
                impact: 5,
                effort: 3,
                confidence: 3,
                risk: Risk::MED,
                why: 'Many identical fast queries suggest an N+1 loop. '
                . 'Eager loading or batching can eliminate most of these round-trips.',
                evidenceRefs: [self::stringVal($group, 'pattern')],
                recommendedActions: [
                    'Use Doctrine fetch join or eager loading',
                    'Batch queries with IN clause',
                    'Consider Doctrine second-level cache for entity loads',
                ],
                expectedGain: 'Significant reduction in query count and latency',
                safetyNotes: 'Eager loading may increase memory usage; profile after change',
            );
        }

        return $opportunities;
    }

    /**
     * @param array<string, mixed> $dbSignals
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectDuplicateQueries(array $dbSignals): array
    {
        $opportunities = [];

        foreach ($this->getQueryGroups($dbSignals) as $group) {
            $count = self::intVal($group, 'count');

            if ($count < 3) {
                continue;
            }

            $opportunities[] = $this->buildOpportunity(
                code: OpportunityCode::PG_DUPLICATE_QUERY,
                title: 'Duplicate query executed ' . $count . ' times',
                category: OpportunityCategory::DB,
                impact: 3,
                effort: 1,
                confidence: 5,
                risk: Risk::LOW,
                why: 'The same query pattern runs multiple times in a single request. '
                . 'Caching or restructuring can avoid redundant DB calls.',
                evidenceRefs: [self::stringVal($group, 'pattern')],
                recommendedActions: [
                    'Cache result in a local variable or service-level memo',
                    'Use Doctrine result cache for stable data',
                    'Refactor caller to avoid repeated lookups',
                ],
                expectedGain: 'Fewer round-trips to the database per request',
                safetyNotes: 'Ensure cached value is not stale within the request lifecycle',
            );
        }

        return $opportunities;
    }

    /**
     * @param array<string, mixed> $dbSignals
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectLargeResultsets(array $dbSignals): array
    {
        $opportunities = [];

        foreach ($this->getQueryGroups($dbSignals) as $group) {
            $maxMs = self::floatVal($group, 'max_ms');
            $count = self::intVal($group, 'count');
            $kind = self::stringVal($group, 'kind');

            if ($kind !== 'SELECT' || $maxMs <= 100.0 || $count > 3) {
                continue;
            }

            $opportunities[] = $this->buildOpportunity(
                code: OpportunityCode::PG_LARGE_RESULTSET,
                title: 'Large resultset suspected (single SELECT ' . round($maxMs, 1) . ' ms)',
                category: OpportunityCategory::DB,
                impact: 2,
                effort: 2,
                confidence: 2,
                risk: Risk::LOW,
                why: 'A single slow SELECT may indicate a large resultset or missing index. '
                . 'Adding LIMIT or pagination can reduce memory and transfer time.',
                evidenceRefs: [self::stringVal($group, 'pattern')],
                recommendedActions: [
                    'Add LIMIT/OFFSET or cursor-based pagination',
                    'Check EXPLAIN output for sequential scans',
                    'Consider partial loading or lazy collections',
                ],
                expectedGain: 'Reduced memory consumption and query time',
                safetyNotes: 'Pagination changes may affect UI/UX',
            );
        }

        return $opportunities;
    }

    /**
     * @param array<string, mixed> $dbSignals
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectCacheCandidates(array $dbSignals): array
    {
        $opportunities = [];

        foreach ($this->getQueryGroups($dbSignals) as $group) {
            $count = self::intVal($group, 'count');
            $kind = self::stringVal($group, 'kind');

            if ($kind !== 'SELECT' || $count < 3) {
                continue;
            }

            $opportunities[] = $this->buildOpportunity(
                code: OpportunityCode::CACHE_CANDIDATE_DB_RESULTS,
                title: 'Cache candidate: SELECT repeated ' . $count . ' times',
                category: OpportunityCategory::CACHE,
                impact: 4,
                effort: 2,
                confidence: 4,
                risk: Risk::MED,
                why: 'Repeated identical SELECTs are prime candidates for result caching. '
                . 'A cache layer can eliminate redundant database round-trips.',
                evidenceRefs: [self::stringVal($group, 'pattern')],
                recommendedActions: [
                    'Add Symfony cache pool for this query result',
                    'Use Doctrine result cache with TTL',
                    'Consider Doctrine second-level cache for entity loads',
                ],
                expectedGain: 'Eliminated repeated DB calls and faster response',
                safetyNotes: 'Set appropriate TTL; stale data risk depends on update frequency',
            );
        }

        return $opportunities;
    }

    /**
     * @param array<string, mixed> $cacheSignals
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectLowHitratePool(array $cacheSignals): array
    {
        $opportunities = [];
        $pools = self::getAppItems($cacheSignals, 'pools');

        foreach ($pools as $pool) {
            $hitRate = self::floatVal($pool, 'hit_rate', 100.0);
            $calls = self::intVal($pool, 'calls');

            if ($hitRate >= 50.0 || $calls < 10) {
                continue;
            }

            $poolName = self::stringVal($pool, 'pool', 'unknown');
            $opportunities[] = $this->buildOpportunity(
                code: OpportunityCode::CACHE_LOW_HITRATE_POOL,
                title: 'Low hit-rate cache pool "' . $poolName . '" (' . round($hitRate, 1) . '%)',
                category: OpportunityCategory::CACHE,
                impact: 3,
                effort: 2,
                confidence: 4,
                risk: Risk::LOW,
                why: 'This cache pool has a low hit rate despite significant call volume. '
                . 'Warming or key strategy adjustment can improve effectiveness.',
                evidenceRefs: [$poolName],
                recommendedActions: [
                    'Review cache key generation for consistency',
                    'Implement cache warming on deploy',
                    'Consider increasing TTL if data is stable',
                    'Check for accidental cache invalidation',
                ],
                expectedGain: 'Higher cache utilization and fewer origin lookups',
                safetyNotes: 'Increasing TTL may serve stale data; validate acceptable staleness',
            );
        }

        return $opportunities;
    }

    /**
     * @param array<string, mixed> $dbSignals
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectDoctrine2LCOpportunity(array $dbSignals): array
    {
        $opportunities = [];

        foreach ($this->getQueryGroups($dbSignals) as $group) {
            $count = self::intVal($group, 'count');
            $avgMs = self::floatVal($group, 'avg_ms');
            $kind = self::stringVal($group, 'kind');

            if ($kind !== 'SELECT' || $count < 5 || $avgMs >= 2.0) {
                continue;
            }

            $opportunities[] = $this->buildOpportunity(
                code: OpportunityCode::DOCTRINE_2LC_OPPORTUNITY,
                title: 'Doctrine 2LC opportunity (' . $count . ' fast entity loads)',
                category: OpportunityCategory::DB,
                impact: 3,
                effort: 3,
                confidence: 3,
                risk: Risk::MED,
                why: 'Many fast, identical SELECTs suggest entity loading that could benefit '
                . 'from Doctrine second-level cache.',
                evidenceRefs: [self::stringVal($group, 'pattern')],
                recommendedActions: [
                    'Enable Doctrine second-level cache for this entity',
                    'Configure cache region with appropriate TTL',
                    'Verify entity is cache-safe (no sensitive side effects)',
                ],
                expectedGain: 'Reduced DB round-trips for frequently loaded entities',
                safetyNotes: 'Second-level cache adds complexity; test invalidation behavior',
            );
        }

        return $opportunities;
    }

    /**
     * @param array<string, mixed> $twigSignals
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectTwigHotTemplate(array $twigSignals): array
    {
        $opportunities = [];
        $templates = self::getAppItems($twigSignals, 'templates');

        foreach ($templates as $template) {
            $totalMs = self::floatVal($template, 'total_ms');

            if ($totalMs < 50.0) {
                continue;
            }

            $templateName = self::stringVal($template, 'template', 'unknown');
            $opportunities[] = $this->buildOpportunity(
                code: OpportunityCode::TWIG_HOT_TEMPLATE,
                title: 'Hot Twig template "' . $templateName . '" (' . round($totalMs, 1) . ' ms)',
                category: OpportunityCategory::TWIG,
                impact: 3,
                effort: 3,
                confidence: 4,
                risk: Risk::LOW,
                why: 'This template consumes significant render time. '
                . 'Fragment caching or simplification can reduce render cost.',
                evidenceRefs: [$templateName],
                recommendedActions: [
                    'Profile template blocks for hotspots',
                    'Consider HTTP fragment caching (ESI/Hinclude)',
                    'Reduce complex logic in Twig; move to Twig Component',
                    'Cache computed data before passing to template',
                ],
                expectedGain: 'Faster page rendering and lower server CPU usage',
                safetyNotes: 'Fragment caching requires cache invalidation strategy',
            );
        }

        return $opportunities;
    }

    /**
     * @param array<string, mixed> $twigSignals
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectTwigDupRender(array $twigSignals): array
    {
        $opportunities = [];
        $templates = self::getAppItems($twigSignals, 'templates');

        foreach ($templates as $template) {
            $renders = self::intVal($template, 'renders');

            if ($renders < 10) {
                continue;
            }

            $templateName = self::stringVal($template, 'template', 'unknown');
            $opportunities[] = $this->buildOpportunity(
                code: OpportunityCode::TWIG_DUP_RENDER,
                title: 'Duplicate Twig render "' . $templateName . '" (' . $renders . ' renders)',
                category: OpportunityCategory::TWIG,
                impact: 2,
                effort: 2,
                confidence: 3,
                risk: Risk::LOW,
                why: 'This template is rendered many times per request. '
                . 'Consolidating renders or caching output can reduce overhead.',
                evidenceRefs: [$templateName],
                recommendedActions: [
                    'Check if renders can be consolidated into a single loop',
                    'Use Twig macro or include with cache',
                    'Consider rendering the list as a single component',
                ],
                expectedGain: 'Reduced Twig compilation and render overhead',
                safetyNotes: 'Consolidation may require template restructuring',
            );
        }

        return $opportunities;
    }

    /**
     * @param array<string, mixed> $eventSignals
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectTooManyListeners(array $eventSignals): array
    {
        $opportunities = [];
        $listeners = self::getAppItems($eventSignals, 'listeners');

        foreach ($listeners as $listener) {
            $calls = self::intVal($listener, 'calls');

            if ($calls <= 50) {
                continue;
            }

            $listenerName = self::stringVal($listener, 'listener', 'unknown');
            $eventName = self::stringVal($listener, 'event', 'unknown');
            $ref = $eventName . '::' . $listenerName;
            $opportunities[] = $this->buildOpportunity(
                code: OpportunityCode::EVENTS_TOO_MANY_LISTENERS,
                title: 'Excessive listener calls (' . $calls . ') for ' . $listenerName,
                category: OpportunityCategory::EVENTS,
                impact: 2,
                effort: 3,
                confidence: 3,
                risk: Risk::MED,
                why: 'This listener is invoked an unusually high number of times. '
                . 'Batching events or debouncing may reduce overhead.',
                evidenceRefs: [$ref],
                recommendedActions: [
                    'Batch events where possible to reduce dispatch count',
                    'Check if listener can be moved to async processing',
                    'Review event dispatching code for unnecessary triggers',
                ],
                expectedGain: 'Reduced event system overhead per request',
                safetyNotes: 'Moving to async may affect data consistency timing',
            );
        }

        return $opportunities;
    }

    /**
     * @param array<string, mixed> $eventSignals
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectSlowListener(array $eventSignals): array
    {
        $opportunities = [];
        $listeners = self::getAppItems($eventSignals, 'listeners');

        foreach ($listeners as $listener) {
            $maxMs = self::floatVal($listener, 'max_ms');

            if ($maxMs < $this->slowListenerMs) {
                continue;
            }

            $listenerName = self::stringVal($listener, 'listener', 'unknown');
            $opportunities[] = $this->buildOpportunity(
                code: OpportunityCode::EVENTS_SLOW_LISTENER,
                title: 'Slow event listener "' . $listenerName . '" (' . round($maxMs, 1) . ' ms)',
                category: OpportunityCategory::EVENTS,
                impact: 4,
                effort: 2,
                confidence: 4,
                risk: Risk::LOW,
                why: 'This listener adds significant latency to the request. '
                . 'Moving heavy work to an async handler can improve response time.',
                evidenceRefs: [$listenerName],
                recommendedActions: [
                    'Move heavy processing to async Messenger handler',
                    'Defer non-critical work to kernel.terminate',
                    'Profile listener for specific bottleneck',
                ],
                expectedGain: 'Faster synchronous event processing',
                safetyNotes: 'Async processing changes execution timing; verify no ordering dependency',
            );
        }

        return $opportunities;
    }

    /**
     * @param array<string, mixed> $httpSignals
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectHttpSlowEndpoint(array $httpSignals): array
    {
        $opportunities = [];
        /** @var array<int, array<string, mixed>> $calls */
        $calls = $httpSignals['calls'] ?? [];

        foreach ($calls as $call) {
            $maxMs = self::floatVal($call, 'max_ms');

            if ($maxMs < 500.0) {
                continue;
            }

            $endpoint = self::stringVal($call, 'endpoint_fingerprint', 'unknown');
            $opportunities[] = $this->buildOpportunity(
                code: OpportunityCode::HTTP_SLOW_ENDPOINT,
                title: 'Slow HTTP endpoint (' . round($maxMs, 1) . ' ms)',
                category: OpportunityCategory::HTTP,
                impact: 4,
                effort: 3,
                confidence: 4,
                risk: Risk::MED,
                why: 'An external HTTP call adds significant latency to the request. '
                . 'Caching responses or moving to async can reduce impact.',
                evidenceRefs: [$endpoint],
                recommendedActions: [
                    'Cache HTTP response if data is stable',
                    'Move call to async Messenger handler',
                    'Add timeout and circuit breaker',
                    'Consider response payload reduction',
                ],
                expectedGain: 'Reduced dependency on external service latency',
                safetyNotes: 'Caching external responses requires invalidation strategy',
            );
        }

        return $opportunities;
    }

    /**
     * @param array<string, mixed> $httpSignals
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectHttpDupCall(array $httpSignals): array
    {
        $opportunities = [];
        /** @var array<int, array<string, mixed>> $calls */
        $calls = $httpSignals['calls'] ?? [];

        foreach ($calls as $call) {
            $callCount = self::intVal($call, 'calls');

            if ($callCount < 2) {
                continue;
            }

            $endpoint = self::stringVal($call, 'endpoint_fingerprint', 'unknown');
            $opportunities[] = $this->buildOpportunity(
                code: OpportunityCode::HTTP_DUP_CALL,
                title: 'Duplicate HTTP call (' . $callCount . ' calls to same endpoint)',
                category: OpportunityCategory::HTTP,
                impact: 3,
                effort: 2,
                confidence: 5,
                risk: Risk::LOW,
                why: 'The same external endpoint is called multiple times in one request. '
                . 'Deduplicating or batching can eliminate redundant network round-trips.',
                evidenceRefs: [$endpoint],
                recommendedActions: [
                    'Cache first response and reuse within request',
                    'Batch multiple calls into a single request if API supports it',
                    'Deduplicate at service layer with memoization',
                ],
                expectedGain: 'Fewer external HTTP round-trips per request',
                safetyNotes: 'Ensure cached response is valid for all callers within the request',
            );
        }

        return $opportunities;
    }

    /**
     * @param array<string, mixed> $otherSignals
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectMessengerSyncHeavy(array $otherSignals): array
    {
        $opportunities = [];
        $syncHandlers = self::getAppItems($otherSignals, 'sync_handlers');

        foreach ($syncHandlers as $handler) {
            $durationMs = self::floatVal($handler, 'duration_ms');

            if ($durationMs < 50.0) {
                continue;
            }

            $handlerName = self::stringVal($handler, 'handler_class', 'unknown');
            $opportunities[] = $this->buildOpportunity(
                code: OpportunityCode::MESSENGER_SYNC_HEAVY,
                title: 'Heavy sync Messenger handler "' . $handlerName . '" ('
                . round($durationMs, 1) . ' ms)',
                category: OpportunityCategory::MESSENGER,
                impact: 4,
                effort: 2,
                confidence: 4,
                risk: Risk::LOW,
                why: 'This synchronous Messenger handler adds significant latency. '
                . 'Routing to an async transport can free the request thread.',
                evidenceRefs: [$handlerName],
                recommendedActions: [
                    'Route message to async transport in messenger.yaml',
                    'Verify handler does not depend on immediate result',
                    'Add monitoring for async processing latency',
                ],
                expectedGain: 'Faster HTTP response by deferring heavy work',
                safetyNotes: 'Async routing changes execution timing; verify no caller depends on sync result',
            );
        }

        return $opportunities;
    }

    /**
     * @param array<int, string> $evidenceRefs
     * @param array<int, string> $recommendedActions
     *
     * @return array<string, mixed>
     */
    private function buildOpportunity(
        OpportunityCode $code,
        string $title,
        OpportunityCategory $category,
        int $impact,
        int $effort,
        int $confidence,
        Risk $risk,
        string $why,
        array $evidenceRefs,
        array $recommendedActions,
        string $expectedGain,
        string $safetyNotes,
    ): array {
        $roi = $effort > 0 ? $impact * $confidence / $effort : 0.0;
        $evidenceKey = implode('|', $evidenceRefs);
        $aiPrompt = $this->buildAiPrompt($title, $why, $evidenceRefs, $recommendedActions, $safetyNotes);

        return [
            'code'                => $code->value,
            'title'               => $title,
            'category'            => $category->value,
            'impact'              => $impact,
            'effort'              => $effort,
            'confidence'          => $confidence,
            'roi'                 => $roi,
            'risk'                => $risk->value,
            'why'                 => $why,
            'evidence_refs'       => $evidenceRefs,
            'recommended_actions' => $recommendedActions,
            'expected_gain'       => $expectedGain,
            'safety_notes'        => $safetyNotes,
            'ai_prompt'           => $aiPrompt,
            'fingerprint'         => md5($code->value . '|' . $evidenceKey),
            'is_quick_win'        => $effort <= 2 && $confidence >= 4,
            'is_high_impact'      => $impact >= 4,
        ];
    }

    /**
     * @param array<int, string> $evidenceRefs
     * @param array<int, string> $recommendedActions
     */
    private function buildAiPrompt(
        string $title,
        string $why,
        array $evidenceRefs,
        array $recommendedActions,
        string $safetyNotes,
    ): string {
        $lines = [
            'Optimization task: ' . $title,
            '',
            'Problem: ' . $why,
            '',
            'Evidence: ' . implode(', ', $evidenceRefs),
            '',
            'Recommended approach:',
        ];

        foreach ($recommendedActions as $i => $action) {
            $lines[] = ($i + 1) . '. ' . $action;
        }

        if ($safetyNotes !== '') {
            $lines[] = '';
            $lines[] = 'Safety: ' . $safetyNotes;
        }

        $lines[] = '';
        $lines[] = 'Please analyze the relevant code and implement the optimization.';

        return implode("\n", $lines);
    }

    /**
     * @param array<int, array<string, mixed>> $opportunities
     *
     * @return array<int, array<string, mixed>>
     */
    private function deduplicateByFingerprint(array $opportunities): array
    {
        $seen = [];
        $unique = [];

        foreach ($opportunities as $opportunity) {
            $fp = is_string($opportunity['fingerprint']) ? $opportunity['fingerprint'] : '';

            if (isset($seen[$fp])) {
                continue;
            }

            $seen[$fp] = true;
            $unique[] = $opportunity;
        }

        return $unique;
    }

    /**
     * @param array<string, mixed> $dbSignals
     *
     * @return array<int, array<string, mixed>>
     */
    private function getQueryGroups(array $dbSignals): array
    {
        /** @var array<int, array<string, mixed>> $groups */
        $groups = $dbSignals['query_groups'] ?? [];

        return array_values(
            array_filter(
                $groups,
                static fn (array $g): bool => ($g['origin'] ?? DataOrigin::APP->value) === DataOrigin::APP->value,
            ),
        );
    }

    /**
     * @param array<string, mixed> $signals
     *
     * @return array<int, array<string, mixed>>
     */
    private static function getAppItems(array $signals, string $key): array
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $signals[$key] ?? [];

        return array_values(
            array_filter(
                $items,
                static fn (array $item): bool => ($item['origin'] ?? DataOrigin::APP->value) === DataOrigin::APP->value,
            ),
        );
    }

    /** @param array<string, mixed> $data */
    private static function floatVal(array $data, string $key, float $default = 0.0): float
    {
        $value = $data[$key] ?? $default;

        return is_float($value) || is_int($value) ? (float) $value : $default;
    }

    /** @param array<string, mixed> $data */
    private static function intVal(array $data, string $key, int $default = 0): int
    {
        $value = $data[$key] ?? $default;

        return is_int($value) ? $value : $default;
    }

    /** @param array<string, mixed> $data */
    private static function stringVal(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }
}
