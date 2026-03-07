<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Sql;

use MartinKup\OptimizationAdvisorBundle\Sql\SqlNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SqlNormalizer SQL normalization and fingerprinting.
 *
 * Test Coverage:
 * - String literal replacement with placeholders
 * - Numeric literal replacement with placeholders
 * - IN list collapsing to single placeholder
 * - Whitespace normalization
 * - Deterministic fingerprinting via MD5 of normalized SQL
 * - Same fingerprint for semantically identical queries
 * - SQL kind extraction (SELECT/INSERT/UPDATE/DELETE/OTHER)
 * - Table name extraction from FROM/JOIN/INTO/UPDATE clauses
 * - SQL sanitization with truncation
 * - Empty and edge case handling
 *
 * @see SqlNormalizer
 */
#[CoversClass(SqlNormalizer::class)]
#[Group('unit')]
final class SqlNormalizerTest extends TestCase
{
    private SqlNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new SqlNormalizer();
    }

    public function testNormalizeReplacesStringLiterals(): void
    {
        $sql = "SELECT * FROM users WHERE name = 'John' AND email = 'john@example.com'";

        $result = $this->normalizer->normalize($sql);

        self::assertSame('SELECT * FROM users WHERE name = ? AND email = ?', $result);
    }

    public function testNormalizeReplacesNumericLiterals(): void
    {
        $sql = 'SELECT * FROM users WHERE id = 42 AND age > 18';

        $result = $this->normalizer->normalize($sql);

        self::assertSame('SELECT * FROM users WHERE id = ? AND age > ?', $result);
    }

    public function testNormalizeCollapsesInLists(): void
    {
        $sql = "SELECT * FROM users WHERE id IN (1, 2, 3, 4, 5) AND status IN ('active', 'pending')";

        $result = $this->normalizer->normalize($sql);

        self::assertSame('SELECT * FROM users WHERE id IN (?) AND status IN (?)', $result);
    }

    public function testNormalizeCollapsesWhitespace(): void
    {
        $sql = "SELECT   *  \n  FROM   users \t  WHERE   id = 1";

        $result = $this->normalizer->normalize($sql);

        self::assertSame('SELECT * FROM users WHERE id = ?', $result);
    }

    public function testFingerprintIsDeterministic(): void
    {
        $sql = "SELECT * FROM users WHERE id = 42";

        $fp1 = $this->normalizer->fingerprint($sql);
        $fp2 = $this->normalizer->fingerprint($sql);

        self::assertSame($fp1, $fp2);
        self::assertSame(32, strlen($fp1));
    }

    public function testFingerprintMatchesForSamePattern(): void
    {
        $sql1 = "SELECT * FROM users WHERE id = 1 AND name = 'Alice'";
        $sql2 = "SELECT * FROM users WHERE id = 999 AND name = 'Bob'";

        self::assertSame(
            $this->normalizer->fingerprint($sql1),
            $this->normalizer->fingerprint($sql2),
        );
    }

    public function testExtractKindSelect(): void
    {
        self::assertSame('SELECT', $this->normalizer->extractKind('SELECT * FROM users'));
    }

    public function testExtractKindInsert(): void
    {
        self::assertSame('INSERT', $this->normalizer->extractKind('INSERT INTO users (name) VALUES (?)'));
    }

    public function testExtractKindUpdate(): void
    {
        self::assertSame('UPDATE', $this->normalizer->extractKind('UPDATE users SET name = ?'));
    }

    public function testExtractKindDelete(): void
    {
        self::assertSame('DELETE', $this->normalizer->extractKind('DELETE FROM users WHERE id = ?'));
    }

    public function testExtractKindOther(): void
    {
        self::assertSame('OTHER', $this->normalizer->extractKind('CREATE TABLE users (id INT)'));
    }

    public function testExtractTablesFromSelect(): void
    {
        $sql = 'SELECT u.*, o.name FROM users u JOIN organizations o ON u.org_id = o.id';

        $tables = $this->normalizer->extractTables($sql);

        self::assertContains('users', $tables);
        self::assertContains('organizations', $tables);
    }

    public function testExtractTablesFromInsert(): void
    {
        $sql = 'INSERT INTO audit_log (action, user_id) VALUES (?, ?)';

        $tables = $this->normalizer->extractTables($sql);

        self::assertContains('audit_log', $tables);
    }

    public function testExtractTablesFromUpdate(): void
    {
        $sql = 'UPDATE users SET name = ? WHERE id = ?';

        $tables = $this->normalizer->extractTables($sql);

        self::assertContains('users', $tables);
    }

    public function testExtractTablesDeduplicates(): void
    {
        $sql = 'SELECT * FROM users u JOIN users u2 ON u.parent_id = u2.id';

        $tables = $this->normalizer->extractTables($sql);

        self::assertCount(1, $tables);
        self::assertSame('users', $tables[0]);
    }

    public function testSanitizeTruncatesLongSql(): void
    {
        $sql = 'SELECT ' . str_repeat('a', 300) . ' FROM users';

        $result = $this->normalizer->sanitize($sql, 240);

        self::assertSame(240, mb_strlen($result));
        self::assertStringEndsWith('...', $result);
    }

    public function testSanitizeKeepsShortSql(): void
    {
        $sql = "SELECT * FROM users WHERE id = 1";

        $result = $this->normalizer->sanitize($sql);

        self::assertSame('SELECT * FROM users WHERE id = ?', $result);
    }

    public function testNormalizeHandlesEmptyString(): void
    {
        self::assertSame('', $this->normalizer->normalize(''));
    }
}
