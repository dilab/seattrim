<?php

namespace App\Zoom\Data;

/**
 * A fully paginated result: every page followed to exhaustion, plus what Zoom
 * claimed the total was, so the scan can flag partial sets.
 *
 * @template T
 */
final readonly class PagedList
{
    /** @param array<int, T> $items */
    public function __construct(
        public array $items,
        public int $pages,
        public ?int $totalRecords,
        public ?string $trackingId = null,
    ) {}

    public function count(): int
    {
        return count($this->items);
    }

    /** True when Zoom's total_records disagrees with what we actually received. */
    public function isPartial(): bool
    {
        return $this->totalRecords !== null && $this->totalRecords !== $this->count();
    }
}
