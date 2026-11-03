<?php

declare(strict_types=1);

namespace YagnangVyas\QueryBudget;

use Closure;
use Illuminate\Contracts\Config\Repository;
use YagnangVyas\QueryBudget\Analysis\BudgetReport;
use YagnangVyas\QueryBudget\Recording\QueryLog;
use YagnangVyas\QueryBudget\Recording\QueryRecorder;
use YagnangVyas\QueryBudget\Support\Settings;

/**
 * Entry point behind the QueryBudget facade: record a callback and analyse the result
 * using the thresholds from config/query-budget.php.
 */
final class QueryBudgetManager
{
    public function __construct(
        private readonly QueryRecorder $recorder,
        private readonly Repository $config,
    ) {}

    public function recorder(): QueryRecorder
    {
        return $this->recorder;
    }

    public function settings(): Settings
    {
        return Settings::fromRepository($this->config);
    }

    /**
     * Run the callback and report on the queries it executed.
     */
    public function measure(Closure $callback): BudgetReport
    {
        return $this->report($this->recorder->record($callback));
    }

    public function report(QueryLog $log): BudgetReport
    {
        $settings = $this->settings();

        return BudgetReport::fromLog($log, $settings->duplicateThreshold, $settings->nPlusOneThreshold);
    }
}
