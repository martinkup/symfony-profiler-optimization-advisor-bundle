<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Analyzer;

use MartinKup\OptimizationAdvisorBundle\Enum\DataOrigin;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class EventAnalyzer
{
    /**
     * @param array<int, string> $profilerNamespacePrefixes
     * @param array<int, string> $profilerListenerClasses
     */
    public function __construct(
        #[Autowire('%optimization_advisor.app_namespace_prefix%')]
        private string $appNamespacePrefix = 'App\\',
        #[Autowire('%optimization_advisor.profiler_event_namespace_prefixes%')]
        private array $profilerNamespacePrefixes = ['Symfony\\Bundle\\WebProfilerBundle\\'],
        #[Autowire('%optimization_advisor.profiler_event_classes%')]
        private array $profilerListenerClasses = ['Symfony\\Component\\HttpKernel\\EventListener\\ProfilerListener'],
    ) {
    }

    /**
     * Analyze called and not-called event listeners.
     *
     * @param array<int, array{event: string, pretty: string, time: float}> $calledListeners
     * @param array<int, array{event: string, pretty: string}>              $notCalledListeners
     *
     * @return array{
     *     listeners: array<int, array{
     *         event: string,
     *         listener: string,
     *         listener_class: string,
     *         origin: string,
     *         calls: int,
     *         total_ms: float,
     *         max_ms: float,
     *         avg_ms: float,
     *     }>,
     *     total_listener_calls: int,
     *     total_events_ms: float,
     *     not_called_count: int,
     *     unique_listeners: int,
     *     unique_events: int,
     *     app_listener_calls: int,
     *     app_events_ms: float,
     *     infra_listener_calls: int,
     *     infra_events_ms: float,
     *     profiler_listener_calls: int,
     *     profiler_events_ms: float,
     * }
     */
    public function analyze(array $calledListeners, array $notCalledListeners): array
    {
        /** @var array<string, array{
         *     event: string,
         *     listener: string,
         *     listener_class: string,
         *     calls: int,
         *     total_ms: float,
         *     max_ms: float,
         * }> $groups
         */
        $groups = [];

        foreach ($calledListeners as $entry) {
            $event = $entry['event'];
            $listenerClass = $entry['pretty'];
            $timeMs = $entry['time'];
            $groupKey = $event . '::' . $listenerClass;

            if (isset($groups[$groupKey])) {
                $groups[$groupKey]['calls'] += 1;
                $groups[$groupKey]['total_ms'] += $timeMs;
                $groups[$groupKey]['max_ms'] = max($groups[$groupKey]['max_ms'], $timeMs);
            } else {
                $groups[$groupKey] = [
                    'event'          => $event,
                    'listener'       => self::shortenClass($listenerClass),
                    'listener_class' => $listenerClass,
                    'calls'          => 1,
                    'total_ms'       => $timeMs,
                    'max_ms'         => $timeMs,
                ];
            }
        }

        $listeners = array_values($groups);

        usort(
            $listeners,
            static fn (array $a, array $b): int => $b['total_ms'] <=> $a['total_ms'],
        );

        $appListenerCalls = 0;
        $appEventsMs = 0.0;
        $infraListenerCalls = 0;
        $infraEventsMs = 0.0;
        $profilerListenerCalls = 0;
        $profilerEventsMs = 0.0;

        $result = [];

        foreach ($listeners as $group) {
            $origin = $this->classifyListener($group['listener_class']);

            if ($origin === DataOrigin::APP) {
                $appListenerCalls += $group['calls'];
                $appEventsMs += $group['total_ms'];
            } elseif ($origin === DataOrigin::PROFILER) {
                $profilerListenerCalls += $group['calls'];
                $profilerEventsMs += $group['total_ms'];
            } else {
                $infraListenerCalls += $group['calls'];
                $infraEventsMs += $group['total_ms'];
            }

            $result[] = [
                'event'          => $group['event'],
                'listener'       => $group['listener'],
                'listener_class' => $group['listener_class'],
                'origin'         => $origin->value,
                'calls'          => $group['calls'],
                'total_ms'       => $group['total_ms'],
                'max_ms'         => $group['max_ms'],
                'avg_ms'         => $group['calls'] > 0 ? $group['total_ms'] / $group['calls'] : 0.0,
            ];
        }

        $uniqueEvents = [];

        foreach ($result as $listener) {
            $uniqueEvents[$listener['event']] = true;
        }

        return [
            'listeners'               => $result,
            'total_listener_calls'    => $appListenerCalls + $infraListenerCalls,
            'total_events_ms'         => $appEventsMs + $infraEventsMs,
            'not_called_count'        => count($notCalledListeners),
            'unique_listeners'        => count($result),
            'unique_events'           => count($uniqueEvents),
            'app_listener_calls'      => $appListenerCalls,
            'app_events_ms'           => $appEventsMs,
            'infra_listener_calls'    => $infraListenerCalls,
            'infra_events_ms'         => $infraEventsMs,
            'profiler_listener_calls' => $profilerListenerCalls,
            'profiler_events_ms'      => $profilerEventsMs,
        ];
    }

    private function classifyListener(string $listenerClass): DataOrigin
    {
        foreach ($this->profilerNamespacePrefixes as $prefix) {
            if (str_starts_with($listenerClass, $prefix)) {
                return DataOrigin::PROFILER;
            }
        }

        if (in_array($listenerClass, $this->profilerListenerClasses, true)) {
            return DataOrigin::PROFILER;
        }

        if (str_starts_with($listenerClass, $this->appNamespacePrefix)) {
            return DataOrigin::APP;
        }

        return DataOrigin::INFRA;
    }

    private static function shortenClass(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos !== false ? substr($fqcn, $pos + 1) : $fqcn;
    }
}
