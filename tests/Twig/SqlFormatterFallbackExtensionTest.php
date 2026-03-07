<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Twig;

use MartinKup\OptimizationAdvisorBundle\Twig\SqlFormatterFallbackExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlFormatterFallbackExtension::class)]
#[Group('unit')]
final class SqlFormatterFallbackExtensionTest extends TestCase
{
    private SqlFormatterFallbackExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new SqlFormatterFallbackExtension();
    }

    #[Test]
    public function get_filters_returns_three_filters_with_same_names_as_main_extension(): void
    {
        $filters = $this->extension->getFilters();

        self::assertCount(3, $filters);

        $names = array_map(static fn ($f) => $f->getName(), $filters);
        self::assertContains('oi_prettify_sql', $names);
        self::assertContains('oi_format_sql', $names);
        self::assertContains('oi_replace_query_params', $names);
    }

    #[Test]
    public function prettify_sql_filter_escapes_html(): void
    {
        $filters = $this->extension->getFilters();
        $prettify = null;

        foreach ($filters as $filter) {
            if ($filter->getName() === 'oi_prettify_sql') {
                $prettify = $filter;

                break;
            }
        }

        self::assertNotNull($prettify);

        $callable = $prettify->getCallable();
        self::assertIsCallable($callable);

        $result = $callable('SELECT * FROM "users" WHERE name = \'<script>\'');
        self::assertIsString($result);
        self::assertStringNotContainsString('<script>', $result);
        self::assertStringContainsString('&lt;script&gt;', $result);
    }

    #[Test]
    public function format_sql_filter_escapes_html(): void
    {
        $filters = $this->extension->getFilters();
        $format = null;

        foreach ($filters as $filter) {
            if ($filter->getName() === 'oi_format_sql') {
                $format = $filter;

                break;
            }
        }

        self::assertNotNull($format);

        $callable = $format->getCallable();
        self::assertIsCallable($callable);

        $result = $callable('SELECT 1');
        self::assertIsString($result);
        self::assertSame('SELECT 1', $result);
    }

    #[Test]
    public function replace_query_params_filter_returns_query_unchanged(): void
    {
        $filters = $this->extension->getFilters();
        $replace = null;

        foreach ($filters as $filter) {
            if ($filter->getName() === 'oi_replace_query_params') {
                $replace = $filter;

                break;
            }
        }

        self::assertNotNull($replace);

        $callable = $replace->getCallable();
        self::assertIsCallable($callable);

        $query = 'SELECT * FROM users WHERE id = ?';
        $result = $callable($query, [42]);
        self::assertSame($query, $result);
    }
}
