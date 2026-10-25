# Changelog

All notable changes to `chakracoder/laravel-query-budget` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - Unreleased

### Added

- Query recording through a nestable recording scope that is inert while no scope is open.
- SQL normalization: whitespace and comment removal, lowercased keywords, `in (?+)` list collapsing and replacement of inline literals with `?`.
- Caller resolution that skips vendor and package frames and reports `file:line` relative to the application.
- Duplicate query and N+1 detection with configurable thresholds, and a `BudgetReport` that never contains binding values.
- Test API: `assertQueryCountAtMost`, `assertQueryCountExactly`, `assertNoDuplicateQueries`, `assertNoNPlusOne`, `recordQueries` and `expectQueryBudget` (trait `AssertsQueryBudget`).
- Pest expectations `toRunAtMostQueries`, `toRunExactlyQueries`, `toHaveDuplicateQueries` and `toHaveNPlusOne`.
- `query.budget` route middleware with `throw`, `log` and `header` modes, active only in `local` and `testing` by default.
- `QueryBudget` facade, publishable `query-budget.php` config with boot-time validation.
- Support for Laravel 12 and 13 on PHP 8.2 to 8.5 (Laravel 13 requires PHP 8.3).
