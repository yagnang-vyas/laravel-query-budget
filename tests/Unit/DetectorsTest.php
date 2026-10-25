<?php

declare(strict_types=1);

use ChakraCoder\QueryBudget\Analysis\BudgetReport;
use ChakraCoder\QueryBudget\Analysis\DuplicateDetector;
use ChakraCoder\QueryBudget\Analysis\NPlusOneDetector;
use ChakraCoder\QueryBudget\Recording\QueryLog;
use ChakraCoder\QueryBudget\Recording\RecordedQuery;

function logOf(RecordedQuery ...$queries): QueryLog
{
    $log = new QueryLog;

    foreach ($queries as $query) {
        $log->add($query);
    }

    return $log;
}

function q(string $sql, string $caller = 'app/A.php:1', string $connection = 'mysql', float $time = 1.0): RecordedQuery
{
    return new RecordedQuery($connection, $sql, $time, 1, $caller);
}

it('flags statements executed more often than the duplicate threshold', function (): void {
    $log = logOf(q('select 1'), q('select 1'), q('select 2'));

    $groups = (new DuplicateDetector)->detect($log, 1);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->sql)->toBe('select 1')
        ->and($groups[0]->count)->toBe(2);
});

it('respects a higher duplicate threshold', function (): void {
    $log = logOf(q('select 1'), q('select 1'), q('select 1'));

    expect((new DuplicateDetector)->detect($log, 3))->toBe([])
        ->and((new DuplicateDetector)->detect($log, 2))->toHaveCount(1);
});

it('keeps the same sql on different connections apart', function (): void {
    $log = logOf(q('select 1', connection: 'a'), q('select 1', connection: 'b'));

    expect((new DuplicateDetector)->detect($log))->toBe([]);
});

it('counts callers per duplicate group, busiest first', function (): void {
    $log = logOf(q('select 1', 'app/A.php:1'), q('select 1', 'app/B.php:2'), q('select 1', 'app/B.php:2'));

    $group = (new DuplicateDetector)->detect($log)[0];

    expect($group->callers)->toBe(['app/B.php:2' => 2, 'app/A.php:1' => 1]);
});

it('orders duplicate groups by count then sql', function (): void {
    $log = logOf(q('select b'), q('select b'), q('select a'), q('select a'), q('select c'), q('select c'), q('select c'));

    $sql = array_map(fn ($g) => $g->sql, (new DuplicateDetector)->detect($log));

    expect($sql)->toBe(['select c', 'select a', 'select b']);
});

it('detects n plus one only for the same sql from the same caller line', function (): void {
    $log = logOf(
        q('select * from authors where id = ?', 'app/A.php:10'),
        q('select * from authors where id = ?', 'app/A.php:10'),
        q('select * from authors where id = ?', 'app/A.php:10'),
        q('select * from tags where id = ?', 'app/A.php:11'),
        q('select * from tags where id = ?', 'app/A.php:12'),
        q('select * from tags where id = ?', 'app/A.php:13'),
    );

    $groups = (new NPlusOneDetector)->detect($log, 3);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->sql)->toBe('select * from authors where id = ?')
        ->and($groups[0]->caller)->toBe('app/A.php:10')
        ->and($groups[0]->count)->toBe(3);
});

it('does not flag n plus one below the threshold', function (): void {
    $log = logOf(q('select 1'), q('select 1'));

    expect((new NPlusOneDetector)->detect($log, 3))->toBe([])
        ->and((new NPlusOneDetector)->detect($log, 2))->toHaveCount(1);
});

it('orders n plus one groups by count then sql and caller', function (): void {
    $log = logOf(
        q('select b', 'x:1'), q('select b', 'x:1'),
        q('select a', 'x:2'), q('select a', 'x:2'),
        q('select a', 'x:1'), q('select a', 'x:1'),
        q('select c', 'x:1'), q('select c', 'x:1'), q('select c', 'x:1'),
    );

    $order = array_map(fn ($g) => $g->sql.'@'.$g->caller, (new NPlusOneDetector)->detect($log, 2));

    expect($order)->toBe(['select c@x:1', 'select a@x:1', 'select a@x:2', 'select b@x:1']);
});

it('builds a report with the five slowest queries', function (): void {
    $queries = [];
    foreach ([5.0, 1.0, 9.0, 3.0, 7.0, 2.0, 8.0] as $i => $time) {
        $queries[] = q('select '.$i, time: $time);
    }

    $report = BudgetReport::fromLog(logOf(...$queries));

    expect($report->totalCount)->toBe(7)
        ->and(array_map(fn ($query) => $query->timeMs, $report->slowest))->toBe([9.0, 8.0, 7.0, 5.0, 3.0]);
});

it('renders a readable report without binding values', function (): void {
    $log = logOf(
        new RecordedQuery('mysql', 'select * from users where email = ?', 0.5, 1, 'app/Http/UserController.php:12'),
        new RecordedQuery('mysql', 'select * from users where email = ?', 0.7, 1, 'app/Http/UserController.php:12'),
        new RecordedQuery('mysql', 'select * from users where email = ?', 0.9, 1, 'app/Http/UserController.php:12'),
    );

    $text = (string) BudgetReport::fromLog($log, 1, 3);

    expect($text)
        ->toContain('Query budget report: 3 queries executed.')
        ->toContain('Duplicate queries (1):')
        ->toContain('3x select * from users where email = ?')
        ->toContain('3x from app/Http/UserController.php:12')
        ->toContain('Possible N+1 queries (1):')
        ->toContain('from app/Http/UserController.php:12')
        ->toContain('Slowest queries:')
        ->toContain('1. 0.90 ms  select * from users where email = ?')
        ->not->toContain('alice@example.com');
});

it('renders a minimal report when nothing is wrong', function (): void {
    expect((string) BudgetReport::fromLog(logOf(q('select 1'))))
        ->toContain('Query budget report: 1 query executed.')
        ->not->toContain('Duplicate')
        ->not->toContain('N+1');

    expect((string) BudgetReport::fromLog(logOf()))->toBe('Query budget report: 0 queries executed.');
});

it('exposes whether duplicates or n plus one were found', function (): void {
    $report = BudgetReport::fromLog(logOf(q('select 1'), q('select 1'), q('select 1')));

    expect($report->hasDuplicates())->toBeTrue()->and($report->hasNPlusOne())->toBeTrue();
    expect(BudgetReport::fromLog(logOf(q('select 1')))->hasDuplicates())->toBeFalse();
});

it('tracks queries in a log', function (): void {
    $log = logOf(q('a'), q('b'));

    expect($log)->toHaveCount(2)->and($log->isEmpty())->toBeFalse()->and(logOf()->isEmpty())->toBeTrue();
});
