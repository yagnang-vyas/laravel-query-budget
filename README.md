# Laravel Query Budget

Make database query counts, duplicate queries and N+1 patterns **assertable in tests** and **enforceable per route** in local development and CI.

N+1 queries pass code review because nothing fails. They show up in production, under load. Laravel Query Budget turns query efficiency into something your test suite and your routes can check, with reports that tell you *which* statement repeated and *where*.

```php
it('lists posts without an N+1', function () {
    expect(fn () => $this->get('/posts'))
        ->toRunAtMostQueries(5)
        ->not->toHaveNPlusOne();
});
```

- Assertions for PHPUnit and Pest, plus Pest expectations
- A `query.budget` route middleware with `throw`, `log` and `header` modes
- Reports with normalized SQL, counts and caller `file:line`
- **Binding values are never recorded or printed**, so reports are safe to paste into CI logs and issues
- Disabled outside `local` and `testing` by default, so it cannot throw in production

> Requires PHP 8.2+ and Laravel 12 or 13 (Laravel 13 needs PHP 8.3+).

## Installation

The package is not on Packagist yet. Until it is, install it from the Git repository by adding a VCS repository entry to your application's `composer.json`:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/yagnang-vyas/laravel-query-budget" }
    ]
}
```

```bash
composer require --dev chakracoder/laravel-query-budget
```

The service provider and the `QueryBudget` facade are auto-discovered. If you use the middleware outside `local` and `testing`, require the package without `--dev`.

Publish the configuration if you want to change it:

```bash
php artisan vendor:publish --tag=query-budget-config
```

## Quick start

Add the trait to a test case (in Pest, `uses(AssertsQueryBudget::class)->in('Feature')` in `tests/Pest.php`):

```php
use ChakraCoder\QueryBudget\Testing\AssertsQueryBudget;

class PostIndexTest extends TestCase
{
    use AssertsQueryBudget;

    public function test_the_index_does_not_run_an_n_plus_one(): void
    {
        Post::factory()->count(3)->create();

        $this->assertNoNPlusOne(fn () => $this->get('/posts'));
        $this->assertQueryCountAtMost(2, fn () => $this->get('/posts'));
    }
}
```

When the route lazy-loads a relation inside a loop, the failure tells you what and where. This is the real output of `assertNoNPlusOne` against the package's own lazy fixture route (`tests/Fixtures/Controllers/PostController.php`, three posts with one author each):

```text
Failed asserting that no N+1 query pattern occurred, but 1 pattern was found.

Query budget report: 4 queries executed.

Duplicate queries (1):
  3x select * from "authors" where "authors"."id" = ? limit ?
       3x from tests/Fixtures/Controllers/PostController.php:22

Possible N+1 queries (1):
  3x select * from "authors" where "authors"."id" = ? limit ?
       from tests/Fixtures/Controllers/PostController.php:22

Slowest queries:
  1. 0.09 ms  select * from "posts"
  2. 0.07 ms  select * from "authors" where "authors"."id" = ? limit ?
  3. 0.04 ms  select * from "authors" where "authors"."id" = ? limit ?
  4. 0.03 ms  select * from "authors" where "authors"."id" = ? limit ?
```

Fix the route (`Post::with('author')->get()`), run the test again, and it passes with two queries.

## Assertions

Available through the `AssertsQueryBudget` trait. Each takes a closure, runs it while recording, and returns the `BudgetReport`. An optional last argument is prepended to the failure message.

| Method | Fails when |
|---|---|
| `assertQueryCountAtMost(int $max, Closure $callback)` | more than `$max` queries ran |
| `assertQueryCountExactly(int $expected, Closure $callback)` | the count differs |
| `assertNoDuplicateQueries(Closure $callback)` | any normalized statement ran more than `duplicate_threshold` times |
| `assertNoNPlusOne(Closure $callback)` | one statement ran `n_plus_one_threshold` or more times from the same caller line |
| `recordQueries(Closure $callback): BudgetReport` | never; returns the report so you can inspect it |
| `expectQueryBudget(int $max)` | the **whole test** ran more than `$max` queries; checked in tear down. Calling it again replaces the budget |

The closure can be anything: `fn () => $this->get('/posts')`, a service call, an Eloquent query.

## Pest expectations

Registered automatically when Pest is installed:

```php
expect(fn () => $this->get('/posts'))->toRunAtMostQueries(5);
expect(fn () => $this->get('/posts'))->toRunExactlyQueries(2);
expect(fn () => $this->get('/posts'))->not->toHaveDuplicateQueries();
expect(fn () => $this->get('/posts'))->not->toHaveNPlusOne();
```

The positive forms `toHaveDuplicateQueries()` and `toHaveNPlusOne()` exist too. Pest does not let a negated expectation carry its own failure message, so a failing `not->toHaveNPlusOne()` is reported as an *error* that contains the full report, rather than as a failure. `assertNoNPlusOne()` fails normally.

## Route middleware

Use the `query.budget` alias on a route or group, with the maximum number of queries:

```php
Route::get('/posts', [PostController::class, 'index'])->middleware('query.budget:10');
```

or add `ChakraCoder\QueryBudget\Http\Middleware\EnforceQueryBudget::class` to your global middleware and set `default_budget` in the config. Without a number the route has no count limit, but duplicate and N+1 detection still apply.

A request violates its budget when it runs more queries than allowed, or (with `detect_duplicates` / `detect_n_plus_one` enabled) repeats a statement or shows an N+1 pattern. What happens next depends on `mode`:

| Mode | Behaviour |
|---|---|
| `throw` (default) | throws `QueryBudgetExceeded`. The exception renders as a 500: the body contains the report when `APP_DEBUG` is true and just `Server Error` otherwise |
| `log` | writes a warning containing the report to `log_channel` and lets the request through |
| `header` | adds `X-Query-Count` to every response, and `X-Query-Budget-Exceeded: 1` when a budget was violated; handy in the browser's network tab |

With `APP_DEBUG=true`, the 500 body for the lazy fixture route (budget 3) is:

```text
Query budget exceeded for this GET /budgeted/lazy: 4 queries executed, budget is 3; 1 duplicate statement; 1 possible N+1 pattern

Query budget report: 4 queries executed.

Duplicate queries (1):
  3x select * from "authors" where "authors"."id" = ? limit ?
       3x from tests/Fixtures/Controllers/PostController.php:22

Possible N+1 queries (1):
  3x select * from "authors" where "authors"."id" = ? limit ?
       from tests/Fixtures/Controllers/PostController.php:22

Slowest queries:
  1. 0.09 ms  select * from "authors" where "authors"."id" = ? limit ?
  2. 0.07 ms  select * from "posts"
  3. 0.02 ms  select * from "authors" where "authors"."id" = ? limit ?
  4. 0.02 ms  select * from "authors" where "authors"."id" = ? limit ?
```

In `header` mode the same route responds with `X-Query-Count: 4` and `X-Query-Budget-Exceeded: 1`.

### Safe by default

The middleware does nothing, and records nothing, unless `enabled` is `true` **and** the application environment is listed in `enabled_environments` (default `['local', 'testing']`). In `production` (or `staging`, or any other environment) it is a pass-through even if a route declares a budget. Your feature tests run in `testing`, so budgets declared on routes are enforced in CI too.

## Configuration

`config/query-budget.php` (publish with the `query-budget-config` tag). It is validated when the application boots; a wrong type or an unknown `mode` throws an `InvalidArgumentException` naming the key.

| Key | Default | Description |
|---|---|---|
| `enabled` | `true` (`QUERY_BUDGET_ENABLED`) | Master switch for the middleware |
| `enabled_environments` | `['local', 'testing']` | Environments in which the middleware is active |
| `mode` | `'throw'` (`QUERY_BUDGET_MODE`) | `throw`, `log` or `header` |
| `default_budget` | `null` | Query limit for the middleware when it has no parameter; `null` means no count limit |
| `duplicate_threshold` | `1` | A statement is a duplicate when it ran more than this many times |
| `n_plus_one_threshold` | `3` | Same statement from the same caller line at least this many times is an N+1 (minimum 2) |
| `detect_duplicates` | `true` | Include duplicates as middleware violations |
| `detect_n_plus_one` | `true` | Include N+1 patterns as middleware violations |
| `ignore_sql_patterns` | `[]` | Regular expressions matched against the **normalized** SQL; matching queries are not recorded. For example `'/\bfrom "?sessions"?\b/i'` |
| `ignore_connections` | `[]` | Connection names that are never recorded |
| `ignored_caller_paths` | `[]` | Extra path prefixes (relative to `base_path()` or absolute) skipped when finding the caller |
| `log_channel` | `null` | Channel for `log` mode; `null` uses the default channel |

The assertions always run, regardless of `enabled` and the environment, and use the thresholds from this file (`detect_*` only affect the middleware).

## Facade

```php
use ChakraCoder\QueryBudget\Facades\QueryBudget;

$report = QueryBudget::measure(fn () => $service->handle());

$report->totalCount;      // int
$report->duplicates;      // list<DuplicateGroup>
$report->nPlusOne;        // list<NPlusOneGroup>
$report->slowest;         // the five slowest queries (SQL and time)
(string) $report;         // the readable report shown above
```

## How it works and what it reports

1. A single `QueryExecuted` listener is registered at boot. It returns immediately unless a recording scope is open, so there is no cost in normal operation. Scopes nest.
2. Each recorded query is stored as: connection, **normalized SQL**, time, **number** of bindings and caller. The normalizer lowercases keywords, collapses whitespace and comments, turns `in (?, ?, ?)` into `in (?+)`, and replaces any inline string or number literal with `?`.
3. The caller is the first stack frame outside `vendor/` and outside this package, shown relative to your application.
4. Duplicates group by connection and normalized SQL. N+1 candidates group by connection, caller line and normalized SQL.

## How it differs from other tools

- **`Model::preventLazyLoading()`** is built into Laravel and throws on *any* lazy load of a relation. It is the best first line of defence, and we recommend enabling it in non-production environments (`Model::preventLazyLoading(! app()->isProduction())`). It cannot see other repeated queries such as calling `count()` ten times, queries that are not relations, or a page that is simply doing too much. Query Budget measures what the database actually received and gives you a number to hold the route to.
- **`beyondcode/laravel-query-detector`** detects N+1 queries at runtime and alerts you in the browser. Query Budget is built for tests and CI: it makes the count and the repeats an assertion that fails the build, with per-route budgets.
- **Debugbar and Telescope** let you inspect the queries of a request you are looking at. Query Budget fails when a regression lands, without anyone having to look.

They work well together.

## Limitations

- **Octane** and other long-lived workers are untested. The recorder holds no state once scopes close, but the behaviour has not been verified there.
- The middleware covers the HTTP request it wraps. **Queued jobs, console commands and work after the response is sent** are not covered; use the assertions in tests for those.
- **Queries that bypass Laravel's events** (raw PDO, `DB::connection()->getPdo()`) are not seen. Failed queries do not fire `QueryExecuted` and are not recorded.
- Caller detection points at the first application frame. For lazy loading inside Blade that is the compiled view in `storage/framework/views`, and code wrapped in your own helpers can hide the true origin; add the helper's path to `ignored_caller_paths`.
- The duplicate and N+1 checks look at *statements*, not bindings. Running the same query for ten different users is reported (that is the point), but so is a deliberate loop.
- Negated Pest expectations report as errors rather than failures (see above).

## Roadmap

- Timing thresholds and slow-query budgets
- Suggested `with()` calls for N+1 groups
- JSON output for CI annotations
- Octane support
- Queue job budgets

## Development

```bash
composer install
composer check   # validate, lint (Pint), analyse (PHPStan max), test (Pest, coverage >= 90%)
composer test    # needs pcov or Xdebug for coverage
composer format  # fix code style
```

Tests run on Orchestra Testbench with in-memory SQLite. See `SPEC.md` for design decisions and the supported version matrix, and `AGENTS.md` for contributor rules. CI runs `composer check` for PHP 8.2 to 8.5 against Laravel 12 and 13, with `--prefer-lowest` and `--prefer-stable`.

## License

MIT. See [LICENSE](LICENSE).
