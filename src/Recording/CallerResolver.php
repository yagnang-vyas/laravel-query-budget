<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Recording;

use Composer\Autoload\ClassLoader;
use ReflectionClass;

/**
 * Finds the application code (file:line) responsible for a query.
 *
 * Frames inside vendor directories and inside this package are skipped.
 */
final class CallerResolver
{
    public const UNKNOWN = 'unknown';

    /** @var list<string> */
    private array $ignoredPrefixes;

    /**
     * @param  list<string>  $ignoredPrefixes  extra absolute path prefixes that are never reported
     */
    public function __construct(private readonly string $basePath, array $ignoredPrefixes = [])
    {
        $this->ignoredPrefixes = $this->expand([
            ...$ignoredPrefixes,
            dirname(__DIR__),
            $this->basePath.'/vendor',
            ...$this->composerVendorPaths(),
        ]);
    }

    /**
     * @param  list<string>  $extraIgnoredPaths  additional prefixes, absolute or relative to the base path
     */
    public function resolve(array $extraIgnoredPaths = []): string
    {
        return $this->resolveFromTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), $extraIgnoredPaths);
    }

    /**
     * @param  list<array<string, mixed>>  $trace
     * @param  list<string>  $extraIgnoredPaths
     */
    public function resolveFromTrace(array $trace, array $extraIgnoredPaths = []): string
    {
        $extra = $this->expand(array_map(
            fn (string $path): string => $this->isAbsolute($path) ? $path : $this->basePath.'/'.ltrim($path, '/\\'),
            $extraIgnoredPaths,
        ));

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? null;
            $line = $frame['line'] ?? null;

            if (! is_string($file) || ! is_int($line)) {
                continue;
            }

            $file = $this->slashes($file);

            if ($this->isIgnored($file, $this->ignoredPrefixes) || $this->isIgnored($file, $extra)) {
                continue;
            }

            return $this->relative($file).':'.$line;
        }

        return self::UNKNOWN;
    }

    /**
     * @param  list<string>  $prefixes
     */
    private function isIgnored(string $file, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($file, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    private function relative(string $file): string
    {
        $roots = [$this->slashes($this->basePath)];
        $cwd = getcwd();

        if ($cwd !== false) {
            $roots[] = $this->slashes($cwd);
        }

        foreach ($roots as $root) {
            if (str_starts_with($file, rtrim($root, '/').'/')) {
                return substr($file, strlen(rtrim($root, '/')) + 1);
            }
        }

        return $file;
    }

    /**
     * @return list<string>
     */
    private function composerVendorPaths(): array
    {
        if (! class_exists(ClassLoader::class)) {
            return [];
        }

        $file = (new ReflectionClass(ClassLoader::class))->getFileName();

        return $file === false ? [] : [dirname($file, 2)];
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function expand(array $paths): array
    {
        $expanded = [];

        foreach ($paths as $path) {
            $path = rtrim($this->slashes($path), '/');

            if ($path === '') {
                continue;
            }

            $expanded[] = $path;
            $real = realpath($path);

            if ($real !== false) {
                $expanded[] = rtrim($this->slashes($real), '/');
            }
        }

        return array_values(array_unique($expanded));
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function slashes(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
