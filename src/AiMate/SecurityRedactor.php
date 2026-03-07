<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\AiMate;

/**
 * Redacts PII and sensitive data from profiler signals before MCP/AI Mate output.
 *
 * Pattern-based redaction: replaces sensitive query params in URIs,
 * raw SQL with normalized patterns, and matching parameter values.
 */
final readonly class SecurityRedactor
{
    private const string REDACTED = '***REDACTED***';

    /**
     * @param list<string> $sensitiveParamPatterns key substring match (case-insensitive)
     * @param list<string> $sensitiveValuePatterns regex match on string values
     * @param list<string> $sensitiveQueryParams   exact query param name match (case-insensitive)
     */
    public function __construct(
        private bool $enabled = true,
        private array $sensitiveParamPatterns = [],
        private array $sensitiveValuePatterns = [],
        private array $sensitiveQueryParams = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function redact(array $data): array
    {
        if (!$this->enabled) {
            return $data;
        }

        $data = $this->redactOrigin($data);

        return $this->redactSignals($data);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function redactOrigin(array $data): array
    {
        if (!isset($data['origin']) || !is_array($data['origin'])) {
            return $data;
        }

        /** @var array<string, mixed> $origin */
        $origin = $data['origin'];

        if (!isset($origin['uri']) || !is_string($origin['uri'])) {
            return $data;
        }

        $parts = explode('?', $origin['uri'], 2);
        $path = $this->redactPathSegments($parts[0]);

        if (isset($parts[1])) {
            parse_str($parts[1], $queryParams);
            $queryParams = $this->redactQueryParams($queryParams);
            $origin['uri'] = $queryParams !== []
                ? $path . '?' . http_build_query($queryParams)
                : $path;
        } else {
            $origin['uri'] = $path;
        }

        $data['origin'] = $origin;

        return $data;
    }

    /**
     * @param array<array-key, mixed> $queryParams
     *
     * @return array<array-key, mixed>
     */
    private function redactQueryParams(array $queryParams): array
    {
        $result = [];

        foreach ($queryParams as $name => $value) {
            if ($this->isSensitiveQueryParam((string) $name)) {
                $result[$name] = self::REDACTED;
            } elseif (is_array($value)) {
                $result[$name] = $this->redactParams($value);
            } elseif ($this->shouldRedactParam($name, $value)) {
                $result[$name] = self::REDACTED;
            } else {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    private function redactPathSegments(string $path): string
    {
        $segments = explode('/', $path);

        foreach ($segments as $index => $segment) {
            if ($segment === '' || !$this->matchesSensitiveValue($segment)) {
                continue;
            }

            $segments[$index] = self::REDACTED;
        }

        return implode('/', $segments);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function redactSignals(array $data): array
    {
        if (!isset($data['signals']) || !is_array($data['signals'])) {
            return $data;
        }

        /** @var array<string, mixed> $signals */
        $signals = $data['signals'];

        if (!isset($signals['db']) || !is_array($signals['db'])) {
            return $data;
        }

        /** @var array<string, mixed> $db */
        $db = $signals['db'];

        if (!isset($db['query_groups']) || !is_array($db['query_groups'])) {
            return $data;
        }

        /** @var array<int, array<string, mixed>> $queryGroups */
        $queryGroups = $db['query_groups'];

        foreach ($queryGroups as $index => $group) {
            if (isset($group['pattern'])) {
                $queryGroups[$index]['example_sql'] = $group['pattern'];
            }

            if (isset($group['example_params']) && is_array($group['example_params'])) {
                $queryGroups[$index]['example_params'] = $this->redactParams($group['example_params']);
            }

            $queryGroups[$index]['runnable'] = false;
        }

        $db['query_groups'] = $queryGroups;
        $signals['db'] = $db;
        $data['signals'] = $signals;

        return $data;
    }

    /**
     * @param array<array-key, mixed> $params
     *
     * @return array<array-key, mixed>
     */
    private function redactParams(array $params): array
    {
        $result = [];

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->redactParams($value);
            } elseif ($this->shouldRedactParam($key, $value)) {
                $result[$key] = self::REDACTED;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function shouldRedactParam(int|string $key, mixed $value): bool
    {
        if (is_string($key)) {
            $lowerKey = strtolower($key);

            foreach ($this->sensitiveParamPatterns as $pattern) {
                if (str_contains($lowerKey, strtolower($pattern))) {
                    return true;
                }
            }
        }

        if (is_string($value)) {
            return $this->matchesSensitiveValue($value);
        }

        return false;
    }

    private function matchesSensitiveValue(string $value): bool
    {
        foreach ($this->sensitiveValuePatterns as $pattern) {
            $result = @preg_match("\x01" . $pattern . "\x01i", $value);

            if ($result === false || $result === 1) {
                return true;
            }
        }

        return false;
    }

    private function isSensitiveQueryParam(string $name): bool
    {
        $lowerName = strtolower($name);

        foreach ($this->sensitiveQueryParams as $pattern) {
            if (strtolower($pattern) === $lowerName) {
                return true;
            }
        }

        return false;
    }
}
