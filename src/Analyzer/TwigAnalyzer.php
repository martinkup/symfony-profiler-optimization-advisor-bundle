<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Analyzer;

use MartinKup\OptimizationAdvisorBundle\Enum\DataOrigin;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Profiler\Profile;

final readonly class TwigAnalyzer
{
    /** @param array<int, string> $profilerTemplatePrefixes */
    public function __construct(
        #[Autowire('%optimization_advisor.profiler_template_prefixes%')]
        private array $profilerTemplatePrefixes = ['@WebProfiler/'],
    ) {
    }

    /**
     * Analyze a Twig profiler tree and aggregate per-template metrics.
     *
     * @return array{
     *     templates: list<array{
     *         template: string,
     *         origin: string,
     *         renders: int,
     *         total_ms: float,
     *         max_ms: float,
     *     }>,
     *     total_twig_ms: float,
     *     total_renders: int,
     *     unique_templates: int,
     *     app_renders: int,
     *     app_twig_ms: float,
     *     infra_renders: int,
     *     infra_twig_ms: float,
     *     profiler_renders: int,
     *     profiler_twig_ms: float,
     * }
     */
    public function analyze(Profile $profile): array
    {
        $groups = $this->collectTemplateMetrics($profile);
        $templates = array_values($groups);

        usort(
            $templates,
            static fn (array $a, array $b): int => $b['total_ms'] <=> $a['total_ms'],
        );

        $appRenders = 0;
        $infraRenders = 0;
        $profilerRenders = 0;

        foreach ($templates as $entry) {
            match ($entry['origin']) {
                DataOrigin::APP->value => $appRenders += $entry['renders'],
                DataOrigin::PROFILER->value => $profilerRenders += $entry['renders'],
                default => $infraRenders += $entry['renders'],
            };
        }

        $timingByOrigin = $this->computeTwigMsByOrigin($profile);
        $appTwigMs = $timingByOrigin[DataOrigin::APP->value];
        $infraTwigMs = $timingByOrigin[DataOrigin::INFRA->value];
        $profilerTwigMs = $timingByOrigin[DataOrigin::PROFILER->value];

        return [
            'templates'        => $templates,
            'total_twig_ms'    => $appTwigMs + $infraTwigMs,
            'total_renders'    => $appRenders + $infraRenders,
            'unique_templates' => count($templates),
            'app_renders'      => $appRenders,
            'app_twig_ms'      => $appTwigMs,
            'infra_renders'    => $infraRenders,
            'infra_twig_ms'    => max(0.0, $infraTwigMs),
            'profiler_renders' => $profilerRenders,
            'profiler_twig_ms' => max(0.0, $profilerTwigMs),
        ];
    }

    /** @return array{app: float, infra: float, profiler: float} */
    private function computeTwigMsByOrigin(Profile $profile): array
    {
        $result = [
            DataOrigin::APP->value      => 0.0,
            DataOrigin::INFRA->value    => 0.0,
            DataOrigin::PROFILER->value => 0.0,
        ];

        foreach ($profile->getProfiles() as $child) {
            if (!$child->isTemplate()) {
                continue;
            }

            $origin = $this->classifyTemplate($child->getTemplate());
            $result[$origin->value] += $child->getDuration() * 1000.0;
        }

        return $result;
    }

    /** @return array<string, array{template: string, origin: string, renders: int, total_ms: float, max_ms: float}> */
    private function collectTemplateMetrics(Profile $profile): array
    {
        $groups = [];

        if ($profile->isTemplate()) {
            $templateName = $profile->getTemplate();
            $durationMs = $profile->getDuration() * 1000.0;

            $groups[$templateName] = [
                'template' => $templateName,
                'origin'   => $this->classifyTemplate($templateName)->value,
                'renders'  => 1,
                'total_ms' => $durationMs,
                'max_ms'   => $durationMs,
            ];
        }

        foreach ($profile->getProfiles() as $child) {
            $childGroups = $this->collectTemplateMetrics($child);

            foreach ($childGroups as $name => $childGroup) {
                if (isset($groups[$name])) {
                    $groups[$name]['renders'] += $childGroup['renders'];
                    $groups[$name]['total_ms'] += $childGroup['total_ms'];
                    $groups[$name]['max_ms'] = max($groups[$name]['max_ms'], $childGroup['max_ms']);
                } else {
                    $groups[$name] = $childGroup;
                }
            }
        }

        return $groups;
    }

    private function classifyTemplate(string $templateName): DataOrigin
    {
        foreach ($this->profilerTemplatePrefixes as $prefix) {
            if (str_starts_with($templateName, $prefix)) {
                return DataOrigin::PROFILER;
            }
        }

        return DataOrigin::APP;
    }
}
