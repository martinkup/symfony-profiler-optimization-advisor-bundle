<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Analyzer;

use MartinKup\OptimizationAdvisorBundle\Analyzer\TwigAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Twig\Profiler\Profile;

/**
 * Unit tests for TwigAnalyzer Twig profiler tree analysis and origin classification.
 *
 * Test Coverage:
 * - Counting render occurrences per template
 * - Computing template durations from profile tree
 * - Handling nested profile hierarchies (root -> template -> block -> template)
 * - Sorting templates by total_ms descending
 * - Skipping root profile (not a template)
 * - Returning empty results for empty profile tree
 * - Classifying templates as APP, INFRA, or PROFILER (@WebProfiler/ prefix)
 * - Split aggregates for app/infra/profiler renders and timing
 * - Profiler-origin templates excluded from total_renders and total_twig_ms
 *
 * @see TwigAnalyzer
 */
#[CoversClass(TwigAnalyzer::class)]
#[Group('unit')]
final class TwigAnalyzerTest extends TestCase
{
    private TwigAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new TwigAnalyzer();
    }

    public function testAnalyzeCountsRenders(): void
    {
        $root = $this->buildRootProfile();

        $template1a = $this->buildTemplateProfile('base.html.twig', 0.005);
        $template1b = $this->buildTemplateProfile('base.html.twig', 0.003);
        $template2 = $this->buildTemplateProfile('page.html.twig', 0.010);

        $root->addProfile($template1a);
        $root->addProfile($template1b);
        $root->addProfile($template2);

        $result = $this->analyzer->analyze($root);

        self::assertSame(3, $result['total_renders']);
        self::assertCount(2, $result['templates']);
        self::assertSame(2, $result['unique_templates']);
        self::assertSame(3, $result['app_renders']);
        self::assertSame(0, $result['infra_renders']);
        self::assertSame(0, $result['profiler_renders']);

        $baseTemplate = $this->findTemplateByName($result['templates'], 'base.html.twig');
        self::assertNotNull($baseTemplate);
        self::assertSame(2, $baseTemplate['renders']);
        self::assertSame('app', $baseTemplate['origin']);
    }

    public function testAnalyzeComputesDurations(): void
    {
        $root = $this->buildRootProfile();

        $template1 = $this->buildTemplateProfile('layout.html.twig', 0.010);
        $template2 = $this->buildTemplateProfile('layout.html.twig', 0.030);

        $root->addProfile($template1);
        $root->addProfile($template2);

        $result = $this->analyzer->analyze($root);

        self::assertCount(1, $result['templates']);

        $group = $result['templates'][0];

        self::assertEqualsWithDelta(40.0, $group['total_ms'], 0.1);
        self::assertEqualsWithDelta(30.0, $group['max_ms'], 0.1);
        self::assertEqualsWithDelta(40.0, $result['total_twig_ms'], 0.1);
    }

    public function testAnalyzeHandlesNestedProfiles(): void
    {
        $root = $this->buildRootProfile();

        $parentTemplate = $this->buildTemplateProfile('parent.html.twig', 0.020);
        $childTemplate = $this->buildTemplateProfile('child.html.twig', 0.005);
        $parentTemplate->addProfile($childTemplate);

        $root->addProfile($parentTemplate);

        $result = $this->analyzer->analyze($root);

        self::assertCount(2, $result['templates']);
        self::assertSame(2, $result['total_renders']);

        $parent = $this->findTemplateByName($result['templates'], 'parent.html.twig');
        $child = $this->findTemplateByName($result['templates'], 'child.html.twig');

        self::assertNotNull($parent);
        self::assertNotNull($child);
        self::assertEqualsWithDelta(20.0, $parent['total_ms'], 0.1);
        self::assertEqualsWithDelta(5.0, $child['total_ms'], 0.1);

        // total_twig_ms must NOT double-count nested templates.
        // parent (20ms inclusive) already contains child (5ms), so total = 20ms, not 25ms.
        self::assertEqualsWithDelta(20.0, $result['total_twig_ms'], 0.1);
    }

    public function testAnalyzeSortsByTotalMsDescending(): void
    {
        $root = $this->buildRootProfile();

        $slow = $this->buildTemplateProfile('slow.html.twig', 0.050);
        $fast = $this->buildTemplateProfile('fast.html.twig', 0.001);
        $medium = $this->buildTemplateProfile('medium.html.twig', 0.020);

        $root->addProfile($fast);
        $root->addProfile($slow);
        $root->addProfile($medium);

        $result = $this->analyzer->analyze($root);

        self::assertCount(3, $result['templates']);
        self::assertSame('slow.html.twig', $result['templates'][0]['template']);
        self::assertSame('medium.html.twig', $result['templates'][1]['template']);
        self::assertSame('fast.html.twig', $result['templates'][2]['template']);
    }

    public function testAnalyzeRootProfile(): void
    {
        $root = $this->buildRootProfile();

        $result = $this->analyzer->analyze($root);

        self::assertSame([], $result['templates']);
        self::assertSame(0, $result['total_renders']);
        self::assertSame(0.0, $result['total_twig_ms']);
        self::assertSame(0, $result['unique_templates']);
        self::assertSame(0, $result['app_renders']);
        self::assertEqualsWithDelta(0.0, $result['app_twig_ms'], 0.1);
        self::assertSame(0, $result['infra_renders']);
        self::assertEqualsWithDelta(0.0, $result['infra_twig_ms'], 0.1);
        self::assertSame(0, $result['profiler_renders']);
        self::assertEqualsWithDelta(0.0, $result['profiler_twig_ms'], 0.1);
    }

    public function testAnalyzeEmptyProfile(): void
    {
        $root = $this->buildRootProfile();

        $block = new Profile('content', Profile::BLOCK, 'content');
        $block->enter();
        $block->leave();
        $root->addProfile($block);

        $result = $this->analyzer->analyze($root);

        self::assertSame([], $result['templates']);
        self::assertSame(0, $result['total_renders']);
        self::assertSame(0.0, $result['total_twig_ms']);
    }

    public function testClassifiesWebProfilerTemplateAsProfiler(): void
    {
        $root = $this->buildRootProfile();

        $appTemplate = $this->buildTemplateProfile('backoffice/page.html.twig', 0.010);
        $profilerTemplate = $this->buildTemplateProfile('@WebProfiler/Profiler/toolbar.html.twig', 0.005);

        $root->addProfile($appTemplate);
        $root->addProfile($profilerTemplate);

        $result = $this->analyzer->analyze($root);

        self::assertCount(2, $result['templates']);

        $app = $this->findTemplateByName($result['templates'], 'backoffice/page.html.twig');
        $profiler = $this->findTemplateByName($result['templates'], '@WebProfiler/Profiler/toolbar.html.twig');

        self::assertNotNull($app);
        self::assertSame('app', $app['origin']);
        self::assertNotNull($profiler);
        self::assertSame('profiler', $profiler['origin']);

        self::assertSame(1, $result['app_renders']);
        self::assertEqualsWithDelta(10.0, $result['app_twig_ms'], 0.1);
        self::assertSame(0, $result['infra_renders']);
        self::assertEqualsWithDelta(0.0, $result['infra_twig_ms'], 0.1);
        self::assertSame(1, $result['profiler_renders']);
        self::assertEqualsWithDelta(5.0, $result['profiler_twig_ms'], 0.1);

        // total_renders and total_twig_ms exclude profiler
        self::assertSame(1, $result['total_renders']);
        self::assertEqualsWithDelta(10.0, $result['total_twig_ms'], 0.1);
    }

    private function buildRootProfile(): Profile
    {
        $root = new Profile('main', Profile::ROOT);
        $root->enter();

        return $root;
    }

    private function buildTemplateProfile(string $name, float $durationSeconds): Profile
    {
        $profile = new Profile($name, Profile::TEMPLATE);
        $profile->enter();
        $profile->leave();

        $this->setProfileDuration($profile, $durationSeconds);

        return $profile;
    }

    private function setProfileDuration(Profile $profile, float $durationSeconds): void
    {
        // Profile __serialize() returns: [0=>template, 1=>name, 2=>type, 3=>starts, 4=>ends, 5=>profiles]
        // starts/ends format: ['wt' => float, 'mu' => int, 'pmu' => int]
        $data = $profile->__serialize();
        $data[3] = ['wt' => 0.0, 'mu' => 0, 'pmu' => 0];
        $data[4] = ['wt' => $durationSeconds, 'mu' => 0, 'pmu' => 0];
        $profile->__unserialize($data);
    }

    /**
     * @param list<array{template: string, origin: string, renders: int, total_ms: float, max_ms: float}> $templates
     *
     * @return ?array{template: string, origin: string, renders: int, total_ms: float, max_ms: float}
     */
    private function findTemplateByName(array $templates, string $name): ?array
    {
        foreach ($templates as $template) {
            if ($template['template'] === $name) {
                return $template;
            }
        }

        return null;
    }
}
