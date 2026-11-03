<?php

declare(strict_types=1);

namespace YagnangVyas\QueryBudget\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use YagnangVyas\QueryBudget\Analysis\BudgetReport;
use YagnangVyas\QueryBudget\QueryBudgetManager;
use YagnangVyas\QueryBudget\Recording\QueryLog;
use YagnangVyas\QueryBudget\Recording\QueryRecorder;
use YagnangVyas\QueryBudget\Support\Settings;

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
