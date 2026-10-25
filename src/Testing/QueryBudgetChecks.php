<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Testing;

use ChakraCoder\QueryBudget\Analysis\BudgetReport;
use PHPUnit\Framework\Assert;

/**
 * Shared pass/fail logic and failure messages for the PHPUnit trait and the Pest expectations.
 * Every method returns null on success or the failure message, which always embeds the report.
 */
final class QueryBudgetChecks
{
    /**
     * Register a passed check with PHPUnit so the test is not reported as risky.
     */
    public static function countAssertion(): void
    {
        Assert::assertThat(true, Assert::isTrue());
    }

    public static function atMost(BudgetReport $report, int $max): ?string
    {
        if ($report->totalCount <= $max) {
            return null;
        }

        return sprintf(
            "Failed asserting that at most %d %s executed, but %d ran.\n\n%s",
            $max,
            $max === 1 ? 'query was' : 'queries were',
            $report->totalCount,
            $report,
        );
    }

    public static function exactly(BudgetReport $report, int $expected): ?string
    {
        if ($report->totalCount === $expected) {
            return null;
        }

        return sprintf(
            "Failed asserting that exactly %d %s executed, but %d ran.\n\n%s",
            $expected,
            $expected === 1 ? 'query was' : 'queries were',
            $report->totalCount,
            $report,
        );
    }

    public static function noDuplicates(BudgetReport $report): ?string
    {
        if (! $report->hasDuplicates()) {
            return null;
        }

        return sprintf(
            "Failed asserting that no duplicate queries were executed, but %d repeated %s found.\n\n%s",
            count($report->duplicates),
            count($report->duplicates) === 1 ? 'statement was' : 'statements were',
            $report,
        );
    }

    public static function noNPlusOne(BudgetReport $report): ?string
    {
        if (! $report->hasNPlusOne()) {
            return null;
        }

        return sprintf(
            "Failed asserting that no N+1 query pattern occurred, but %d %s found.\n\n%s",
            count($report->nPlusOne),
            count($report->nPlusOne) === 1 ? 'pattern was' : 'patterns were',
            $report,
        );
    }
}
