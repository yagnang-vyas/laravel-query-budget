<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Analysis;

use ChakraCoder\QueryBudget\Recording\QueryLog;

final class NPlusOneDetector
{
    /**
     * Statements executed at least $threshold times from one caller line, most frequent first.
     *
     * @return list<NPlusOneGroup>
     */
    public function detect(QueryLog $log, int $threshold = 3): array
    {
        /** @var array<string, array{connection: string, sql: string, caller: string, count: int}> $groups */
        $groups = [];

        foreach ($log->all() as $query) {
            $key = $query->connection."\0".$query->caller."\0".$query->sql;

            $groups[$key] ??= ['connection' => $query->connection, 'sql' => $query->sql, 'caller' => $query->caller, 'count' => 0];
            $groups[$key]['count']++;
        }

        $found = [];

        foreach ($groups as $group) {
            if ($group['count'] >= $threshold) {
                $found[] = new NPlusOneGroup($group['connection'], $group['sql'], $group['caller'], $group['count']);
            }
        }

        usort(
            $found,
            static fn (NPlusOneGroup $a, NPlusOneGroup $b): int => [$b->count, $a->sql, $a->caller] <=> [$a->count, $b->sql, $b->caller],
        );

        return $found;
    }
}
