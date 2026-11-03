<?php

declare(strict_types=1);

namespace YagnangVyas\QueryBudget;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use LogicException;
use YagnangVyas\QueryBudget\Http\Middleware\EnforceQueryBudget;
use YagnangVyas\QueryBudget\Recording\CallerResolver;
use YagnangVyas\QueryBudget\Recording\QueryRecorder;
use YagnangVyas\QueryBudget\Recording\SqlNormalizer;
use YagnangVyas\QueryBudget\Support\Settings;
use YagnangVyas\QueryBudget\Testing\PestExpectations;

final class QueryBudgetServiceProvider extends ServiceProvider
{
    public const MIDDLEWARE_ALIAS = 'query.budget';

    public const CONFIG_TAG = 'query-budget-config';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/query-budget.php', 'query-budget');

        $this->app->singleton(SqlNormalizer::class);

        $this->app->singleton(CallerResolver::class, fn (Application $app): CallerResolver => new CallerResolver($app->basePath()));

        $this->app->singleton(QueryRecorder::class, fn (): QueryRecorder => new QueryRecorder(
            $this->resolve(SqlNormalizer::class),
            $this->resolve(CallerResolver::class),
            $this->resolve(Repository::class),
        ));

        $this->app->singleton(QueryBudgetManager::class, fn (): QueryBudgetManager => new QueryBudgetManager(
            $this->resolve(QueryRecorder::class),
            $this->resolve(Repository::class),
        ));
    }

    public function boot(): void
    {
        // Fail early and clearly when the configuration has the wrong shape.
        Settings::fromRepository($this->resolve(Repository::class));

        $this->publishes([
            __DIR__.'/../config/query-budget.php' => $this->app->configPath('query-budget.php'),
        ], self::CONFIG_TAG);

        $this->resolve(Router::class)->aliasMiddleware(self::MIDDLEWARE_ALIAS, EnforceQueryBudget::class);

        $recorder = $this->resolve(QueryRecorder::class);

        $this->resolve(Dispatcher::class)->listen(
            QueryExecuted::class,
            static function (QueryExecuted $event) use ($recorder): void {
                $recorder->handle($event);
            },
        );

        (new PestExpectations)->register();
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
