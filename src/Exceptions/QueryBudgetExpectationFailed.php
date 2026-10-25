<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Exceptions;

use RuntimeException;

/**
 * Raised by negated Pest expectations (`->not->toHaveNPlusOne()`).
 *
 * Pest turns any assertion failure thrown inside a negated expectation into a pass and replaces
 * the message of a real failure with a generic one. A plain exception is the only way to surface
 * the detailed report, so the test is reported as an error that carries it.
 */
final class QueryBudgetExpectationFailed extends RuntimeException {}
