<?php

declare(strict_types=1);

namespace MartinKup\OptimizationAdvisorBundle\Messenger;

use Symfony\Contracts\Service\ResetInterface;

final class TraceRegistry implements ResetInterface
{
    private const int MAX_RECORDS = 200;

    /** @var array<int, array<string, mixed>> */
    private array $records = [];

    private int $nextSeq = 1;

    private bool $truncated = false;

    private int $droppedCount = 0;

    /** @var array<int, int> */
    private array $dispatchStack = [];

    /** @param array<string, mixed> $record */
    public function append(array $record): ?int
    {
        if (count($this->records) >= self::MAX_RECORDS) {
            $this->truncated = true;
            $this->droppedCount += 1;

            return null;
        }

        $seq = $this->nextSeq;
        $this->nextSeq += 1;
        $this->records[$seq] = $record;

        return $seq;
    }

    /** @param array<string, mixed> $updates */
    public function update(int $seq, array $updates): void
    {
        if (!isset($this->records[$seq])) {
            return;
        }

        $this->records[$seq] = array_merge($this->records[$seq], $updates);
    }

    public function pushDispatchStack(int $seq): void
    {
        $this->dispatchStack[] = $seq;
    }

    public function popDispatchStack(): void
    {
        array_pop($this->dispatchStack);
    }

    public function getCurrentParentSeq(): ?int
    {
        if ($this->dispatchStack === []) {
            return null;
        }

        return $this->dispatchStack[array_key_last($this->dispatchStack)];
    }

    public function getCurrentDepth(): int
    {
        return count($this->dispatchStack);
    }

    /** @return array<int, array<string, mixed>> */
    public function getRecords(): array
    {
        return $this->records;
    }

    public function getRecordCount(): int
    {
        return count($this->records);
    }

    public function isTruncated(): bool
    {
        return $this->truncated;
    }

    public function getDroppedCount(): int
    {
        return $this->droppedCount;
    }

    public function reset(): void
    {
        $this->records = [];
        $this->nextSeq = 1;
        $this->truncated = false;
        $this->droppedCount = 0;
        $this->dispatchStack = [];
    }
}
