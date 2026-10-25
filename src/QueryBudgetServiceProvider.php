<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget;

use ChakraCoder\QueryBudget\Support\Settings;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use LogicException;

final class QueryBudgetServiceProvider extends ServiceProvider
{
    public const MIDDLEWARE_ALIAS = 'query.budget';

    public const CONFIG_TAG = 'query-budget-config';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/query-budget.php', 'query-budget');
    }

    public function boot(): void
    {
        // Fail early and clearly when the configuration has the wrong shape.
        Settings::fromRepository($this->resolve(Repository::class));

        $this->publishes([
            __DIR__.'/../config/query-budget.php' => $this->app->configPath('query-budget.php'),
        ], self::CONFIG_TAG);
    }

    /**
     * @template TService of object
     *
     * @param  class-string<TService>  $abstract
     * @return TService
     */
    private function resolve(string $abstract): object
    {
        $service = $this->app->make($abstract);

        if (! $service instanceof $abstract) {
            throw new LogicException(sprintf('Unable to resolve %s from the container.', $abstract));
        }

        return $service;
    }
}
