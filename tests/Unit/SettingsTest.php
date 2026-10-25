<?php

declare(strict_types=1);

use ChakraCoder\QueryBudget\Support\EnforcementMode;
use ChakraCoder\QueryBudget\Support\Settings;
use Illuminate\Config\Repository;

function validConfig(array $overrides = []): array
{
    return array_replace(require dirname(__DIR__, 2).'/config/query-budget.php', $overrides);
}

it('reads the default configuration', function (): void {
    $settings = Settings::fromArray(validConfig());

    expect($settings->enabled)->toBeTrue()
        ->and($settings->enabledEnvironments)->toBe(['local', 'testing'])
        ->and($settings->mode)->toBe(EnforcementMode::Throw)
        ->and($settings->defaultBudget)->toBeNull()
        ->and($settings->duplicateThreshold)->toBe(1)
        ->and($settings->nPlusOneThreshold)->toBe(3)
        ->and($settings->detectDuplicates)->toBeTrue()
        ->and($settings->detectNPlusOne)->toBeTrue()
        ->and($settings->ignoreSqlPatterns)->toBe([])
        ->and($settings->logChannel)->toBeNull();
});

it('reads values from a config repository', function (): void {
    $settings = Settings::fromRepository(new Repository(['query-budget' => validConfig(['default_budget' => 12, 'log_channel' => 'stderr'])]));

    expect($settings->defaultBudget)->toBe(12)->and($settings->logChannel)->toBe('stderr');
});

it('rejects a missing or non array config section', function (): void {
    Settings::fromRepository(new Repository(['query-budget' => 'nope']));
})->throws(InvalidArgumentException::class, 'must be an array');

it('rejects invalid values with a clear message', function (array $override, string $message): void {
    expect(fn () => Settings::fromArray(validConfig($override)))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'enabled' => [['enabled' => 'yes'], 'query-budget.enabled must be a boolean, string given'],
    'mode type' => [['mode' => 1], 'query-budget.mode must be a string, int given'],
    'mode value' => [['mode' => 'explode'], 'query-budget.mode must be one of "throw", "log" or "header"'],
    'environments' => [['enabled_environments' => 'local'], 'query-budget.enabled_environments must be an array of strings, string given'],
    'environment item' => [['enabled_environments' => ['local', 5]], 'query-budget.enabled_environments must only contain strings, int found'],
    'budget type' => [['default_budget' => '10'], 'query-budget.default_budget must be an integer, string given'],
    'budget negative' => [['default_budget' => -1], 'query-budget.default_budget must be at least 0, -1 given'],
    'duplicate threshold' => [['duplicate_threshold' => 0], 'query-budget.duplicate_threshold must be at least 1'],
    'n+1 threshold' => [['n_plus_one_threshold' => 1], 'query-budget.n_plus_one_threshold must be at least 2'],
    'detect duplicates' => [['detect_duplicates' => 1], 'query-budget.detect_duplicates must be a boolean, int given'],
    'detect n+1' => [['detect_n_plus_one' => null], 'query-budget.detect_n_plus_one must be a boolean, null given'],
    'pattern' => [['ignore_sql_patterns' => ['/(unclosed']], 'query-budget.ignore_sql_patterns contains an invalid regular expression'],
    'connections' => [['ignore_connections' => 'mysql'], 'query-budget.ignore_connections must be an array of strings'],
    'caller paths' => [['ignored_caller_paths' => [1]], 'query-budget.ignored_caller_paths must only contain strings'],
    'log channel' => [['log_channel' => 5], 'query-budget.log_channel must be a string, int given'],
]);

it('rejects a missing key', function (): void {
    $config = validConfig();
    unset($config['mode']);

    Settings::fromArray($config);
})->throws(InvalidArgumentException::class, 'query-budget.mode is missing');
