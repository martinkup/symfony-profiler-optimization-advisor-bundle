<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\AiMate\Formatter;

use MartinKup\OptimizationAdvisorBundle\AiMate\OpportunityFilter;
use MartinKup\OptimizationAdvisorBundle\AiMate\SecurityRedactor;
use MartinKup\OptimizationAdvisorBundle\DataCollector\OptimizationAdvisorDataCollector;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorFormatterInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\VarDumper\Cloner\Data;
use Throwable;

/**
 * Formats optimization advisor collector data for AI consumption.
 *
 * @implements CollectorFormatterInterface<OptimizationAdvisorDataCollector>
 */
final class OptimizationAdvisorCollectorFormatter implements CollectorFormatterInterface
{
    public function __construct(private readonly SecurityRedactor $securityRedactor)
    {
    }

    public function getName(): string
    {
        return 'optimization_advisor';
    }

    /** @return array<string, mixed> */
    public function format(DataCollectorInterface $collector): array
    {
        $data = [
            'origin' => $collector->getOrigin(),
            'opportunities' => OpportunityFilter::forMcpOutput($collector->getOpportunities()),
            'summary' => $collector->getSummary(),
            'signals' => $this->sanitizeData($collector->getSignals()),
        ];

        return $this->securityRedactor->redact($data);
    }

    /** @return array<string, mixed> */
    public function getSummary(DataCollectorInterface $collector): array
    {
        $summary = $collector->getSummary();

        return [
            'opportunity_count' => $summary['opportunity_count'] ?? 0,
            'quick_win_count' => $summary['quick_win_count'] ?? 0,
            'high_impact_count' => $summary['high_impact_count'] ?? 0,
            'optimization_score' => $summary['optimization_score'] ?? 100,
        ];
    }

    private function sanitizeData(mixed $value): mixed
    {
        if ($value instanceof Data) {
            try {
                return $value->getValue(true);
            } catch (Throwable $e) {
                return '[unserializable: ' . $e->getMessage() . ']';
            }
        }

        if (is_array($value)) {
            /** @var array<array-key, mixed> $result */
            $result = [];

            foreach ($value as $key => $item) {
                $result[$key] = $this->sanitizeData($item);
            }

            return $result;
        }

        return $value;
    }
}
