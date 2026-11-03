<?php

declare(strict_types=1);

namespace YagnangVyas\QueryBudget\Tests\Fixtures\Support;

use YagnangVyas\QueryBudget\Testing\AssertsQueryBudget;
use YagnangVyas\QueryBudget\Tests\TestCase;

/**
 * The shape of test case a user of the package would write. Also lets PHPStan analyse the trait.
 */
abstract class BudgetAssertingTestCase extends TestCase
{
    use AssertsQueryBudget;
}
