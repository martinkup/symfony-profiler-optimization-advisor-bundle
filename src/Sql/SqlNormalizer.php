<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Sql;

final readonly class SqlNormalizer
{
    public function normalize(string $sql): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;

        // Replace quoted strings
        $normalized = preg_replace("/'[^']*'/", '?', $normalized) ?? $normalized;

        // Replace numeric literals (not part of identifiers)
        $normalized = preg_replace('/\b\d+(\.\d+)?\b/', '?', $normalized) ?? $normalized;

        // Collapse IN lists: IN (?, ?, ?, ...) -> IN (?)
        $normalized = preg_replace('/\bIN\s*\(\s*\?(?:\s*,\s*\?)*\s*\)/i', 'IN (?)', $normalized) ?? $normalized;

        return $normalized;
    }

    public function fingerprint(string $sql): string
    {
        return md5($this->normalize($sql));
    }

    public function extractKind(string $sql): string
    {
        $trimmed = ltrim($sql);
        $token = strtok($trimmed, " \t\n\r");
        $firstWord = strtoupper($token !== false ? $token : '');

        return match ($firstWord) {
            'SELECT', 'INSERT', 'UPDATE', 'DELETE' => $firstWord,
            default => 'OTHER',
        };
    }

    /** @return array<int, string> */
    public function extractTables(string $sql): array
    {
        $tables = [];
        $normalized = preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;

        // FROM table
        if (preg_match_all('/\bFROM\s+([a-z_][a-z0-9_.]*)/i', $normalized, $matches) > 0) {
            $tables = [...$tables, ...$matches[1]];
        }

        // JOIN table
        if (preg_match_all('/\bJOIN\s+([a-z_][a-z0-9_.]*)/i', $normalized, $matches) > 0) {
            $tables = [...$tables, ...$matches[1]];
        }

        // INTO table
        if (preg_match_all('/\bINTO\s+([a-z_][a-z0-9_.]*)/i', $normalized, $matches) > 0) {
            $tables = [...$tables, ...$matches[1]];
        }

        // UPDATE table
        if (preg_match_all('/\bUPDATE\s+([a-z_][a-z0-9_.]*)/i', $normalized, $matches) > 0) {
            $tables = [...$tables, ...$matches[1]];
        }

        return array_values(array_unique($tables));
    }

    public function sanitize(string $sql, int $max = 240): string
    {
        $normalized = $this->normalize($sql);

        if (mb_strlen($normalized) <= $max) {
            return $normalized;
        }

        return mb_substr($normalized, 0, $max - 3) . '...';
    }
}
