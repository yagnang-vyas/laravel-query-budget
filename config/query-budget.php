<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch for the route middleware. Even when true, the middleware is
    | a pass-through unless the application environment is listed in
    | "enabled_environments". Recording inside test assertions always works.
    |
    */
    'enabled' => env('QUERY_BUDGET_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Enabled environments
    |--------------------------------------------------------------------------
    |
    | The middleware only enforces budgets in these environments. Production is
    | deliberately absent: add it only if you accept the recording overhead.
    |
    */
    'enabled_environments' => ['local', 'testing'],

    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    |
    | throw  - throw QueryBudgetExceeded when a budget is violated
    | log    - log a warning containing the report to "log_channel"
    | header - add X-Query-Count (and X-Query-Budget-Exceeded: 1 when over)
    |
    */
    'mode' => env('QUERY_BUDGET_MODE', 'throw'),

    /*
    |--------------------------------------------------------------------------
    | Default budget
    |--------------------------------------------------------------------------
    |
    | Maximum number of queries per request when the middleware is used without
    | a parameter. Null means no count limit (duplicate / N+1 checks still run).
    |
    */
    'default_budget' => null,

    /*
    |--------------------------------------------------------------------------
    | Duplicate and N+1 detection
    |--------------------------------------------------------------------------
    |
    | duplicate_threshold:  a statement executed more than this many times is a
    |                       duplicate (1 means any repeat counts).
    | n_plus_one_threshold: a statement executed at least this many times from
    |                       the same caller line is reported as N+1.
    |
    */
    'duplicate_threshold' => 1,
    'n_plus_one_threshold' => 3,
    'detect_duplicates' => true,
    'detect_n_plus_one' => true,

    /*
    |--------------------------------------------------------------------------
    | Ignore rules
    |--------------------------------------------------------------------------
    |
    | ignore_sql_patterns:  regular expressions matched against the normalized
    |                       SQL; matching queries are not recorded.
    | ignore_connections:   connection names that are never recorded.
    | ignored_caller_paths: extra path prefixes (relative to base_path()) that
    |                       are skipped when resolving the caller of a query.
    |
    */
    'ignore_sql_patterns' => [
        // '/\bfrom "?sessions"?\b/i',
        // '/\bfrom "?cache"?\b/i',
    ],
    'ignore_connections' => [],
    'ignored_caller_paths' => [],

    /*
    |--------------------------------------------------------------------------
    | Log channel
    |--------------------------------------------------------------------------
    |
    | Channel used by the "log" mode. Null uses the default log channel.
    |
    */
    'log_channel' => null,

];
