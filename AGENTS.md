# AGENTS.md

Guidance for AI coding agents and contributors working on `chakracoder/laravel-query-budget`.

## What this package does

It records the queries a callback or request executes, groups them by normalized SQL and caller line, and fails tests or requests that exceed a query budget, repeat a statement or show an N+1 pattern. See `SPEC.md` for the behaviour contract and `README.md` for usage.

## Commands

| Command | Purpose |
|---|---|
| `composer test` | Pest with coverage, fails below 90% (needs pcov or Xdebug) |
| `composer analyse` | PHPStan / Larastan at level `max` |
| `composer lint` | Pint in check mode |
| `composer format` | Pint, rewrites files |
| `composer check` | `composer validate --strict`, lint, analyse, test. Must pass before every commit |

## Rules that must not be broken

1. **Never expose binding values.** Reports, exceptions, logs and headers contain normalized SQL with `?` placeholders, counts and `file:line` only. `RecordedQuery` stores the number of bindings, never the values. Any new output path needs a test that asserts a fake value such as `alice@example.com` is absent.
2. **Production is off by default.** The middleware only records in `enabled_environments` (`local`, `testing`) and only when `enabled` is true. In any other environment it is a pass-through that does not start a recording scope. Keep the tests in `tests/Feature/MiddlewareTest.php` ("environment safety") green.
3. **No work while idle.** `QueryRecorder::handle()` must return immediately when no scope is open.
4. Do not weaken, skip or delete tests, lower the coverage threshold, expand a PHPStan baseline (there is none) or add `@phpstan-ignore` without a written justification.
5. Every PHP file has `declare(strict_types=1);`. Classes are `final` unless a fixture needs otherwise. Use constructor promotion and `readonly` where possible.

## Layout

```
src/Recording   QueryRecorder, RecordedQuery, QueryLog, CallerResolver, SqlNormalizer
src/Analysis    DuplicateDetector, NPlusOneDetector, BudgetReport and the group value objects
src/Testing     AssertsQueryBudget trait, PestExpectations, QueryBudgetChecks (shared messages)
src/Http        EnforceQueryBudget middleware
src/Support     Settings (validated config) and EnforcementMode
tests/Fixtures  Authors, posts and comments, migrations, factories, controllers and routes with N+1, eager and duplicate variants
```

## Testing notes

- Tests run on Orchestra Testbench with an in-memory SQLite database plus a second connection named `secondary`.
- Feature tests use the `AssertsQueryBudget` trait through `tests/Fixtures/Support/BudgetAssertingTestCase.php`, which is the shape of test case a package user would write.
- Tests that switch `app()->environment()` to `production` must restore it to `testing` afterwards so Testbench can roll back its migrations.

## Commits

Conventional Commits (`feat:`, `fix:`, `docs:`, `chore:`, `refactor:`, `test:`). Run `composer check` first.
