<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\AiMate\Capability;

use MartinKup\OptimizationAdvisorBundle\AiMate\OpportunityFilter;
use MartinKup\OptimizationAdvisorBundle\AiMate\SecurityRedactor;
use MartinKup\OptimizationAdvisorBundle\DataCollector\OptimizationAdvisorDataCollector;
use Mcp\Capability\Attribute\McpTool;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfilerDataProvider;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

use function sprintf;

/**
 * MCP tool for accessing optimization advisor data from the Symfony Profiler.
 */
final class OptimizationAdvisorTool
{
    public function __construct(
        private readonly ProfilerDataProvider $dataProvider,
        private readonly SecurityRedactor $securityRedactor,
    ) {
    }

    /** @return array<string, mixed> */
    #[McpTool(
        'optimization-advisor-opportunities',
        'List optimization opportunities from a Symfony Profiler request. '
        . 'Returns scored, actionable recommendations. '
        . 'Filter by category (db, cache, twig, events, http, messenger) '
        . 'or type (quick_wins, high_impact, risky). '
        . 'If no token provided, uses the latest profiler request.',
    )]
    public function getOpportunities(
        ?string $token = null,
        ?string $category = null,
        ?string $type = null,
        int $limit = 20,
    ): array {
        $collector = $this->getCollector($token);

        if (!$collector instanceof OptimizationAdvisorDataCollector) {
            return $collector;
        }

        $opportunities = $this->resolveOpportunities($collector, $type);

        if ($category !== null) {
            $opportunities = array_values(
                array_filter(
                    $opportunities,
                    static fn (array $o): bool => ($o['category'] ?? '') === $category,
                ),
            );
        }

        $opportunities = array_slice($opportunities, 0, $limit);
        $opportunities = OpportunityFilter::forMcpOutput($opportunities);

        return $this->securityRedactor->redact([
            'summary' => $collector->getSummary(),
            'opportunities' => $opportunities,
        ]);
    }

    /** @return OptimizationAdvisorDataCollector|array<string, mixed> */
    private function getCollector(?string $token): OptimizationAdvisorDataCollector|array
    {
        if ($token === null) {
            $latest = $this->dataProvider->getLatestProfile();

            if ($latest === null) {
                return ['error' => 'No profiler profiles found'];
            }

            $token = $latest->getToken();
        }

        $profileData = $this->dataProvider->findProfile($token);

        if ($profileData === null) {
            return ['error' => sprintf('Profile not found for token: %s', $token)];
        }

        $collectors = $profileData->getProfile()->getCollectors();

        foreach ($collectors as $collector) {
            if (
                $collector->getName() === 'optimization_advisor'
                && $collector instanceof OptimizationAdvisorDataCollector
            ) {
                return $collector;
            }
        }

        return [
            'error' => sprintf(
                'optimization_advisor collector not found in profile %s. '
                . 'Available collectors: %s',
                $token,
                implode(', ', array_map(
                    static fn (DataCollectorInterface $c): string => $c->getName(),
                    $collectors,
                )),
            ),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function resolveOpportunities(OptimizationAdvisorDataCollector $collector, ?string $type): array
    {
        return match ($type) {
            'quick_wins' => $collector->getQuickWins(),
            'high_impact' => $collector->getHighImpactOpportunities(),
            'risky' => $collector->getRiskyChanges(),
            default => $collector->getOpportunities(),
        };
    }
}
