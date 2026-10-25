<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Support;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;

/**
 * Validated, typed view of config/query-budget.php.
 */
final readonly class Settings
{
    /**
     * @param  list<string>  $enabledEnvironments
     * @param  list<string>  $ignoreSqlPatterns
     * @param  list<string>  $ignoreConnections
     * @param  list<string>  $ignoredCallerPaths
     */
    public function __construct(
        public bool $enabled,
        public array $enabledEnvironments,
        public EnforcementMode $mode,
        public ?int $defaultBudget,
        public int $duplicateThreshold,
        public int $nPlusOneThreshold,
        public bool $detectDuplicates,
        public bool $detectNPlusOne,
        public array $ignoreSqlPatterns,
        public array $ignoreConnections,
        public array $ignoredCallerPaths,
        public ?string $logChannel,
    ) {}

    public static function fromRepository(Repository $config): self
    {
        $raw = $config->get('query-budget');

        if (! is_array($raw)) {
            throw new InvalidArgumentException('The "query-budget" configuration must be an array.');
        }

        /** @var array<string, mixed> $raw */
        return self::fromArray($raw);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $mode = self::string($config, 'mode');

        return new self(
            enabled: self::bool($config, 'enabled'),
            enabledEnvironments: self::stringList($config, 'enabled_environments'),
            mode: EnforcementMode::tryFrom($mode) ?? throw new InvalidArgumentException(
                sprintf('query-budget.mode must be one of "throw", "log" or "header", "%s" given.', $mode),
            ),
            defaultBudget: self::nullableInt($config, 'default_budget', 0),
            duplicateThreshold: self::int($config, 'duplicate_threshold', 1),
            nPlusOneThreshold: self::int($config, 'n_plus_one_threshold', 2),
            detectDuplicates: self::bool($config, 'detect_duplicates'),
            detectNPlusOne: self::bool($config, 'detect_n_plus_one'),
            ignoreSqlPatterns: self::patterns($config, 'ignore_sql_patterns'),
            ignoreConnections: self::stringList($config, 'ignore_connections'),
            ignoredCallerPaths: self::stringList($config, 'ignored_caller_paths'),
            logChannel: self::nullableString($config, 'log_channel'),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function value(array $config, string $key): mixed
    {
        if (! array_key_exists($key, $config)) {
            throw new InvalidArgumentException(sprintf('query-budget.%s is missing from the configuration.', $key));
        }

        return $config[$key];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function bool(array $config, string $key): bool
    {
        $value = self::value($config, $key);

        return is_bool($value)
            ? $value
            : throw new InvalidArgumentException(sprintf('query-budget.%s must be a boolean, %s given.', $key, get_debug_type($value)));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function string(array $config, string $key): string
    {
        $value = self::value($config, $key);

        return is_string($value)
            ? $value
            : throw new InvalidArgumentException(sprintf('query-budget.%s must be a string, %s given.', $key, get_debug_type($value)));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function nullableString(array $config, string $key): ?string
    {
        return self::value($config, $key) === null ? null : self::string($config, $key);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function int(array $config, string $key, int $minimum): int
    {
        $value = self::value($config, $key);

        if (! is_int($value)) {
            throw new InvalidArgumentException(sprintf('query-budget.%s must be an integer, %s given.', $key, get_debug_type($value)));
        }

        if ($value < $minimum) {
            throw new InvalidArgumentException(sprintf('query-budget.%s must be at least %d, %d given.', $key, $minimum, $value));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function nullableInt(array $config, string $key, int $minimum): ?int
    {
        return self::value($config, $key) === null ? null : self::int($config, $key, $minimum);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private static function stringList(array $config, string $key): array
    {
        $value = self::value($config, $key);

        if (! is_array($value)) {
            throw new InvalidArgumentException(sprintf('query-budget.%s must be an array of strings, %s given.', $key, get_debug_type($value)));
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw new InvalidArgumentException(sprintf('query-budget.%s must only contain strings, %s found.', $key, get_debug_type($item)));
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private static function patterns(array $config, string $key): array
    {
        $patterns = self::stringList($config, $key);

        foreach ($patterns as $pattern) {
            if (! self::isValidPattern($pattern)) {
                throw new InvalidArgumentException(sprintf('query-budget.%s contains an invalid regular expression: %s', $key, $pattern));
            }
        }

        return $patterns;
    }

    private static function isValidPattern(string $pattern): bool
    {
        // preg_match() raises a warning on a malformed pattern; swallow it and rely on the `false` return.
        set_error_handler(static fn (): bool => true);

        try {
            return preg_match($pattern, '') !== false;
        } finally {
            restore_error_handler();
        }
    }
}
