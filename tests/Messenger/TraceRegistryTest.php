<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Tests\Messenger;

use MartinKup\OptimizationAdvisorBundle\Messenger\TraceRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TraceRegistry dispatch trace storage.
 *
 * Test Coverage:
 * - append() returns sequential numbers starting at 1
 * - update() merges additional data into an existing record
 * - Truncation at MAX_RECORDS (200) returns null and sets isTruncated flag
 * - Dispatch stack nesting tracks parent sequences and depth correctly
 * - reset() clears all internal state
 * - update() silently ignores unknown sequence numbers
 * - Empty dispatch stack returns null parent and zero depth
 *
 * @see TraceRegistry
 */
#[CoversClass(TraceRegistry::class)]
#[Group('unit')]
final class TraceRegistryTest extends TestCase
{
    private TraceRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new TraceRegistry();
    }

    public function testAppendReturnsSequenceNumber(): void
    {
        $seq1 = $this->registry->append(['message_class' => 'CommandA']);
        $seq2 = $this->registry->append(['message_class' => 'CommandB']);
        $seq3 = $this->registry->append(['message_class' => 'CommandC']);

        self::assertSame(1, $seq1);
        self::assertSame(2, $seq2);
        self::assertSame(3, $seq3);
    }

    public function testUpdateMergesIntoRecord(): void
    {
        $seq = $this->registry->append(['message_class' => 'CommandA', 'succeeded' => true]);
        self::assertNotNull($seq);

        $this->registry->update(
            $seq,
            [
            'handler_class'       => 'HandlerA',
            'handler_duration_ms' => 42.5,
            ],
        );

        $records = $this->registry->getRecords();

        self::assertSame('CommandA', $records[$seq]['message_class']);
        self::assertTrue($records[$seq]['succeeded']);
        self::assertSame('HandlerA', $records[$seq]['handler_class']);
        self::assertSame(42.5, $records[$seq]['handler_duration_ms']);
    }

    public function testTruncationAtMaxRecords(): void
    {
        for ($i = 1; $i <= 200; $i++) {
            $seq = $this->registry->append(['message_class' => 'Cmd' . $i]);
            self::assertSame($i, $seq);
        }

        self::assertFalse($this->registry->isTruncated());
        self::assertSame(0, $this->registry->getDroppedCount());

        $result = $this->registry->append(['message_class' => 'Cmd201']);

        self::assertNull($result);
        self::assertTrue($this->registry->isTruncated());
        self::assertSame(1, $this->registry->getDroppedCount());
        self::assertSame(200, $this->registry->getRecordCount());
    }

    public function testDispatchStackNesting(): void
    {
        $this->registry->pushDispatchStack(1);

        self::assertSame(1, $this->registry->getCurrentParentSeq());
        self::assertSame(1, $this->registry->getCurrentDepth());

        $this->registry->pushDispatchStack(2);

        self::assertSame(2, $this->registry->getCurrentParentSeq());
        self::assertSame(2, $this->registry->getCurrentDepth());

        $this->registry->popDispatchStack();

        self::assertSame(1, $this->registry->getCurrentParentSeq());
        self::assertSame(1, $this->registry->getCurrentDepth());
    }

    public function testResetClearsAllState(): void
    {
        $this->registry->append(['message_class' => 'CmdA']);
        $this->registry->append(['message_class' => 'CmdB']);
        $this->registry->pushDispatchStack(1);

        self::assertSame(2, $this->registry->getRecordCount());
        self::assertSame(1, $this->registry->getCurrentDepth());

        $this->registry->reset();

        self::assertSame([], $this->registry->getRecords());
        self::assertSame(0, $this->registry->getRecordCount());
        self::assertFalse($this->registry->isTruncated());
        self::assertSame(0, $this->registry->getDroppedCount());
        self::assertNull($this->registry->getCurrentParentSeq());
        self::assertSame(0, $this->registry->getCurrentDepth());

        $seq = $this->registry->append(['message_class' => 'CmdC']);
        self::assertSame(1, $seq, 'Sequence numbers should restart from 1 after reset');
    }

    public function testUpdateIgnoresUnknownSeq(): void
    {
        $this->registry->append(['message_class' => 'CmdA']);

        $this->registry->update(999, ['handler_class' => 'ShouldBeIgnored']);

        $records = $this->registry->getRecords();

        self::assertCount(1, $records);
        self::assertArrayNotHasKey(999, $records);
    }

    public function testEmptyDispatchStackReturnsNull(): void
    {
        self::assertNull($this->registry->getCurrentParentSeq());
        self::assertSame(0, $this->registry->getCurrentDepth());
    }
}
