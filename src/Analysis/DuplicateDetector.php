<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Analysis;

use ChakraCoder\QueryBudget\Recording\QueryLog;

final class DuplicateDetector
{
    /**
     * Statements (per connection) executed more than $threshold times, most frequent first.
     *
     * @return list<DuplicateGroup>
     */
    public function detect(QueryLog $log, int $threshold = 1): array
    {
        /** @var array<string, array{connection: string, sql: string, count: int, callers: array<string, int>}> $groups */
        $groups = [];

        foreach ($log->all() as $query) {
            $key = $query->connection."\0".$query->sql;

            $groups[$key] ??= ['connection' => $query->connection, 'sql' => $query->sql, 'count' => 0, 'callers' => []];
            $groups[$key]['count']++;
            $groups[$key]['callers'][$query->caller] = ($groups[$key]['callers'][$query->caller] ?? 0) + 1;
        }

        $duplicates = [];

        foreach ($groups as $group) {
            if ($group['count'] > $threshold) {
                arsort($group['callers']);
                $duplicates[] = new DuplicateGroup($group['connection'], $group['sql'], $group['count'], $group['callers']);
            }
        }

        usort(
            $duplicates,
            static fn (DuplicateGroup $a, DuplicateGroup $b): int => [$b->count, $a->sql] <=> [$a->count, $b->sql],
        );

        return $duplicates;
    }
}
