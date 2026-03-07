<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Sql;

use Stringable;

/**
 * Sanitizes query parameters for safe display in the profiler panel.
 *
 * Converts complex types (objects, resources) to display-safe placeholders
 * and determines whether the resulting parameter set can produce a runnable query.
 */
final readonly class QueryParamSanitizer
{
    /**
     * Sanitize query parameters for profiler display.
     *
     * @param array<array-key, mixed> $params
     *
     * @return array{params: array<array-key, mixed>, runnable: bool}
     */
    public function sanitize(array $params): array
    {
        $runnable = true;
        $sanitized = [];

        foreach ($params as $key => $value) {
            $result = $this->sanitizeValue($value);
            $sanitized[$key] = $result['value'];

            if ($result['runnable']) {
                continue;
            }

            $runnable = false;
        }

        return [
            'params'   => $sanitized,
            'runnable' => $runnable,
        ];
    }

    /** @return array{value: mixed, runnable: bool} */
    private function sanitizeValue(mixed $value): array
    {
        if ($value === null || is_scalar($value)) {
            return ['value' => $value, 'runnable' => true];
        }

        if (is_array($value)) {
            return $this->sanitizeArray($value);
        }

        if ($value instanceof Stringable) {
            return ['value' => (string) $value, 'runnable' => true];
        }

        if (is_object($value)) {
            return ['value' => '{object(' . $value::class . ')}', 'runnable' => false];
        }

        if (is_resource($value)) {
            return ['value' => '/* Resource(' . get_resource_type($value) . ') */', 'runnable' => false];
        }

        return ['value' => '{unknown}', 'runnable' => false];
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array{value: array<array-key, mixed>, runnable: bool}
     */
    private function sanitizeArray(array $values): array
    {
        $runnable = true;
        $sanitized = [];

        foreach ($values as $key => $value) {
            $result = $this->sanitizeValue($value);
            $sanitized[$key] = $result['value'];

            if ($result['runnable']) {
                continue;
            }

            $runnable = false;
        }

        return ['value' => $sanitized, 'runnable' => $runnable];
    }
}
