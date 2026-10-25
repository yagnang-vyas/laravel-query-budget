<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Analysis;

/**
 * One statement that was executed more often than the duplicate threshold allows.
 */
final readonly class DuplicateGroup
{
    /**
     * @param  array<string, int>  $callers  caller location => number of executions from it
     */
    public function __construct(
        public string $connection,
        public string $sql,
        public int $count,
        public array $callers,
    ) {}
}
