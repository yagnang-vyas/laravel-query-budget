<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Support;

enum EnforcementMode: string
{
    case Throw = 'throw';
    case Log = 'log';
    case Header = 'header';
}
