<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Tests\Fixtures\Support;

use ChakraCoder\QueryBudget\Testing\AssertsQueryBudget;
use ChakraCoder\QueryBudget\Tests\TestCase;

/**
 * The shape of test case a user of the package would write. Also lets PHPStan analyse the trait.
 */
abstract class BudgetAssertingTestCase extends TestCase
{
    use AssertsQueryBudget;
}
