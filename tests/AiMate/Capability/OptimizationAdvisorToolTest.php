<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\AiMate\Capability;

use MartinKup\OptimizationAdvisorBundle\AiMate\Capability\OptimizationAdvisorTool;
use MartinKup\OptimizationAdvisorBundle\AiMate\SecurityRedactor;
use MartinKup\OptimizationAdvisorBundle\DataCollector\OptimizationAdvisorDataCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorRegistry;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfilerDataProvider;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\Profiler\FileProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profile;

/** @see OptimizationAdvisorTool */
#[CoversClass(OptimizationAdvisorTool::class)]
#[Group('unit')]
final class OptimizationAdvisorToolTest extends TestCase
{
    private string $profilerDir;

    protected function setUp(): void
    {
        $this->profilerDir = sys_get_temp_dir() . '/oa_tool_test_' . uniqid();
        mkdir($this->profilerDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->profilerDir);
    }

    public function testGetOpportunitiesWithToken(): void
    {
        $collector = $this->createCollectorWithData([
            'opportunities' => [
                [
                    'code' => 'DB_N_PLUS_ONE',
                    'category' => 'db',
                    'impact' => 8,
                    'recommended_actions' => ['Fix N+1'],
                    'ai_prompt' => 'Some prompt',
                ],
                [
                    'code' => 'CACHE_LOW_HIT',
                    'category' => 'cache',
                    'impact' => 5,
                    'recommended_actions' => ['Tune TTL'],
                    'ai_prompt' => 'Another prompt',
                ],
            ],
            'summary' => ['opportunity_count' => 2, 'optimization_score' => 90],
        ]);

        $this->writeProfile('abc123', $collector);
        $tool = $this->createTool();

        $result = $tool->getOpportunities(token: 'abc123');

        self::assertArrayHasKey('summary', $result);
        self::assertArrayHasKey('opportunities', $result);

        /** @var array<int, array<string, mixed>> $opportunities */
        $opportunities = $result['opportunities'];
        self::assertCount(2, $opportunities);
        self::assertArrayNotHasKey('recommended_actions', $opportunities[0]);
        self::assertArrayNotHasKey('recommended_actions', $opportunities[1]);
        self::assertArrayNotHasKey('ai_prompt', $opportunities[0]);
        self::assertArrayNotHasKey('ai_prompt', $opportunities[1]);

        /** @var array<string, mixed> $summary */
        $summary = $result['summary'];
        self::assertSame(90, $summary['optimization_score']);
    }

    public function testGetOpportunitiesWithNullTokenUsesLatest(): void
    {
        $collector = $this->createCollectorWithData([
            'opportunities' => [['code' => 'TWIG_DEEP', 'category' => 'twig', 'impact' => 3]],
            'summary' => ['opportunity_count' => 1],
        ]);

        $this->writeProfile('latest-token', $collector);
        $tool = $this->createTool();

        $result = $tool->getOpportunities();

        /** @var array<int, array<string, mixed>> $opportunities */
        $opportunities = $result['opportunities'];
        self::assertCount(1, $opportunities);
        self::assertSame('TWIG_DEEP', $opportunities[0]['code']);
    }

    public function testGetOpportunitiesFiltersByCategory(): void
    {
        $collector = $this->createCollectorWithData([
            'opportunities' => [
                ['code' => 'DB_N_PLUS_ONE', 'category' => 'db', 'impact' => 8],
                ['code' => 'CACHE_LOW_HIT', 'category' => 'cache', 'impact' => 5],
                ['code' => 'DB_SLOW_QUERY', 'category' => 'db', 'impact' => 6],
            ],
            'summary' => ['opportunity_count' => 3],
        ]);

        $this->writeProfile('token1', $collector);
        $tool = $this->createTool();

        $result = $tool->getOpportunities(token: 'token1', category: 'db');

        /** @var array<int, array<string, mixed>> $opportunities */
        $opportunities = $result['opportunities'];
        self::assertCount(2, $opportunities);
        self::assertSame('db', $opportunities[0]['category']);
        self::assertSame('db', $opportunities[1]['category']);
    }

    public function testGetOpportunitiesFiltersByTypeQuickWins(): void
    {
        $collector = $this->createCollectorWithData([
            'opportunities' => [
                ['code' => 'QUICK_1', 'is_quick_win' => true, 'roi' => 5.0],
                ['code' => 'NOT_QUICK', 'is_quick_win' => false, 'roi' => 1.0],
            ],
            'summary' => ['opportunity_count' => 2],
        ]);

        $this->writeProfile('token2', $collector);
        $tool = $this->createTool();

        $result = $tool->getOpportunities(token: 'token2', type: 'quick_wins');

        /** @var array<int, array<string, mixed>> $opportunities */
        $opportunities = $result['opportunities'];
        self::assertCount(1, $opportunities);
        self::assertSame('QUICK_1', $opportunities[0]['code']);
    }

    public function testGetOpportunitiesFiltersByTypeHighImpact(): void
    {
        $collector = $this->createCollectorWithData([
            'opportunities' => [
                ['code' => 'HIGH_1', 'is_high_impact' => true],
                ['code' => 'LOW_1', 'is_high_impact' => false],
            ],
            'summary' => ['opportunity_count' => 2],
        ]);

        $this->writeProfile('token3', $collector);
        $tool = $this->createTool();

        $result = $tool->getOpportunities(token: 'token3', type: 'high_impact');

        /** @var array<int, array<string, mixed>> $opportunities */
        $opportunities = $result['opportunities'];
        self::assertCount(1, $opportunities);
        self::assertSame('HIGH_1', $opportunities[0]['code']);
    }

    public function testGetOpportunitiesFiltersByTypeRisky(): void
    {
        $collector = $this->createCollectorWithData([
            'opportunities' => [
                ['code' => 'RISKY_1', 'risk' => 'high'],
                ['code' => 'SAFE_1', 'risk' => 'low'],
            ],
            'summary' => ['opportunity_count' => 2],
        ]);

        $this->writeProfile('token4', $collector);
        $tool = $this->createTool();

        $result = $tool->getOpportunities(token: 'token4', type: 'risky');

        /** @var array<int, array<string, mixed>> $opportunities */
        $opportunities = $result['opportunities'];
        self::assertCount(1, $opportunities);
        self::assertSame('RISKY_1', $opportunities[0]['code']);
    }

    public function testGetOpportunitiesRespectsLimit(): void
    {
        $items = [];

        for ($i = 0; $i < 10; $i++) {
            $items[] = ['code' => 'OPP_' . $i, 'category' => 'db', 'impact' => $i];
        }

        $collector = $this->createCollectorWithData([
            'opportunities' => $items,
            'summary' => ['opportunity_count' => 10],
        ]);

        $this->writeProfile('token5', $collector);
        $tool = $this->createTool();

        $result = $tool->getOpportunities(token: 'token5', limit: 3);

        /** @var array<int, array<string, mixed>> $opportunities */
        $opportunities = $result['opportunities'];
        self::assertCount(3, $opportunities);
    }

    public function testGetOpportunitiesReturnsErrorWhenNoProfiles(): void
    {
        $tool = $this->createTool();

        $result = $tool->getOpportunities();

        self::assertArrayHasKey('error', $result);
        self::assertIsString($result['error']);
        self::assertStringContainsString('No profiler profiles found', $result['error']);
    }

    public function testGetOpportunitiesReturnsErrorWhenProfileNotFound(): void
    {
        $tool = $this->createTool();

        $result = $tool->getOpportunities(token: 'missing');

        self::assertArrayHasKey('error', $result);
        self::assertIsString($result['error']);
        self::assertStringContainsString('Profile not found', $result['error']);
    }

    public function testGetOpportunitiesReturnsErrorWhenCollectorNotFound(): void
    {
        $profile = new Profile('no-collector');
        $profile->setTime(time());
        $profile->setMethod('GET');
        $profile->setUrl('/');
        $profile->setStatusCode(200);

        $storage = new FileProfilerStorage('file:' . $this->profilerDir);
        $storage->write($profile);

        $tool = $this->createTool();

        $result = $tool->getOpportunities(token: 'no-collector');

        self::assertArrayHasKey('error', $result);
        self::assertIsString($result['error']);
        self::assertStringContainsString('optimization_advisor collector not found', $result['error']);
    }

    public function testGetOpportunitiesAppliesSecurityRedactor(): void
    {
        $collector = $this->createCollectorWithData([
            'opportunities' => [['code' => 'DB_SLOW', 'category' => 'db', 'impact' => 5]],
            'summary' => ['opportunity_count' => 1],
        ]);

        $this->writeProfile('redact-token', $collector);

        $redactor = new SecurityRedactor(
            true,
            ['email'],
            ['[^@\\s]+@[^@\\s]+\\.[^@\\s]+'],
            ['token'],
        );

        $tool = new OptimizationAdvisorTool($this->createDataProvider(), $redactor);

        $result = $tool->getOpportunities(token: 'redact-token');

        self::assertArrayHasKey('summary', $result);
        self::assertArrayHasKey('opportunities', $result);
    }

    /** @param array<string, mixed> $data */
    private function createCollectorWithData(array $data): OptimizationAdvisorDataCollector
    {
        $reflection = new ReflectionClass(OptimizationAdvisorDataCollector::class);
        $collector = $reflection->newInstanceWithoutConstructor();

        $property = (new ReflectionClass(DataCollector::class))->getProperty('data');
        $property->setValue($collector, $data);

        return $collector;
    }

    private function createTool(): OptimizationAdvisorTool
    {
        return new OptimizationAdvisorTool($this->createDataProvider(), new SecurityRedactor());
    }

    private function createDataProvider(): ProfilerDataProvider
    {
        return new ProfilerDataProvider($this->profilerDir, new CollectorRegistry());
    }

    private function writeProfile(string $token, OptimizationAdvisorDataCollector $collector): void
    {
        $profile = new Profile($token);
        $profile->setTime(time());
        $profile->setMethod('GET');
        $profile->setUrl('/');
        $profile->setStatusCode(200);
        $profile->addCollector($collector);

        $storage = new FileProfilerStorage('file:' . $this->profilerDir);
        $storage->write($profile);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $scanned = scandir($dir);
        /** @var array<int, string> $files */
        $files = is_array($scanned) ? $scanned : [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
