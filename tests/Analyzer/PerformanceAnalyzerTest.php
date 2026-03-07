<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Analyzer;

use MartinKup\OptimizationAdvisorBundle\Analyzer\PerformanceAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PerformanceAnalyzer Stopwatch event analysis.
 *
 * Test Coverage:
 * - Sorting events by duration descending (slowest first)
 * - Filtering out __section__ event
 * - Computing percent_of_total correctly
 * - Extracting top 3 slowest events
 * - Handling fewer than 3 events for top_three
 * - Handling empty input
 * - Handling zero request duration (no division by zero)
 * - Preserving memory and period_count pass-through
 *
 * @see PerformanceAnalyzer
 */
#[CoversClass(PerformanceAnalyzer::class)]
#[Group('unit')]
final class PerformanceAnalyzerTest extends TestCase
{
    private PerformanceAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new PerformanceAnalyzer();
    }

    public function testSortsEventsByDurationDescending(): void
    {
        $events = [
            $this->buildEvent('fast_event', duration: 10.0),
            $this->buildEvent('slow_event', duration: 200.0),
            $this->buildEvent('medium_event', duration: 50.0),
        ];

        $result = $this->analyzer->analyze($events, 500.0);

        self::assertSame('slow_event', $result['events'][0]['name']);
        self::assertSame('medium_event', $result['events'][1]['name']);
        self::assertSame('fast_event', $result['events'][2]['name']);
    }

    public function testFiltersSectionEvent(): void
    {
        $events = [
            $this->buildEvent('__section__', duration: 500.0),
            $this->buildEvent('app.handler', duration: 100.0),
        ];

        $result = $this->analyzer->analyze($events, 500.0);

        self::assertCount(1, $result['events']);
        self::assertSame('app.handler', $result['events'][0]['name']);
        self::assertSame(1, $result['event_count']);
    }

    public function testComputesPercentOfTotal(): void
    {
        $events = [
            $this->buildEvent('slow', duration: 250.0),
            $this->buildEvent('fast', duration: 50.0),
        ];

        $result = $this->analyzer->analyze($events, 500.0);

        self::assertSame(50.0, $result['events'][0]['percent_of_total']);
        self::assertSame(10.0, $result['events'][1]['percent_of_total']);
    }

    public function testExtractsTopThreeSlowest(): void
    {
        $events = [
            $this->buildEvent('a', duration: 10.0),
            $this->buildEvent('b', duration: 200.0),
            $this->buildEvent('c', duration: 50.0),
            $this->buildEvent('d', duration: 150.0),
        ];

        $result = $this->analyzer->analyze($events, 500.0);

        self::assertCount(3, $result['top_three']);
        self::assertSame('b', $result['top_three'][0]['name']);
        self::assertSame(200.0, $result['top_three'][0]['duration']);
        self::assertSame('d', $result['top_three'][1]['name']);
        self::assertSame(150.0, $result['top_three'][1]['duration']);
        self::assertSame('c', $result['top_three'][2]['name']);
        self::assertSame(50.0, $result['top_three'][2]['duration']);
    }

    public function testHandlesFewerThanThreeEventsForTopThree(): void
    {
        $events = [
            $this->buildEvent('only_one', duration: 100.0),
        ];

        $result = $this->analyzer->analyze($events, 500.0);

        self::assertCount(1, $result['top_three']);
        self::assertSame('only_one', $result['top_three'][0]['name']);
    }

    public function testHandlesEmptyInput(): void
    {
        $result = $this->analyzer->analyze([], 0.0);

        self::assertSame([], $result['events']);
        self::assertSame(0.0, $result['request_duration']);
        self::assertSame(0, $result['event_count']);
        self::assertSame([], $result['top_three']);
    }

    public function testHandlesZeroRequestDuration(): void
    {
        $events = [
            $this->buildEvent('some_event', duration: 50.0),
        ];

        $result = $this->analyzer->analyze($events, 0.0);

        self::assertSame(0.0, $result['events'][0]['percent_of_total']);
        self::assertSame(0.0, $result['request_duration']);
    }

    public function testPreservesMemoryAndPeriodCount(): void
    {
        $events = [
            $this->buildEvent('app.handler', duration: 100.0, memory: 2097152, periodCount: 3),
        ];

        $result = $this->analyzer->analyze($events, 500.0);

        self::assertSame(2097152, $result['events'][0]['memory']);
        self::assertSame(3, $result['events'][0]['period_count']);
    }

    /**
     * @return array{
     *     name: string,
     *     category: string,
     *     duration: float,
     *     memory: int,
     *     start_time: float,
     *     end_time: float,
     *     period_count: int,
     * }
     */
    private function buildEvent(
        string $name,
        string $category = 'default',
        float $duration = 0.0,
        int $memory = 0,
        float $startTime = 0.0,
        ?float $endTime = null,
        int $periodCount = 1,
    ): array {
        return [
            'name'         => $name,
            'category'     => $category,
            'duration'     => $duration,
            'memory'       => $memory,
            'start_time'   => $startTime,
            'end_time'     => $endTime ?? $startTime + $duration,
            'period_count' => $periodCount,
        ];
    }
}
