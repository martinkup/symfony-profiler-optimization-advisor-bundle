<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Analyzer;

use MartinKup\OptimizationAdvisorBundle\Analyzer\HttpClientAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for HttpClientAnalyzer HTTP client trace grouping and fingerprinting.
 *
 * Test Coverage:
 * - Grouping traces by endpoint fingerprint (method + host + path pattern)
 * - Replacing numeric path segments with {id} placeholder
 * - Computing status buckets (2xx, 3xx, 4xx, 5xx)
 * - Sorting call groups by total_ms descending
 * - Classifying all HTTP calls with APP origin
 * - Tracking APP-scoped and PROFILER-scoped HTTP call counters
 * - Returning empty results for empty client data
 *
 * @see HttpClientAnalyzer
 */
#[CoversClass(HttpClientAnalyzer::class)]
#[Group('unit')]
final class HttpClientAnalyzerTest extends TestCase
{
    private HttpClientAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new HttpClientAnalyzer();
    }

    public function testAnalyzeGroupsByEndpointFingerprint(): void
    {
        $clientsData = [
            'api_client' => [
                $this->buildTrace('GET', 'https://api.example.com/users/1', 200, 0.100),
                $this->buildTrace('GET', 'https://api.example.com/users/42', 200, 0.150),
                $this->buildTrace('POST', 'https://api.example.com/users', 201, 0.200),
            ],
        ];

        $result = $this->analyzer->analyze($clientsData);

        self::assertCount(2, $result['calls']);
        self::assertSame(3, $result['total_http_calls']);
        self::assertSame('app', $result['calls'][0]['origin']);
        self::assertSame('app', $result['calls'][1]['origin']);
        self::assertSame(3, $result['app_http_calls']);
        self::assertSame(0, $result['profiler_http_calls']);
        self::assertSame(0.0, $result['profiler_http_ms']);
    }

    public function testAnalyzeReplacesNumericPathSegments(): void
    {
        $clientsData = [
            'client' => [
                $this->buildTrace('GET', 'https://api.example.com/users/123/posts/456', 200, 0.050),
                $this->buildTrace('GET', 'https://api.example.com/users/999/posts/1', 200, 0.060),
            ],
        ];

        $result = $this->analyzer->analyze($clientsData);

        self::assertCount(1, $result['calls']);

        $group = $result['calls'][0];

        self::assertSame('GET api.example.com/users/{id}/posts/{id}', $group['endpoint_fingerprint']);
        self::assertSame('GET', $group['method']);
        self::assertSame('api.example.com', $group['host']);
        self::assertSame('/users/{id}/posts/{id}', $group['path_pattern']);
        self::assertSame('app', $group['origin']);
        self::assertSame(2, $group['calls']);
    }

    public function testAnalyzeComputesStatusBuckets(): void
    {
        $clientsData = [
            'client' => [
                $this->buildTrace('GET', 'https://api.example.com/data', 200, 0.010),
                $this->buildTrace('GET', 'https://api.example.com/data', 200, 0.015),
                $this->buildTrace('GET', 'https://api.example.com/data', 301, 0.005),
                $this->buildTrace('GET', 'https://api.example.com/data', 404, 0.003),
                $this->buildTrace('GET', 'https://api.example.com/data', 500, 0.020),
            ],
        ];

        $result = $this->analyzer->analyze($clientsData);

        self::assertCount(1, $result['calls']);

        $buckets = $result['calls'][0]['status_buckets'];

        self::assertSame(2, $buckets['2xx']);
        self::assertSame(1, $buckets['3xx']);
        self::assertSame(1, $buckets['4xx']);
        self::assertSame(1, $buckets['5xx']);
    }

    public function testAnalyzeSortsByTotalMsDescending(): void
    {
        $clientsData = [
            'client' => [
                $this->buildTrace('GET', 'https://fast.example.com/ping', 200, 0.001),
                $this->buildTrace('POST', 'https://slow.example.com/process', 200, 0.500),
                $this->buildTrace('PUT', 'https://medium.example.com/update', 200, 0.100),
            ],
        ];

        $result = $this->analyzer->analyze($clientsData);

        self::assertCount(3, $result['calls']);
        self::assertSame('slow.example.com', $result['calls'][0]['host']);
        self::assertSame('medium.example.com', $result['calls'][1]['host']);
        self::assertSame('fast.example.com', $result['calls'][2]['host']);
    }

    public function testAnalyzeEmptyClients(): void
    {
        $result = $this->analyzer->analyze([]);

        self::assertSame([], $result['calls']);
        self::assertSame(0, $result['total_http_calls']);
        self::assertSame(0.0, $result['total_http_ms']);
        self::assertSame(0, $result['app_http_calls']);
        self::assertSame(0.0, $result['app_http_ms']);
        self::assertSame(0, $result['profiler_http_calls']);
        self::assertSame(0.0, $result['profiler_http_ms']);
    }

    /** @return array{method: string, url: string, http_code: int, duration: float} */
    private function buildTrace(string $method, string $url, int $httpCode, float $durationSeconds): array
    {
        return [
            'method'    => $method,
            'url'       => $url,
            'http_code' => $httpCode,
            'duration'  => $durationSeconds,
        ];
    }
}
