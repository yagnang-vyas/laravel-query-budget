<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Recording;

use Countable;

/**
 * The queries captured by one recording scope.
 */
final class QueryLog implements Countable
{
    /** @var list<RecordedQuery> */
    private array $queries = [];

    public function add(RecordedQuery $query): void
    {
        $this->queries[] = $query;
    }

    /**
     * @return list<RecordedQuery>
     */
    public function all(): array
    {
        return $this->queries;
    }

    public function count(): int
    {
        return count($this->queries);
    }

    public function isEmpty(): bool
    {
        return $this->queries === [];
    }
}
