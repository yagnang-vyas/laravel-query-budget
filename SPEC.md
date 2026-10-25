# Laravel Query Budget: Specification

Package: `chakracoder/laravel-query-budget` · Namespace: `ChakraCoder\QueryBudget` · Version: 0.1.0

## Purpose

Make database query efficiency measurable and enforceable. N+1 queries and query explosions pass code review because nothing fails; they only show up under production load. This package turns query counts, repeated statements and N+1 patterns into **test assertions** and **route-level budgets** that fail locally and in CI.

Non-goals for 0.1.0: production APM, timing thresholds, a UI, automatic eager-load fixes, non-Eloquent database libraries beyond what `DB::listen` / `QueryExecuted` captures, Octane-specific handling (documented as untested).

## Decisions

Ambiguities were resolved with the most conservative interpretation.

### Supported versions (version matrix)

The official Laravel release policy page was not reachable from the build environment, so the matrix was derived from the policy as known (a major receives bug fixes for 18 months and security fixes for 2 years after release) and from Packagist release data checked on the build date (2026-10-25):

| Laravel | PHP | Status used |
|---|---|---|
| 13.x (`^13.0`, released 2026-03-17) | 8.3, 8.4, 8.5 | supported |
| 12.x (`^12.0`, released 2025-02-24) | 8.2, 8.3, 8.4, 8.5 | supported (security fixes until Feb 2027) |
| 11.x and older | | not supported (11.x was released 2024-03-12, so its two years of security fixes ended in March 2026) |

`composer.json` requires `php ^8.2`, `illuminate/{contracts,database,http,log,routing,support} ^12.0 || ^13.0`. Laravel 13 itself requires PHP 8.3, so Composer resolves the right pairs and CI excludes PHP 8.2 with Laravel 13. Dev tooling: Testbench `^10 || ^11`, Pest `^3 || ^4`, Larastan `^3.1`, Pint `^1.25`.

Locally verified on PHP 8.3 with `composer check`: Laravel 12.0.1 (prefer-lowest), 12.70.1, 13.0.0 (prefer-lowest) and 13.38.0 (prefer-stable). PHP 8.2, 8.4 and 8.5 are covered by the CI matrix only.

### Behaviour

- **Duplicates and N+1 are reported independently.** A statement that is repeated 3 times from one line appears in both sections. `assertNoDuplicateQueries` therefore also fails for an N+1, which is correct: it is a repeated statement. `assertNoNPlusOne` is the narrower check (same statement, same caller line).
- **Duplicate key** is `(connection, normalized SQL)`. **N+1 key** is `(connection, caller line, normalized SQL)`. Thresholds: duplicate when count is *greater than* `duplicate_threshold` (default 1); N+1 when count is *at least* `n_plus_one_threshold` (default 3, minimum 2).
- **Recording scopes nest.** Every open scope receives every query recorded while it is open. Settings (ignore rules) are snapshotted when the outermost scope opens.
- **`ignore_sql_patterns` match the normalized SQL**, not the raw statement, so patterns never see literals.
- **Normalization is privacy-first.** String literals and standalone numbers become `?`. A backslash inside a string literal is treated as an escape (MySQL behaviour) and an unterminated literal swallows the rest of the statement, so a mistake loses structure rather than leaking data.
- **Caller resolution** skips frames inside `vendor/` (the application's and Composer's), this package, and `ignored_caller_paths`. Paths are relative to `base_path()`, then to the working directory, otherwise absolute. For Eloquent lazy loading this is the line that touched the relation; for Blade it is the compiled view file.
- **Ignored caller paths** are relative to `base_path()` unless absolute.
- **Reports** show the route pattern (`GET /users/{email}`), never the concrete URL, because URLs can carry personal data.
- **Middleware order of checks:** `enabled` and the environment are checked first; a malformed `query.budget:` parameter is only validated when the middleware is active.
- **Failed queries** (`QueryException`) do not fire `QueryExecuted` and are not recorded. Laravel itself puts bindings into that exception message; this package does not add to it.
- **`expectQueryBudget`** uses Laravel's `beforeApplicationDestroyed` hook (run in `tearDown`). A second call replaces the first budget.
- **Negated Pest expectations** (`->not->toHaveNPlusOne()`): Pest swallows assertion failures raised inside a negated expectation and replaces the message with a generic one. To keep the detailed report visible, a negated failure throws `QueryBudgetExpectationFailed` (a plain exception), so Pest reports it as an error carrying the report instead of a failure. The positive forms and `assertNoNPlusOne` / `assertNoDuplicateQueries` fail normally.
- **Config validation** runs when the provider boots and every time `Settings` is read, and throws `InvalidArgumentException` naming the key. A missing key is an error (the package config is merged first, so this only happens with a hand-built config).
- **Static analysis scope:** PHPStan runs at level `max` on `src/` plus the test case fixture that exercises the trait. `treatPhpDocTypesAsCertain` is `false` because some Laravel PHPDoc types differ between the supported versions.
- **Dependency installation in the build sandbox:** GitHub zipball downloads were blocked in the environment that produced 0.1.0, so dependencies were installed from shallow git fetches of the locked commits. This does not affect the published package or CI.

## Components

| Class | Responsibility |
|---|---|
| `Recording\QueryRecorder` | Subscribes once to `QueryExecuted`; records only while a scope is open; `start()/stop()/record()/flush()` |
| `Recording\RecordedQuery` | connection, normalized SQL, time (ms), **number** of bindings, caller |
| `Recording\QueryLog` | The queries of one scope |
| `Recording\SqlNormalizer` | Whitespace/comments, keyword case, `in (?+)`, literals to `?`, trailing `;` |
| `Recording\CallerResolver` | First frame outside vendor and package |
| `Analysis\DuplicateDetector`, `NPlusOneDetector` | Grouping and thresholds |
| `Analysis\BudgetReport` | Total, duplicates, N+1, five slowest (SQL and time only); `__toString()` |
| `Testing\AssertsQueryBudget` | PHPUnit/Pest trait |
| `Testing\PestExpectations` | Registered when Pest is present |
| `Http\Middleware\EnforceQueryBudget` | `query.budget` alias; `throw`, `log`, `header` modes |
| `Exceptions\QueryBudgetExceeded` | Carries the report; renders a 500 (report only when `app.debug`) |
| `QueryBudgetManager` + `Facades\QueryBudget` | `measure(Closure): BudgetReport`, `report(QueryLog)`, `recorder()`, `settings()` |
| `Support\Settings`, `EnforcementMode` | Validated configuration |

## Safety requirements (all covered by tests)

1. Binding values and inline literals never appear in reports, assertion messages, exceptions, rendered responses or logs.
2. Production is fully disabled by default: the middleware only acts when `enabled` is true **and** the environment is in `enabled_environments` (`local`, `testing`). Otherwise it is a pass-through that never opens a recording scope, even when a route declares a budget.
3. Recording is inert (immediate return) while no scope is open.
4. Closing a scope releases its queries; scopes close in `finally` blocks.

## Performance (measured on the build machine, SQLite in memory, PHP 8.3)

- 20,000 `select 1` queries with the package's listener registered but no scope open: 293 ms and 298 ms across two runs, against 300 ms and 265 ms with no listener at all: no measurable difference.
- Recording 5,000 queries inside a scope: about 0.03 ms per query (dominated by `debug_backtrace`).
- 50 scopes of 200 queries each: no retained memory after the scopes closed (covered by a test).

## Testing strategy

Pest on Orchestra Testbench with in-memory SQLite and a second connection. Unit tests cover the normalizer, caller resolver, detectors, report and settings. Feature tests cover the recorder (nesting, connections, ignore rules), every assertion on eager (passes) and lazy (fails with a helpful message) fixture routes, Pest expectations, the middleware in each mode, production inertness, provider registration and the privacy guarantees. Coverage gate: 90% (measured 98%).

## Roadmap (not in 0.1.0)

Timing thresholds, Octane support, queue job budgets, richer N+1 hints (suggested `with()` calls), JSON report output for CI annotations.
