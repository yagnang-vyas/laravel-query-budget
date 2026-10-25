<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Facades;

use ChakraCoder\QueryBudget\Analysis\BudgetReport;
use ChakraCoder\QueryBudget\QueryBudgetManager;
use ChakraCoder\QueryBudget\Recording\QueryLog;
use ChakraCoder\QueryBudget\Recording\QueryRecorder;
use ChakraCoder\QueryBudget\Support\Settings;
use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static BudgetReport measure(Closure $callback)
 * @method static BudgetReport report(QueryLog $log)
 * @method static QueryRecorder recorder()
 * @method static Settings settings()
 *
 * @see QueryBudgetManager
 */
final class QueryBudget extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return QueryBudgetManager::class;
    }
}
