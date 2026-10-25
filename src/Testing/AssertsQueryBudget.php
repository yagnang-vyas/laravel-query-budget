<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Testing;

use ChakraCoder\QueryBudget\Analysis\BudgetReport;
use ChakraCoder\QueryBudget\QueryBudgetManager;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use LogicException;
use PHPUnit\Framework\Assert;

/**
 * Query budget assertions for Laravel feature tests (PHPUnit and Pest).
 *
 * Use in a test case that boots the application, for example
 * `uses(AssertsQueryBudget::class)` in Pest or `use AssertsQueryBudget;` in PHPUnit.
 *
 * @property Application $app
 */
trait AssertsQueryBudget
{
    private ?int $queryBudgetToken = null;

    private ?int $queryBudgetLimit = null;

    /**
     * Run the callback and return a report on the queries it executed.
     */
    protected function recordQueries(Closure $callback): BudgetReport
    {
        return $this->queryBudgetManager()->measure($callback);
    }

    protected function assertQueryCountAtMost(int $max, Closure $callback, string $message = ''): BudgetReport
    {
        $report = $this->recordQueries($callback);

        $this->assertQueryBudgetCheck(QueryBudgetChecks::atMost($report, $max), $message);

        return $report;
    }

    protected function assertQueryCountExactly(int $expected, Closure $callback, string $message = ''): BudgetReport
    {
        $report = $this->recordQueries($callback);

        $this->assertQueryBudgetCheck(QueryBudgetChecks::exactly($report, $expected), $message);

        return $report;
    }

    protected function assertNoDuplicateQueries(Closure $callback, string $message = ''): BudgetReport
    {
        $report = $this->recordQueries($callback);

        $this->assertQueryBudgetCheck(QueryBudgetChecks::noDuplicates($report), $message);

        return $report;
    }

    protected function assertNoNPlusOne(Closure $callback, string $message = ''): BudgetReport
    {
        $report = $this->recordQueries($callback);

        $this->assertQueryBudgetCheck(QueryBudgetChecks::noNPlusOne($report), $message);

        return $report;
    }

    /**
     * Limit the number of queries for the rest of the test. Checked when the test is torn down.
     * Calling it again replaces the previous budget.
     */
    protected function expectQueryBudget(int $max): void
    {
        $recorder = $this->queryBudgetManager()->recorder();

        if ($this->queryBudgetLimit === null) {
            $this->beforeApplicationDestroyed(function (): void {
                $token = $this->queryBudgetToken;
                $limit = $this->queryBudgetLimit;

                if ($token === null || $limit === null) {
                    return;
                }

                $this->queryBudgetToken = null;
                $manager = $this->queryBudgetManager();
                $report = $manager->report($manager->recorder()->stop($token));

                $this->assertQueryBudgetCheck(QueryBudgetChecks::atMost($report, $limit), '');
            });
        } elseif ($this->queryBudgetToken !== null) {
            $recorder->stop($this->queryBudgetToken);
        }

        $this->queryBudgetLimit = $max;
        $this->queryBudgetToken = $recorder->start();
    }

    private function queryBudgetManager(): QueryBudgetManager
    {
        $manager = $this->app->make(QueryBudgetManager::class);

        if (! $manager instanceof QueryBudgetManager) {
            throw new LogicException('The query budget manager is not bound in the container. Is the service provider registered?');
        }

        return $manager;
    }

    private function assertQueryBudgetCheck(?string $failure, string $message): void
    {
        if ($failure === null) {
            QueryBudgetChecks::countAssertion();

            return;
        }

        Assert::fail($message === '' ? $failure : $message."\n".$failure);
    }
}
