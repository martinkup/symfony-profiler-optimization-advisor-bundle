<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Twig;

use MartinKup\OptimizationAdvisorBundle\Twig\SqlFormatterExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Cloner\VarCloner;

/**
 * Unit tests for SqlFormatterExtension Twig filters.
 *
 * Test Coverage:
 * - Filter registration (names and count)
 * - prettifySql produces highlighted inline HTML
 * - formatSql with highlight produces indented highlighted HTML
 * - formatSql without highlight produces plain text
 * - replaceQueryParams with positional placeholders
 * - replaceQueryParams with named placeholders
 * - replaceQueryParams with null values
 * - replaceQueryParams with boolean values
 * - replaceQueryParams with binary strings
 * - replaceQueryParams with VarDumper Data object
 *
 * @see SqlFormatterExtension
 */
#[CoversClass(SqlFormatterExtension::class)]
#[Group('unit')]
final class SqlFormatterExtensionTest extends TestCase
{
    private SqlFormatterExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new SqlFormatterExtension();
    }

    public function testGetFiltersReturnsThreeFilters(): void
    {
        $filters = $this->extension->getFilters();

        self::assertCount(3, $filters);

        $names = array_map(static fn ($f) => $f->getName(), $filters);
        self::assertContains('oi_prettify_sql', $names);
        self::assertContains('oi_format_sql', $names);
        self::assertContains('oi_replace_query_params', $names);
    }

    public function testPrettifySqlContainsHighlightMarkup(): void
    {
        $result = $this->extension->prettifySql('SELECT id FROM users WHERE id = 1');

        self::assertStringContainsString('<pre', $result);
        self::assertStringContainsString('class="keyword"', $result);
        self::assertStringContainsString('SELECT', $result);
    }

    public function testFormatSqlWithHighlightContainsPreTag(): void
    {
        $result = $this->extension->formatSql('SELECT id FROM users WHERE id = 1', highlight: true);

        self::assertStringContainsString('<pre', $result);
        self::assertStringContainsString('class="keyword"', $result);
        self::assertStringContainsString('SELECT', $result);
    }

    public function testFormatSqlWithoutHighlightProducesPlainText(): void
    {
        $result = $this->extension->formatSql('SELECT id FROM users WHERE id = 1', highlight: false);

        self::assertStringNotContainsString('<pre', $result);
        self::assertStringNotContainsString('class=', $result);
        self::assertStringContainsString('SELECT', $result);
        self::assertStringContainsString('users', $result);
    }

    public function testReplaceQueryParamsPositional(): void
    {
        $sql = 'SELECT * FROM users WHERE id = ? AND name = ?';
        $params = [42, 'Alice'];

        $result = $this->extension->replaceQueryParams($sql, $params);

        self::assertSame("SELECT * FROM users WHERE id = 42 AND name = 'Alice'", $result);
    }

    public function testReplaceQueryParamsNamed(): void
    {
        $sql = 'SELECT * FROM users WHERE id = :id AND status = :status';
        $params = ['id' => 7, 'status' => 'active'];

        $result = $this->extension->replaceQueryParams($sql, $params);

        self::assertSame("SELECT * FROM users WHERE id = 7 AND status = 'active'", $result);
    }

    public function testReplaceQueryParamsNull(): void
    {
        $sql = 'SELECT * FROM users WHERE deleted_at = ?';
        $params = [null];

        $result = $this->extension->replaceQueryParams($sql, $params);

        self::assertSame('SELECT * FROM users WHERE deleted_at = NULL', $result);
    }

    public function testReplaceQueryParamsBool(): void
    {
        $sql = 'SELECT * FROM users WHERE active = ? AND verified = ?';
        $params = [true, false];

        $result = $this->extension->replaceQueryParams($sql, $params);

        self::assertSame('SELECT * FROM users WHERE active = 1 AND verified = 0', $result);
    }

    public function testReplaceQueryParamsBinary(): void
    {
        $sql = 'SELECT * FROM files WHERE hash = ?';
        $binaryData = "\x00\x01\x02\xff";
        $params = [$binaryData];

        $result = $this->extension->replaceQueryParams($sql, $params);

        self::assertSame('SELECT * FROM files WHERE hash = 0x000102ff', $result);
    }

    public function testReplaceQueryParamsWithDataObject(): void
    {
        $cloner = new VarCloner();
        $data = $cloner->cloneVar([42, 'hello']);

        $sql = 'SELECT * FROM users WHERE id = ? AND name = ?';

        $result = $this->extension->replaceQueryParams($sql, $data);

        self::assertSame("SELECT * FROM users WHERE id = 42 AND name = 'hello'", $result);
    }

    public function testReplaceQueryParamsEmptyParams(): void
    {
        $sql = 'SELECT 1';

        $result = $this->extension->replaceQueryParams($sql, []);

        self::assertSame('SELECT 1', $result);
    }
}
