<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Analyzer;

/**
 * Analyzes Stopwatch events to produce a sorted performance breakdown.
 *
 * Receives pre-serialized event arrays and the total request duration,
 * then sorts by duration (slowest first) and computes aggregate metrics.
 */
final readonly class PerformanceAnalyzer
{
    /**
     * Analyze pre-serialized Stopwatch events.
     *
     * @param array<int, array{
     *     name: string,
     *     category: string,
     *     duration: float,
     *     memory: int,
     *     start_time: float,
     *     end_time: float,
     *     period_count: int,
     * }> $events
     *
     * @return array{
     *     events: list<array{
     *         name: string,
     *         category: string,
     *         duration: float,
     *         memory: int,
     *         start_time: float,
     *         end_time: float,
     *         period_count: int,
     *         percent_of_total: float,
     *     }>,
     *     request_duration: float,
     *     event_count: int,
     *     top_three: list<array{name: string, duration: float}>,
     * }
     */
    public function analyze(array $events, float $requestDuration): array
    {
        $filtered = array_values(
            array_filter(
                $events,
                static fn (array $event): bool => $event['name'] !== '__section__',
            ),
        );

        usort(
            $filtered,
            static fn (array $a, array $b): int => $b['duration'] <=> $a['duration'],
        );

        $enriched = [];

        foreach ($filtered as $event) {
            $percentOfTotal = $requestDuration > 0.0
                ? $event['duration'] / $requestDuration * 100.0
                : 0.0;

            $enriched[] = [
                'name'             => $event['name'],
                'category'         => $event['category'],
                'duration'         => $event['duration'],
                'memory'           => $event['memory'],
                'start_time'       => $event['start_time'],
                'end_time'         => $event['end_time'],
                'period_count'     => $event['period_count'],
                'percent_of_total' => round($percentOfTotal, 2),
            ];
        }

        $topThree = array_map(
            static fn (array $event): array => [
                'name'     => $event['name'],
                'duration' => $event['duration'],
            ],
            array_slice($enriched, 0, 3),
        );

        return [
            'events'           => $enriched,
            'request_duration' => $requestDuration,
            'event_count'      => count($enriched),
            'top_three'        => $topThree,
        ];
    }
}
