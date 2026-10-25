<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Exceptions;

use ChakraCoder\QueryBudget\Analysis\BudgetReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

final class QueryBudgetExceeded extends RuntimeException
{
    /**
     * @param  list<string>  $violations
     */
    private function __construct(
        string $message,
        private readonly BudgetReport $budgetReport,
        private readonly array $violations,
    ) {
        parent::__construct($message);
    }

    /**
     * @param  list<string>  $violations  human readable descriptions of what was violated
     */
    public static function fromReport(BudgetReport $report, array $violations, string $subject = 'request'): self
    {
        return new self(
            sprintf("Query budget exceeded for this %s: %s\n\n%s", $subject, implode('; ', $violations), $report),
            $report,
            $violations,
        );
    }

    public function report(): BudgetReport
    {
        return $this->budgetReport;
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    /**
     * Render as a 500. The report is only shown when the application is in debug mode.
     */
    public function render(Request $request): Response
    {
        $debug = (bool) config('app.debug');
        $body = $debug ? $this->getMessage() : 'Server Error';

        return new Response($body, 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
