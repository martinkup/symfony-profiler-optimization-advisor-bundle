<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Analyzer;

use MartinKup\OptimizationAdvisorBundle\Enum\DataOrigin;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class OtherSignalsAnalyzer
{
    public function __construct(
        #[Autowire('%optimization_advisor.app_namespace_prefix%')]
        private string $appNamespacePrefix = 'App\\',
    ) {
    }

    /**
     * Analyze messenger trace records for sync handler signals.
     *
     * @param array<int, array<string, mixed>> $records TraceRegistry::getRecords() format
     *
     * @return array{
     *     messenger: array{
     *         sync_handlers: list<array{
     *             handler_class: string,
     *             handler_class_full: string,
     *             message_short: string,
     *             origin: string,
     *             duration_ms: float,
     *         }>,
     *         total_sync_ms: float,
     *         sync_count: int,
     *         app_sync_ms: float,
     *         app_sync_count: int,
     *         infra_sync_ms: float,
     *         infra_sync_count: int,
     *         profiler_sync_ms: float,
     *         profiler_sync_count: int,
     *     },
     * }
     */
    public function analyze(array $records): array
    {
        $syncHandlers = [];
        $appSyncMs = 0.0;
        $appSyncCount = 0;
        $infraSyncMs = 0.0;
        $infraSyncCount = 0;

        foreach ($records as $record) {
            if (($record['is_handled_sync'] ?? false) !== true) {
                continue;
            }

            $handlerClassFull = is_string($record['handler_class'] ?? null) ? $record['handler_class'] : '';
            $rawDuration = $record['handler_duration_ms'] ?? 0.0;
            $durationMs = is_float($rawDuration) || is_int($rawDuration) ? (float) $rawDuration : 0.0;
            $messageShort = is_string($record['message_short'] ?? null) ? $record['message_short'] : '';
            $origin = $this->classifyHandler($handlerClassFull);

            if ($origin === DataOrigin::APP) {
                $appSyncMs += $durationMs;
                $appSyncCount += 1;
            } else {
                $infraSyncMs += $durationMs;
                $infraSyncCount += 1;
            }

            $syncHandlers[] = [
                'handler_class'      => self::shortenClass($handlerClassFull),
                'handler_class_full' => $handlerClassFull,
                'message_short'      => $messageShort,
                'origin'             => $origin->value,
                'duration_ms'        => $durationMs,
            ];
        }

        usort(
            $syncHandlers,
            static fn (array $a, array $b): int => $b['duration_ms'] <=> $a['duration_ms'],
        );

        return [
            'messenger' => [
                'sync_handlers'       => $syncHandlers,
                'total_sync_ms'       => $appSyncMs + $infraSyncMs,
                'sync_count'          => $appSyncCount + $infraSyncCount,
                'app_sync_ms'         => $appSyncMs,
                'app_sync_count'      => $appSyncCount,
                'infra_sync_ms'       => $infraSyncMs,
                'infra_sync_count'    => $infraSyncCount,
                'profiler_sync_ms'    => 0.0,
                'profiler_sync_count' => 0,
            ],
        ];
    }

    private function classifyHandler(string $handlerClassFull): DataOrigin
    {
        if (str_starts_with($handlerClassFull, $this->appNamespacePrefix)) {
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
