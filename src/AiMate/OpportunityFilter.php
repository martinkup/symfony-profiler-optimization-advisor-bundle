<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\AiMate;

final readonly class OpportunityFilter
{
    private const array EXCLUDED_FIELDS = ['recommended_actions', 'ai_prompt'];

    /**
     * @param array<int, array<string, mixed>> $opportunities
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forMcpOutput(array $opportunities): array
    {
        return array_map(static function (array $opportunity): array {
            foreach (self::EXCLUDED_FIELDS as $field) {
                unset($opportunity[$field]);
            }

            return $opportunity;
        }, $opportunities);
    }
}
