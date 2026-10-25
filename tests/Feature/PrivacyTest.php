<?php

declare(strict_types=1);

use ChakraCoder\QueryBudget\Exceptions\QueryBudgetExceeded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\AssertionFailedError;

it('never exposes binding values or inline literals in reports', function (): void {
    $report = $this->recordQueries(function (): void {
        foreach (['alice@example.com', 'bob@example.com', 'carol@example.com'] as $email) {
            DB::select('select * from authors where email = ?', [$email]);
        }

        DB::select("select * from authors where email = 'dave@example.com' and id = 987654");
    });

    $text = (string) $report;

    expect($text)
        ->toContain('select * from authors where email = ?')
        ->not->toContain('example.com')
        ->not->toContain('alice')
        ->not->toContain('987654');
});

it('never exposes binding values in assertion failures', function (): void {
    try {
        $this->assertNoNPlusOne(function (): void {
            foreach (['alice@example.com', 'bob@example.com', 'carol@example.com'] as $email) {
                DB::select('select * from authors where email = ?', [$email]);
            }
        });
        $this->fail('Expected an N+1 failure.');
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toContain('Possible N+1 queries')->not->toContain('example.com');
    }
});

it('never exposes binding values in the middleware exception', function (): void {
    Route::get('/lookup', function () {
        foreach (['alice@example.com', 'bob@example.com', 'carol@example.com'] as $email) {
            DB::select('select * from authors where email = ?', [$email]);
        }

        return 'ok';
    })->middleware('query.budget:1');

    try {
        $this->withoutExceptionHandling()->get('/lookup');
        $this->fail('Expected the budget to be exceeded.');
    } catch (QueryBudgetExceeded $e) {
        expect($e->getMessage())->not->toContain('example.com')
            ->and((string) $e->report())->not->toContain('example.com');
    }
});

it('never exposes binding values in the rendered 500 page', function (): void {
    config(['app.debug' => true]);

    Route::get('/lookup-rendered', function () {
        foreach (['alice@example.com', 'bob@example.com', 'carol@example.com'] as $email) {
            DB::select('select * from authors where email = ?', [$email]);
        }

        return 'ok';
    })->middleware('query.budget:1');

    $response = $this->get('/lookup-rendered')->assertStatus(500);

    expect($response->getContent())->toContain('select * from authors where email = ?')->not->toContain('example.com');
});
