<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Analysis;

/**
 * One statement executed repeatedly from the same caller line, the signature of an N+1 problem.
 */
final readonly class NPlusOneGroup
{
    public function __construct(
        public string $connection,
        public string $sql,
        public string $caller,
        public int $count,
    ) {}
}
