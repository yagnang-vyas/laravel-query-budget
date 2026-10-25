<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Testing;

use ChakraCoder\QueryBudget\Analysis\BudgetReport;
use ChakraCoder\QueryBudget\Exceptions\QueryBudgetExpectationFailed;
use ChakraCoder\QueryBudget\Facades\QueryBudget;
use Closure;
use InvalidArgumentException;
use Pest\Expectation;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Registers the query budget expectations when Pest is present:
 *
 *   expect(fn () => $this->get('/posts'))->toRunAtMostQueries(5);
 *   expect(fn () => $this->get('/posts'))->toRunExactlyQueries(2);
 *   expect(fn () => $this->get('/posts'))->not->toHaveDuplicateQueries();
 *   expect(fn () => $this->get('/posts'))->not->toHaveNPlusOne();
 */
final class PestExpectations
{
    /**
     * Pest rebinds the extension closures below to its Expectation, whose public `$value` holds the
     * subject. Declared here so static analysis resolves `$this->value` inside those closures
     * on every supported Pest version.
     */
    public mixed $value = null;

    private static bool $registered = false;

    public static function isAvailable(): bool
    {
        return function_exists('expect') && class_exists(Expectation::class);
    }

    public function register(): void
    {
        if (self::$registered || ! self::isAvailable()) {
            return;
        }

        self::$registered = true;

        expect()->extend('toRunAtMostQueries', function (int $max): mixed {
            $failure = QueryBudgetChecks::atMost(PestExpectations::measure($this->value), $max);

            return PestExpectations::requirePass($this, $failure);
        });

        expect()->extend('toRunExactlyQueries', function (int $expected): mixed {
            $failure = QueryBudgetChecks::exactly(PestExpectations::measure($this->value), $expected);

            return PestExpectations::requirePass($this, $failure);
        });

        expect()->extend('toHaveDuplicateQueries', function (): mixed {
            return PestExpectations::requireFinding($this, PestExpectations::measure($this->value), 'duplicate queries', QueryBudgetChecks::noDuplicates(...));
        });

        expect()->extend('toHaveNPlusOne', function (): mixed {
            return PestExpectations::requireFinding($this, PestExpectations::measure($this->value), 'an N+1 query pattern', QueryBudgetChecks::noNPlusOne(...));
        });
    }

    /**
     * @internal
     */
    public static function measure(mixed $callback): BudgetReport
    {
        if (! $callback instanceof Closure) {
            throw new InvalidArgumentException('Query budget expectations require a closure: expect(fn () => ...).');
        }

        return QueryBudget::measure($callback);
    }

    /**
     * @internal
     */
    public static function requirePass(mixed $expectation, ?string $failure): mixed
    {
        if ($failure !== null) {
            throw new ExpectationFailedException($failure);
        }

        QueryBudgetChecks::countAssertion();

        return $expectation;
    }

    /**
     * Positive form: passes when the report contains the finding. Negated form: Pest calls the
     * positive form, swallows any assertion failure and replaces a real failure with a generic message,
     * so the detailed report is raised from here as a QueryBudgetExpectationFailed instead.
     *
     * @param  Closure(BudgetReport): ?string  $problem  returns the failure message of the matching "no ..." check
     *
     * @internal
     */
    public static function requireFinding(mixed $expectation, BudgetReport $report, string $what, Closure $problem): mixed
    {
        $message = $problem($report);

        // Counted up front: the negated pass path ends in a swallowed exception and would otherwise register no assertion.
        QueryBudgetChecks::countAssertion();

        if ($message === null) {
            throw new ExpectationFailedException(sprintf("Failed asserting that the closure ran %s.\n\n%s", $what, $report));
        }

        if (self::calledThroughNegation()) {
            throw new QueryBudgetExpectationFailed($message);
        }

        return $expectation;
    }

    private static function calledThroughNegation(): bool
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (isset($frame['class']) && str_ends_with($frame['class'], 'OppositeExpectation')) {
                return true;
            }
        }

        return false;
    }
}
