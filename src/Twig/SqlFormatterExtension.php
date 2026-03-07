<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Twig;

use Doctrine\SqlFormatter\HtmlHighlighter;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use stdClass;
use Stringable;
use Symfony\Component\VarDumper\Cloner\Data;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Registers Optimization-Insights-specific SQL formatting Twig filters.
 *
 * Uses `doctrine/sql-formatter` directly so the profiler panel
 * remains independent of Doctrine-Bundle's own Twig extension.
 */
final class SqlFormatterExtension extends AbstractExtension
{
    /** @return array<TwigFilter> */
    public function getFilters(): array
    {
        return [
            new TwigFilter('oi_prettify_sql', $this->prettifySql(...), ['is_safe' => ['html']]),
            new TwigFilter('oi_format_sql', $this->formatSql(...), ['is_safe' => ['html']]),
            new TwigFilter('oi_replace_query_params', $this->replaceQueryParams(...)),
        ];
    }

    public function prettifySql(string $sql): string
    {
        return $this->createFormatter(highlight: true)->highlight($sql);
    }

    public function formatSql(string $sql, bool $highlight = true): string
    {
        return $this->createFormatter($highlight)->format($sql);
    }

    /** @param array<array-key, mixed>|Data $parameters */
    public function replaceQueryParams(string $query, array | Data $parameters): string
    {
        $params = $parameters instanceof Data
            ? $this->extractDataValue($parameters)
            : $parameters;

        if ($params === []) {
            return $query;
        }

        $counter = new stdClass();
        $counter->value = 0;

        return (string) preg_replace_callback(
            '/(?<!\?)\?(?!\?)|(?<!:)(:[a-z0-9_]+)/i',
            function (array $matches) use ($params, $counter): string {
                if ($matches[0] === '?') {
                    $value = $params[$counter->value] ?? null;
                    $counter->value += 1;

                    return (string) $this->escapeValue($value);
                }

                $name = ltrim($matches[0], ':');
                $value = $params[$name] ?? $params[$matches[0]] ?? null;

                return (string) $this->escapeValue($value);
            },
            $query,
        );
    }

    private function createFormatter(bool $highlight): SqlFormatter
    {
        return new SqlFormatter(
            $highlight ? new HtmlHighlighter(
                [
                    HtmlHighlighter::HIGHLIGHT_PRE            => 'class="highlight highlight-sql"',
                    HtmlHighlighter::HIGHLIGHT_QUOTE          => 'class="string"',
                    HtmlHighlighter::HIGHLIGHT_BACKTICK_QUOTE => 'class="string"',
                    HtmlHighlighter::HIGHLIGHT_RESERVED       => 'class="keyword"',
                    HtmlHighlighter::HIGHLIGHT_BOUNDARY       => 'class="symbol"',
                    HtmlHighlighter::HIGHLIGHT_NUMBER         => 'class="number"',
                    HtmlHighlighter::HIGHLIGHT_WORD           => 'class="word"',
                    HtmlHighlighter::HIGHLIGHT_ERROR          => 'class="error"',
                    HtmlHighlighter::HIGHLIGHT_COMMENT        => 'class="comment"',
                    HtmlHighlighter::HIGHLIGHT_VARIABLE       => 'class="variable"',
                ],
            ) : new NullHighlighter(),
        );
    }

    private function escapeValue(mixed $value): string | int | float
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            if (!mb_check_encoding($value, 'UTF-8')) {
                return '0x' . bin2hex($value);
            }

            return "'" . addslashes($value) . "'";
        }

        if (is_array($value)) {
            $escaped = array_map(fn (mixed $v): string | int | float => $this->escapeValue($v), $value);

            return implode(', ', array_map(static fn (string | int | float $v): string => (string) $v, $escaped));
        }

        if ($value instanceof Stringable) {
            return "'" . addslashes((string) $value) . "'";
        }

        return '?';
    }

    /** @return array<array-key, mixed> */
    private function extractDataValue(Data $data): array
    {
        $raw = $data->getValue(true);

        if (is_array($raw)) {
            return $raw;
        }

        return [];
    }
}
