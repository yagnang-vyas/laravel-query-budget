<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Analysis;

use ChakraCoder\QueryBudget\Recording\QueryLog;
use ChakraCoder\QueryBudget\Recording\RecordedQuery;
use Stringable;

/**
 * Summary of one recording scope. Contains normalized SQL, counts and caller
 * locations only; binding values are never available to it.
 */
final readonly class BudgetReport implements Stringable
{
    public const SLOWEST_LIMIT = 5;

    /**
     * @param  list<DuplicateGroup>  $duplicates
     * @param  list<NPlusOneGroup>  $nPlusOne
     * @param  list<RecordedQuery>  $slowest
     */
    public function __construct(
        public int $totalCount,
        public array $duplicates,
        public array $nPlusOne,
        public array $slowest,
    ) {}

    public static function fromLog(QueryLog $log, int $duplicateThreshold = 1, int $nPlusOneThreshold = 3): self
    {
        $slowest = $log->all();
        usort($slowest, static fn (RecordedQuery $a, RecordedQuery $b): int => $b->timeMs <=> $a->timeMs);

        return new self(
            totalCount: $log->count(),
            duplicates: (new DuplicateDetector)->detect($log, $duplicateThreshold),
            nPlusOne: (new NPlusOneDetector)->detect($log, $nPlusOneThreshold),
            slowest: array_slice($slowest, 0, self::SLOWEST_LIMIT),
        );
    }

    public function hasDuplicates(): bool
    {
        return $this->duplicates !== [];
    }

    public function hasNPlusOne(): bool
    {
        return $this->nPlusOne !== [];
    }

    public function __toString(): string
    {
        $lines = [sprintf('Query budget report: %d %s executed.', $this->totalCount, $this->totalCount === 1 ? 'query' : 'queries')];

        if ($this->duplicates !== []) {
            $lines[] = '';
            $lines[] = sprintf('Duplicate queries (%d):', count($this->duplicates));

            foreach ($this->duplicates as $group) {
                $lines[] = sprintf('  %dx %s', $group->count, $group->sql);

                foreach ($group->callers as $caller => $count) {
                    $lines[] = sprintf('       %dx from %s', $count, $caller);
                }
            }
        }

        if ($this->nPlusOne !== []) {
            $lines[] = '';
            $lines[] = sprintf('Possible N+1 queries (%d):', count($this->nPlusOne));

            foreach ($this->nPlusOne as $group) {
                $lines[] = sprintf('  %dx %s', $group->count, $group->sql);
                $lines[] = sprintf('       from %s', $group->caller);
            }
        }

        if ($this->slowest !== []) {
            $lines[] = '';
            $lines[] = 'Slowest queries:';

            foreach ($this->slowest as $position => $query) {
                $lines[] = sprintf('  %d. %.2f ms  %s', $position + 1, $query->timeMs, $query->sql);
            }
        }

        return implode("\n", $lines);
    }
}
