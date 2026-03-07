<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Fallback Twig extension when doctrine/sql-formatter is not installed.
 *
 * Registers the same filter names as SqlFormatterExtension but with
 * no-op implementations that simply escape HTML output.
 */
final class SqlFormatterFallbackExtension extends AbstractExtension
{
    /** @return array<TwigFilter> */
    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'oi_prettify_sql',
                static fn (string $sql): string => htmlspecialchars($sql, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                ['is_safe' => ['html']],
            ),
            new TwigFilter(
                'oi_format_sql',
                static fn (string $sql): string => htmlspecialchars($sql, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                ['is_safe' => ['html']],
            ),
            new TwigFilter(
                'oi_replace_query_params',
                static fn (string $query, mixed $params = []): string => $query,
            ),
        ];
    }
}
