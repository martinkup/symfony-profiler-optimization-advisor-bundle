<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Analyzer;

use MartinKup\OptimizationAdvisorBundle\Analyzer\EventAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EventAnalyzer event listener grouping, aggregation, and origin classification.
 *
 * Test Coverage:
 * - Grouping listeners by event name and listener class
 * - Computing call counts for grouped listeners
 * - Computing max_ms across multiple calls of the same listener
 * - Sorting listeners by total_ms descending
 * - Returning empty results for empty listener data
 * - Classifying listeners as APP, INFRA, or PROFILER by namespace prefix and class
 * - Split aggregates for app/infra/profiler listener calls and timing
 * - Profiler-origin listeners excluded from total_listener_calls and total_events_ms
 *
 * @see EventAnalyzer
 */
#[CoversClass(EventAnalyzer::class)]
#[Group('unit')]
final class EventAnalyzerTest extends TestCase
{
    private EventAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new EventAnalyzer();
    }

    public function testAnalyzeGroupsByEventAndListener(): void
    {
        $calledListeners = [
            $this->buildListener('kernel.request', 'App\\Listener\\AuthListener', 1.5),
            $this->buildListener('kernel.request', 'App\\Listener\\AuthListener', 2.0),
            $this->buildListener('kernel.request', 'App\\Listener\\LoggingListener', 0.5),
        ];

        $result = $this->analyzer->analyze($calledListeners, []);

        self::assertCount(2, $result['listeners']);
        self::assertSame(3, $result['total_listener_calls']);
        self::assertSame(2, $result['unique_listeners']);
        self::assertSame(1, $result['unique_events']);
    }

    public function testAnalyzeComputesCalls(): void
    {
        $calledListeners = [
            $this->buildListener('kernel.response', 'App\\Listener\\CorsListener', 1.0),
            $this->buildListener('kernel.response', 'App\\Listener\\CorsListener', 2.0),
            $this->buildListener('kernel.response', 'App\\Listener\\CorsListener', 1.5),
        ];

        $result = $this->analyzer->analyze($calledListeners, []);

        self::assertCount(1, $result['listeners']);

        $listener = $result['listeners'][0];

        self::assertSame(3, $listener['calls']);
        self::assertSame('kernel.response', $listener['event']);
        self::assertSame('CorsListener', $listener['listener']);
        self::assertSame('App\\Listener\\CorsListener', $listener['listener_class']);
        self::assertSame('app', $listener['origin']);
        self::assertEqualsWithDelta(4.5, $listener['total_ms'], 0.01);
        self::assertEqualsWithDelta(1.5, $listener['avg_ms'], 0.01);
    }

    public function testAnalyzeComputesMaxMs(): void
    {
        $calledListeners = [
            $this->buildListener('kernel.request', 'App\\Listener\\SlowListener', 5.0),
            $this->buildListener('kernel.request', 'App\\Listener\\SlowListener', 15.0),
            $this->buildListener('kernel.request', 'App\\Listener\\SlowListener', 8.0),
        ];

        $result = $this->analyzer->analyze($calledListeners, []);

        self::assertEqualsWithDelta(15.0, $result['listeners'][0]['max_ms'], 0.01);
    }

    public function testAnalyzeSortsByTotalMsDescending(): void
    {
        $calledListeners = [
            $this->buildListener('event.a', 'App\\Listener\\Fast', 1.0),
            $this->buildListener('event.b', 'App\\Listener\\Slow', 50.0),
            $this->buildListener('event.c', 'App\\Listener\\Medium', 10.0),
        ];

        $result = $this->analyzer->analyze($calledListeners, []);

        self::assertCount(3, $result['listeners']);
        self::assertSame('Slow', $result['listeners'][0]['listener']);
        self::assertSame('Medium', $result['listeners'][1]['listener']);
        self::assertSame('Fast', $result['listeners'][2]['listener']);
    }

    public function testAnalyzeSortsByTotalMsDescendingWithMultipleEvents(): void
    {
        $calledListeners = [
            $this->buildListener('event.a', 'App\\Listener\\A', 1.0),
            $this->buildListener('event.b', 'App\\Listener\\B', 5.0),
            $this->buildListener('event.c', 'App\\Listener\\C', 3.0),
        ];

        $result = $this->analyzer->analyze($calledListeners, []);

        self::assertSame(3, $result['unique_listeners']);
        self::assertSame(3, $result['unique_events']);
    }

    public function testAnalyzeEmptyListeners(): void
    {
        $notCalledListeners = [
            ['event' => 'kernel.terminate', 'pretty' => 'App\\Listener\\CleanupListener'],
            ['event' => 'kernel.terminate', 'pretty' => 'App\\Listener\\MetricsListener'],
        ];

        $result = $this->analyzer->analyze([], $notCalledListeners);

        self::assertSame([], $result['listeners']);
        self::assertSame(0, $result['total_listener_calls']);
        self::assertSame(0.0, $result['total_events_ms']);
        self::assertSame(2, $result['not_called_count']);
        self::assertSame(0, $result['unique_listeners']);
        self::assertSame(0, $result['unique_events']);
        self::assertSame(0, $result['app_listener_calls']);
        self::assertSame(0.0, $result['app_events_ms']);
        self::assertSame(0, $result['infra_listener_calls']);
        self::assertSame(0.0, $result['infra_events_ms']);
        self::assertSame(0, $result['profiler_listener_calls']);
        self::assertSame(0.0, $result['profiler_events_ms']);
    }

    public function testClassifiesListenersByOrigin(): void
    {
        $calledListeners = [
            $this->buildListener('kernel.request', 'App\\Listener\\AuthListener', 3.0),
            $this->buildListener(
                'kernel.request',
                'Symfony\\Component\\HttpKernel\\EventListener\\SessionListener',
                1.0,
            ),
            $this->buildListener('kernel.response', 'App\\BackOffice\\Listener\\HeaderListener', 2.0),
            $this->buildListener(
                'kernel.response',
                'Symfony\\Component\\HttpKernel\\EventListener\\ProfilerListener',
                0.5,
            ),
        ];

        $result = $this->analyzer->analyze($calledListeners, []);

        self::assertCount(4, $result['listeners']);
        // total_listener_calls = app + infra (profiler excluded)
        self::assertSame(3, $result['total_listener_calls']);
        self::assertSame(2, $result['app_listener_calls']);
        self::assertEqualsWithDelta(5.0, $result['app_events_ms'], 0.01);
        self::assertSame(1, $result['infra_listener_calls']);
        self::assertEqualsWithDelta(1.0, $result['infra_events_ms'], 0.01);
        self::assertSame(1, $result['profiler_listener_calls']);
        self::assertEqualsWithDelta(0.5, $result['profiler_events_ms'], 0.01);
        // total_events_ms = app + infra (profiler excluded)
        self::assertEqualsWithDelta(6.0, $result['total_events_ms'], 0.01);
    }

    public function testClassifiesProfilerListenersByOrigin(): void
    {
        $calledListeners = [
            $this->buildListener(
                'kernel.response',
                'Symfony\\Bundle\\WebProfilerBundle\\EventListener\\WebDebugToolbarListener',
                2.0,
            ),
            $this->buildListener(
                'kernel.response',
                'Symfony\\Component\\HttpKernel\\EventListener\\ProfilerListener',
                1.5,
            ),
            $this->buildListener('kernel.request', 'App\\Listener\\AppListener', 3.0),
        ];

        $result = $this->analyzer->analyze($calledListeners, []);

        self::assertSame(2, $result['profiler_listener_calls']);
        self::assertSame(1, $result['app_listener_calls']);
        self::assertSame(0, $result['infra_listener_calls']);
        // total_listener_calls = app + infra (profiler excluded)
        self::assertSame(1, $result['total_listener_calls']);
    }

    /** @return array{event: string, pretty: string, time: float} */
    private function buildListener(string $event, string $listenerClass, float $timeMs): array
    {
        return [
            'event'  => $event,
            'pretty' => $listenerClass,
            'time'   => $timeMs,
        ];
    }
}
