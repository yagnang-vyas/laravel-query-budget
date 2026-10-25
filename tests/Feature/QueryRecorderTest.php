<?php

declare(strict_types=1);

use ChakraCoder\QueryBudget\Recording\CallerResolver;
use ChakraCoder\QueryBudget\Recording\QueryRecorder;
use ChakraCoder\QueryBudget\Tests\Fixtures\Models\Author;
use ChakraCoder\QueryBudget\Tests\Fixtures\Models\Post;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->recorder = $this->app->make(QueryRecorder::class);
});

it('records nothing while no scope is open', function (): void {
    expect($this->recorder->isRecording())->toBeFalse();

    DB::select('select 1');

    $log = $this->recorder->record(fn () => DB::select('select 2'));

    expect($log)->toHaveCount(1);
});

it('records normalized sql, connection, timing, binding count and caller', function (): void {
    $log = $this->recorder->record(fn () => DB::select('SELECT  *  FROM sqlite_master WHERE name = ? AND type = ?', ['alice@example.com', 'table']));

    $query = $log->all()[0];

    expect($query->connection)->toBe('testing')
        ->and($query->sql)->toBe('select * from sqlite_master where name = ? and type = ?')
        ->and($query->bindingCount)->toBe(2)
        ->and($query->timeMs)->toBeGreaterThanOrEqual(0.0)
        ->and($query->caller)->toStartWith('tests/Feature/QueryRecorderTest.php:');
});

it('never stores binding values or inline literals', function (): void {
    $log = $this->recorder->record(function (): void {
        DB::select('select * from sqlite_master where name = ?', ['alice@example.com']);
        DB::select("select * from sqlite_master where name = 'bob@example.com' and rootpage = 4242");
    });

    $dump = print_r($log, true).serialize($log);

    expect($dump)->not->toContain('alice@example.com')
        ->and($dump)->not->toContain('bob@example.com')
        ->and($dump)->not->toContain('4242')
        ->and($log->all()[1]->sql)->toBe('select * from sqlite_master where name = ? and rootpage = ?');
});

it('nests scopes so the outer scope sees the inner queries too', function (): void {
    $inner = null;

    $outer = $this->recorder->record(function () use (&$inner): void {
        DB::select('select 1');
        $inner = $this->recorder->record(fn () => DB::select('select 2'));
        DB::select('select 3');
    });

    expect($outer)->toHaveCount(3)
        ->and($inner)->toHaveCount(1)
        ->and($inner->all()[0]->sql)->toBe('select ?')
        ->and($this->recorder->isRecording())->toBeFalse();
});

it('can close scopes out of order', function (): void {
    $first = $this->recorder->start();
    DB::select('select 1');
    $second = $this->recorder->start();
    DB::select('select 2');

    $firstLog = $this->recorder->stop($first);
    DB::select('select 3');
    $secondLog = $this->recorder->stop($second);

    expect($firstLog)->toHaveCount(2)->and($secondLog)->toHaveCount(2);
});

it('closes the scope even when the callback throws', function (): void {
    expect(fn () => $this->recorder->record(fn () => throw new RuntimeException('boom')))->toThrow(RuntimeException::class, 'boom');

    expect($this->recorder->isRecording())->toBeFalse();
});

it('rejects closing an unknown scope', function (): void {
    $this->recorder->stop(999);
})->throws(LogicException::class, 'Unknown or already closed');

it('flushes every open scope', function (): void {
    $this->recorder->start();
    $this->recorder->start();

    $this->recorder->flush();

    expect($this->recorder->isRecording())->toBeFalse();
});

it('records queries from several connections separately labelled', function (): void {
    Schema::connection('secondary')->create('things', function ($table): void {
        $table->id();
    });

    $log = $this->recorder->record(function (): void {
        DB::connection('testing')->select('select 1');
        DB::connection('secondary')->select('select * from things');
    });

    expect(array_map(fn ($q) => $q->connection, $log->all()))->toBe(['testing', 'secondary']);
});

it('ignores connections listed in ignore_connections', function (): void {
    config(['query-budget.ignore_connections' => ['secondary']]);

    $log = $this->recorder->record(function (): void {
        DB::connection('testing')->select('select 1');
        DB::connection('secondary')->select('select 2');
    });

    expect($log)->toHaveCount(1)->and($log->all()[0]->connection)->toBe('testing');
});

it('ignores queries matching ignore_sql_patterns', function (): void {
    config(['query-budget.ignore_sql_patterns' => ['/\bfrom "?sessions"?\b/i', '/^select \?$/']]);

    $log = $this->recorder->record(function (): void {
        DB::select('select 1');
        DB::select('select * from sqlite_master');
        DB::statement('create table sessions (id integer)');
        DB::select('select * from "sessions" where id = ?', [1]);
    });

    expect(array_map(fn ($q) => $q->sql, $log->all()))->toBe(['select * from sqlite_master', 'create table sessions (id integer)']);
});

it('skips ignored caller paths when resolving callers', function (): void {
    config(['query-budget.ignored_caller_paths' => [__DIR__]]);

    $log = $this->recorder->record(fn () => DB::select('select 1'));

    expect($log->all()[0]->caller)->toBe(CallerResolver::UNKNOWN);
});

it('attributes eloquent lazy loading to the application line', function (): void {
    $this->seedBlog(2, 0);

    $log = $this->recorder->record(function (): void {
        foreach (Post::all() as $post) {
            $post->author;
        }
    });

    $callers = array_unique(array_map(fn ($q) => $q->caller, array_slice($log->all(), 1)));

    expect($callers)->toHaveCount(1)
        ->and(array_values($callers)[0])->toStartWith('tests/Feature/QueryRecorderTest.php:')
        ->and(Author::query()->count())->toBe(2);
});

it('uses the settings that were active when the outermost scope opened', function (): void {
    $token = $this->recorder->start();
    config(['query-budget.ignore_connections' => ['testing']]);

    DB::select('select 1');
    $log = $this->recorder->stop($token);

    expect($log)->toHaveCount(1);
});

it('does not retain queries once a scope is closed', function (): void {
    $run = function (): void {
        for ($scope = 0; $scope < 20; $scope++) {
            $this->recorder->record(function (): void {
                for ($i = 0; $i < 100; $i++) {
                    DB::select('select 1');
                }
            });
        }
    };

    $run();
    gc_collect_cycles();
    $before = memory_get_usage();

    $run();
    gc_collect_cycles();

    expect(memory_get_usage() - $before)->toBeLessThan(256 * 1024)
        ->and($this->recorder->isRecording())->toBeFalse();
});

it('does no work for events that arrive while no scope is open', function (): void {
    $connection = DB::connection();
    $event = new QueryExecuted("select * from t where a = 'secret'", ['secret'], 1.0, $connection);

    $this->recorder->handle($event);

    $token = $this->recorder->start();
    $log = $this->recorder->stop($token);

    expect($log)->toHaveCount(0);
});

it('treats a missing execution time as zero', function (): void {
    $event = new QueryExecuted('select 1', [], null, DB::connection());

    $token = $this->recorder->start();
    $this->recorder->handle($event);
    $log = $this->recorder->stop($token);

    expect($log->all()[0]->timeMs)->toBe(0.0);
});
