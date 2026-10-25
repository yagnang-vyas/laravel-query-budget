<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Recording;

/**
 * A single executed query. Binding values are never stored, only their number.
 */
final readonly class RecordedQuery
{
    public function __construct(
        public string $connection,
        public string $sql,
        public float $timeMs,
        public int $bindingCount,
        public string $caller,
    ) {}
}
