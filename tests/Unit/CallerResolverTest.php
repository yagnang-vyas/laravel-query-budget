<?php

declare(strict_types=1);

use ChakraCoder\QueryBudget\Recording\CallerResolver;

function frame(string $file, int $line): array
{
    return ['file' => $file, 'line' => $line, 'function' => 'x'];
}

beforeEach(function (): void {
    $this->resolver = new CallerResolver('/srv/app');
});

it('returns the first frame outside vendor and the package, relative to the base path', function (): void {
    $trace = [
        frame('/srv/app/vendor/laravel/framework/src/Illuminate/Database/Connection.php', 10),
        frame(dirname(__DIR__, 2).'/src/Recording/QueryRecorder.php', 20),
        frame('/srv/app/app/Http/Controllers/PostController.php', 42),
        frame('/srv/app/app/Other.php', 7),
    ];

    expect($this->resolver->resolveFromTrace($trace))->toBe('app/Http/Controllers/PostController.php:42');
});

it('skips frames without a file or line', function (): void {
    $trace = [
        ['function' => 'internal'],
        ['file' => '/srv/app/app/A.php', 'function' => 'noline'],
        frame('/srv/app/app/B.php', 3),
    ];

    expect($this->resolver->resolveFromTrace($trace))->toBe('app/B.php:3');
});

it('honours extra ignored paths, absolute or relative to the base path', function (): void {
    $trace = [
        frame('/srv/app/app/Support/Wrapper.php', 5),
        frame('/srv/shared/Helper.php', 6),
        frame('/srv/app/app/Real.php', 7),
    ];

    expect($this->resolver->resolveFromTrace($trace, ['app/Support', '/srv/shared']))->toBe('app/Real.php:7');
});

it('does not treat a sibling directory with a common prefix as ignored', function (): void {
    $trace = [frame('/srv/app/vendor-extras/Thing.php', 9)];

    expect($this->resolver->resolveFromTrace($trace))->toBe('vendor-extras/Thing.php:9');
});

it('reports unknown when every frame is ignored', function (): void {
    expect($this->resolver->resolveFromTrace([frame('/srv/app/vendor/a/b.php', 1)]))->toBe(CallerResolver::UNKNOWN);
    expect($this->resolver->resolveFromTrace([]))->toBe(CallerResolver::UNKNOWN);
});

it('keeps paths outside the base path and working directory absolute', function (): void {
    expect($this->resolver->resolveFromTrace([frame('/elsewhere/File.php', 2)]))->toBe('/elsewhere/File.php:2');
});

it('normalises windows style separators', function (): void {
    $resolver = new CallerResolver('C:\\srv\\app');

    expect($resolver->resolveFromTrace([frame('C:\\srv\\app\\app\\Thing.php', 4)]))->toBe('app/Thing.php:4');
    expect($resolver->resolveFromTrace([frame('C:\\srv\\app\\app\\Thing.php', 4)], ['D:\\other', 'C:\\srv\\app\\app']))->toBe(CallerResolver::UNKNOWN);
});

it('resolves from the real call stack', function (): void {
    expect($this->resolver->resolve())->toContain('tests/Unit/CallerResolverTest.php:');
});
