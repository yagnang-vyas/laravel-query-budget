<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Http\Middleware;

use ChakraCoder\QueryBudget\Analysis\BudgetReport;
use ChakraCoder\QueryBudget\Exceptions\QueryBudgetExceeded;
use ChakraCoder\QueryBudget\QueryBudgetManager;
use ChakraCoder\QueryBudget\Support\EnforcementMode;
use ChakraCoder\QueryBudget\Support\Settings;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Log\LogManager;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces a query budget for a request: `->middleware('query.budget:20')`.
 *
 * The middleware is a pass-through unless "enabled" is true and the application
 * environment is listed in "enabled_environments".
 */
final class EnforceQueryBudget
{
    public function __construct(
        private readonly QueryBudgetManager $manager,
        private readonly Repository $config,
        private readonly Application $app,
        private readonly LogManager $log,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $budget = null): Response
    {
        $settings = Settings::fromRepository($this->config);

        if (! $this->isActive($settings)) {
            return $next($request);
        }

        $limit = $this->parseBudget($budget) ?? $settings->defaultBudget;
        $recorder = $this->manager->recorder();
        $token = $recorder->start();

        try {
            $response = $next($request);
        } finally {
            $log = $recorder->stop($token);
        }

        $report = BudgetReport::fromLog($log, $settings->duplicateThreshold, $settings->nPlusOneThreshold);
        $violations = $this->violations($report, $limit, $settings);
        $subject = $this->subject($request);

        switch ($settings->mode) {
            case EnforcementMode::Throw:
                if ($violations !== []) {
                    throw QueryBudgetExceeded::fromReport($report, $violations, $subject);
                }
                break;

            case EnforcementMode::Log:
                if ($violations !== []) {
                    $this->log->channel($settings->logChannel)->warning(
                        sprintf("Query budget exceeded for %s: %s\n\n%s", $subject, implode('; ', $violations), $report),
                    );
                }
                break;

            case EnforcementMode::Header:
                $response->headers->set('X-Query-Count', (string) $report->totalCount);

                if ($violations !== []) {
                    $response->headers->set('X-Query-Budget-Exceeded', '1');
                }
                break;
        }

        return $response;
    }

    private function isActive(Settings $settings): bool
    {
        return $settings->enabled
            && in_array($this->app->environment(), $settings->enabledEnvironments, true);
    }

    private function parseBudget(?string $budget): ?int
    {
        if ($budget === null || $budget === '') {
            return null;
        }

        if (! ctype_digit($budget)) {
            throw new InvalidArgumentException(sprintf('The query.budget middleware parameter must be a non-negative integer, "%s" given.', $budget));
        }

        return (int) $budget;
    }

    /**
     * @return list<string>
     */
    private function violations(BudgetReport $report, ?int $limit, Settings $settings): array
    {
        $violations = [];

        if ($limit !== null && $report->totalCount > $limit) {
            $violations[] = sprintf('%d queries executed, budget is %d', $report->totalCount, $limit);
        }

        if ($settings->detectDuplicates && $report->hasDuplicates()) {
            $violations[] = sprintf('%d duplicate %s', count($report->duplicates), count($report->duplicates) === 1 ? 'statement' : 'statements');
        }

        if ($settings->detectNPlusOne && $report->hasNPlusOne()) {
            $violations[] = sprintf('%d possible N+1 %s', count($report->nPlusOne), count($report->nPlusOne) === 1 ? 'pattern' : 'patterns');
        }

        return $violations;
    }

    /**
     * Route pattern only: concrete URLs may contain personal data.
     */
    private function subject(Request $request): string
    {
        $uri = $request->route()?->uri();

        return is_string($uri)
            ? sprintf('%s /%s', $request->method(), ltrim($uri, '/'))
            : sprintf('%s (unmatched route)', $request->method());
    }
}
