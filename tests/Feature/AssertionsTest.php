<?php

declare(strict_types=1);

use ChakraCoder\QueryBudget\Analysis\BudgetReport;
use ChakraCoder\QueryBudget\Testing\AssertsQueryBudget;
use ChakraCoder\QueryBudget\Tests\Fixtures\Models\Comment;
use ChakraCoder\QueryBudget\Tests\Fixtures\Models\Post;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

beforeEach(function (): void {
    $this->seedBlog(3, 2);
});

function failureOf(Closure $assertion): string
{
    try {
        $assertion();
    } catch (AssertionFailedError $e) {
        return $e->getMessage();
    }

    throw new RuntimeException('The assertion was expected to fail but passed.');
}

it('passes every assertion on the eager route', function (): void {
    $this->assertQueryCountAtMost(2, fn () => $this->get('/posts-eager')->assertOk());
    $this->assertQueryCountExactly(2, fn () => $this->get('/posts-eager'));
    $this->assertNoDuplicateQueries(fn () => $this->get('/posts-eager'));
    $this->assertNoNPlusOne(fn () => $this->get('/posts-eager'));
});

it('returns the report from assertions', function (): void {
    $report = $this->assertQueryCountAtMost(10, fn () => Post::with('comments')->get());

    expect($report)->toBeInstanceOf(BudgetReport::class)->and($report->totalCount)->toBe(2);
});

it('fails the count assertions on the lazy route with the report in the message', function (): void {
    $message = failureOf(fn () => $this->assertQueryCountAtMost(2, fn () => $this->get('/posts-lazy')));

    expect($message)
        ->toContain('Failed asserting that at most 2 queries were executed, but 4 ran.')
        ->toContain('Query budget report: 4 queries executed.')
        ->toContain('select * from "posts"')
        ->toContain('3x select * from "authors" where "authors"."id" = ? limit ?')
        ->toContain('tests/Fixtures/Controllers/PostController.php:');

    $exactly = failureOf(fn () => $this->assertQueryCountExactly(2, fn () => $this->get('/posts-lazy')));
    expect($exactly)->toContain('Failed asserting that exactly 2 queries were executed, but 4 ran.');
});

it('uses singular wording for one query', function (): void {
    $message = failureOf(fn () => $this->assertQueryCountAtMost(1, fn () => Post::query()->get()->each(fn (Post $post) => $post->author)));

    expect($message)->toContain('at most 1 query was executed');
});

it('fails assertNoNPlusOne on the lazy route and names the caller line', function (): void {
    $message = failureOf(fn () => $this->assertNoNPlusOne(fn () => $this->get('/posts-lazy')));

    expect($message)
        ->toContain('Failed asserting that no N+1 query pattern occurred, but 1 pattern was found.')
        ->toContain('Possible N+1 queries (1):')
        ->toContain('3x select * from "authors" where "authors"."id" = ? limit ?')
        ->toMatch('#from tests/Fixtures/Controllers/PostController\.php:\d+#');
});

it('fails assertNoDuplicateQueries for repeated count queries that are not lazy loading', function (): void {
    $message = failureOf(fn () => $this->assertNoDuplicateQueries(fn () => $this->get('/posts-duplicates')));

    expect($message)
        ->toContain('Failed asserting that no duplicate queries were executed, but 1 repeated statement was found.')
        ->toMatch('/3x select count\(\*\) as "?aggregate"? from "comments"/');
});

it('prefixes a custom message', function (): void {
    $message = failureOf(fn () => $this->assertNoDuplicateQueries(fn () => $this->get('/posts-duplicates'), 'Home page regressed.'));

    expect($message)->toStartWith("Home page regressed.\nFailed asserting that no duplicate queries");
});

it('returns a report from recordQueries', function (): void {
    $report = $this->recordQueries(fn () => Comment::count());

    expect($report->totalCount)->toBe(1)->and($report->hasDuplicates())->toBeFalse();
});

it('uses the configured thresholds', function (): void {
    config(['query-budget.duplicate_threshold' => 3, 'query-budget.n_plus_one_threshold' => 4]);

    $this->assertNoDuplicateQueries(fn () => $this->get('/posts-lazy'));
    $this->assertNoNPlusOne(fn () => $this->get('/posts-lazy'));
});

/**
 * expectQueryBudget() is verified in tearDown, so drive a minimal test case through its lifecycle by hand.
 */
function budgetedCase(object $app, int $budget, int $queries, ?int $replacement = null): object
{
    $case = new class('scenario') extends TestCase
    {
        use AssertsQueryBudget;

        public object $app;

        public int $budget = 0;

        public int $queries = 0;

        public ?int $replacement = null;

        /** @var list<Closure> */
        private array $teardownCallbacks = [];

        public function beforeApplicationDestroyed(Closure $callback): void
        {
            $this->teardownCallbacks[] = $callback;
        }

        public function scenario(): void
        {
            try {
                $this->expectQueryBudget($this->budget);

                if ($this->replacement !== null) {
                    $this->expectQueryBudget($this->replacement);
                }

                for ($i = 0; $i < $this->queries; $i++) {
                    DB::select('select '.$i);
                }
            } finally {
                foreach ($this->teardownCallbacks as $callback) {
                    $callback();
                }
            }
        }
    };

    $case->app = $app;
    $case->budget = $budget;
    $case->queries = $queries;
    $case->replacement = $replacement;

    return $case;
}

it('passes expectQueryBudget when the whole test stays within budget', function (): void {
    budgetedCase($this->app, 3, 3)->scenario();

    expect(true)->toBeTrue();
});

it('fails expectQueryBudget in tearDown when the test exceeds the budget', function (): void {
    expect(fn () => budgetedCase($this->app, 2, 3)->scenario())
        ->toThrow(AssertionFailedError::class, 'at most 2 queries were executed, but 3 ran.');
});

it('lets a later expectQueryBudget call replace the earlier budget', function (): void {
    budgetedCase($this->app, 1, 3, 5)->scenario();

    expect(fn () => budgetedCase($this->app, 5, 3, 2)->scenario())->toThrow(AssertionFailedError::class, 'at most 2 queries');
});
