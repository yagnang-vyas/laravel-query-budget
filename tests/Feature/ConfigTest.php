<?php

declare(strict_types=1);

use ChakraCoder\QueryBudget\QueryBudgetServiceProvider;
use Illuminate\Support\ServiceProvider;

it('publishes the config file under the query-budget-config tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(QueryBudgetServiceProvider::class, 'query-budget-config');

    expect($paths)->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith('config/query-budget.php')
        ->and(array_key_first($paths))->toBeFile()
        ->and(array_values($paths)[0])->toEndWith('config/query-budget.php');
});

it('merges the default configuration', function (): void {
    expect(config('query-budget.enabled_environments'))->toBe(['local', 'testing'])
        ->and(config('query-budget.mode'))->toBe('throw')
        ->and(config('query-budget.n_plus_one_threshold'))->toBe(3);
});

it('validates the configuration when booting', function (): void {
    config(['query-budget.mode' => 'explode']);

    (new QueryBudgetServiceProvider($this->app))->boot();
})->throws(InvalidArgumentException::class, 'query-budget.mode must be one of');
