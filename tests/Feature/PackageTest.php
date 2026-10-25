<?php

declare(strict_types=1);

use ChakraCoder\QueryBudget\Facades\QueryBudget;
use ChakraCoder\QueryBudget\Http\Middleware\EnforceQueryBudget;
use ChakraCoder\QueryBudget\QueryBudgetManager;
use ChakraCoder\QueryBudget\Recording\QueryRecorder;
use ChakraCoder\QueryBudget\Tests\Fixtures\Models\Comment;
use Illuminate\Routing\Router;

it('registers the middleware alias', function (): void {
    expect($this->app->make(Router::class)->getMiddleware())
        ->toHaveKey('query.budget', EnforceQueryBudget::class);
});

it('uses a single recorder instance', function (): void {
    expect($this->app->make(QueryRecorder::class))->toBe($this->app->make(QueryRecorder::class))
        ->and($this->app->make(QueryBudgetManager::class)->recorder())->toBe($this->app->make(QueryRecorder::class));
});

it('exposes the manager through the facade', function (): void {
    $this->seedBlog(1, 3);

    $report = QueryBudget::measure(fn () => Comment::count());

    expect($report->totalCount)->toBe(1)
        ->and(QueryBudget::settings()->mode->value)->toBe('throw')
        ->and(QueryBudget::recorder()->isRecording())->toBeFalse()
        ->and(QueryBudget::report(QueryBudget::recorder()->record(fn () => Comment::count()))->totalCount)->toBe(1);
});

it('reads configuration lazily so test overrides take effect', function (): void {
    config(['query-budget.duplicate_threshold' => 5]);

    expect(QueryBudget::settings()->duplicateThreshold)->toBe(5);
});
