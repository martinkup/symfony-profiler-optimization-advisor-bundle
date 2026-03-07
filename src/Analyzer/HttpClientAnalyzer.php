<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Analyzer;

use MartinKup\OptimizationAdvisorBundle\Enum\DataOrigin;

final readonly class HttpClientAnalyzer
{
    /**
     * Analyze HTTP client traces and group by endpoint fingerprint.
     *
     * @param array<string, array<int, array{
     *     method: string,
     *     url: string,
     *     http_code: int,
     *     duration: float,
     * }>> $clientsData
     *
     * @return array{
     *     calls: list<array{
     *         endpoint_fingerprint: string,
     *         method: string,
     *         host: string,
     *         path_pattern: string,
     *         origin: string,
     *         calls: int,
     *         total_ms: float,
     *         max_ms: float,
     *         status_buckets: array<string, int>,
     *     }>,
     *     total_http_calls: int,
     *     total_http_ms: float,
     *     app_http_calls: int,
     *     app_http_ms: float,
     *     profiler_http_calls: int,
     *     profiler_http_ms: float,
     * }
     */
    public function analyze(array $clientsData): array
    {
        /** @var array<string, array{
         *     endpoint_fingerprint: string,
         *     method: string,
         *     host: string,
         *     path_pattern: string,
         *     origin: string,
         *     calls: int,
         *     total_ms: float,
         *     max_ms: float,
         *     status_buckets: array<string, int>,
         * }> $groups
         */
        $groups = [];

        foreach ($clientsData as $traces) {
            foreach ($traces as $trace) {
                $method = $trace['method'];
                $url = $trace['url'];
                $httpCode = $trace['http_code'];
                $durationMs = $trace['duration'] * 1000.0;

                $parsed = parse_url($url);
                $host = is_array($parsed) && isset($parsed['host']) ? $parsed['host'] : 'unknown';
                $path = is_array($parsed) && isset($parsed['path']) ? $parsed['path'] : '/';
                $pathPattern = $this->fingerprintPath($path);

                $fingerprint = $method . ' ' . $host . $pathPattern;

                if (isset($groups[$fingerprint])) {
                    $groups[$fingerprint]['calls'] += 1;
                    $groups[$fingerprint]['total_ms'] += $durationMs;
                    $groups[$fingerprint]['max_ms'] = max($groups[$fingerprint]['max_ms'], $durationMs);
                } else {
                    $groups[$fingerprint] = [
                        'endpoint_fingerprint' => $fingerprint,
                        'method'               => $method,
                        'host'                 => $host,
                        'path_pattern'         => $pathPattern,
                        'origin'               => DataOrigin::APP->value,
                        'calls'                => 1,
                        'total_ms'             => $durationMs,
                        'max_ms'               => $durationMs,
                        'status_buckets'       => ['2xx' => 0, '3xx' => 0, '4xx' => 0, '5xx' => 0],
                    ];
                }

                $bucket = $this->resolveStatusBucket($httpCode);

                if ($bucket === null) {
                    continue;
                }

                $groups[$fingerprint]['status_buckets'][$bucket] += 1;
            }
        }

        $calls = array_values($groups);

        usort(
            $calls,
            static fn (array $a, array $b): int => $b['total_ms'] <=> $a['total_ms'],
        );

        $totalHttpCalls = 0;
        $totalHttpMs = 0.0;

        foreach ($calls as $group) {
            $totalHttpCalls += $group['calls'];
            $totalHttpMs += $group['total_ms'];
        }

        return [
            'calls'               => $calls,
            'total_http_calls'    => $totalHttpCalls,
            'total_http_ms'       => $totalHttpMs,
            'app_http_calls'      => $totalHttpCalls,
            'app_http_ms'         => $totalHttpMs,
            'profiler_http_calls' => 0,
            'profiler_http_ms'    => 0.0,
        ];
    }

    /**
     * Replace numeric path segments with {id} placeholder.
     *
     * Example: /users/123/posts/456 -> /users/{id}/posts/{id}
     */
    private function fingerprintPath(string $path): string
    {
        $segments = explode('/', $path);
        $result = [];

        foreach ($segments as $segment) {
            if ($segment !== '' && ctype_digit($segment)) {
                $result[] = '{id}';
            } else {
                $result[] = $segment;
            }
        }

        return implode('/', $result);
    }

    private function resolveStatusBucket(int $httpCode): ?string
    {
        return match (true) {
            $httpCode >= 200 && $httpCode < 300 => '2xx',
            $httpCode >= 300 && $httpCode < 400 => '3xx',
            $httpCode >= 400 && $httpCode < 500 => '4xx',
            $httpCode >= 500 && $httpCode < 600 => '5xx',
            default => null,
        };
    }
}
