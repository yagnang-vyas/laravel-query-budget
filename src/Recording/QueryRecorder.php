<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Recording;

use ChakraCoder\QueryBudget\Support\Settings;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Events\QueryExecuted;
use LogicException;

/**
 * Captures executed queries, but only while at least one recording scope is open.
 *
 * Scopes nest: every open scope receives every query recorded while it is open.
 */
final class QueryRecorder
{
    /** @var array<int, QueryLog> */
    private array $scopes = [];

    private int $nextToken = 0;

    private ?Settings $settings = null;

    public function __construct(
        private readonly SqlNormalizer $normalizer,
        private readonly CallerResolver $callers,
        private readonly Repository $config,
    ) {}

    public function isRecording(): bool
    {
        return $this->scopes !== [];
    }

    /**
     * Open a recording scope and return the token that closes it.
     */
    public function start(): int
    {
        if ($this->scopes === []) {
            $this->settings = Settings::fromRepository($this->config);
        }

        $token = $this->nextToken++;
        $this->scopes[$token] = new QueryLog;

        return $token;
    }

    /**
     * Close a scope and return everything it captured.
     */
    public function stop(int $token): QueryLog
    {
        $log = $this->scopes[$token] ?? throw new LogicException('Unknown or already closed query recording scope.');

        unset($this->scopes[$token]);

        if ($this->scopes === []) {
            $this->settings = null;
        }

        return $log;
    }

    /**
     * Run the callback inside a recording scope.
     */
    public function record(Closure $callback): QueryLog
    {
        $token = $this->start();

        try {
            $callback();
        } finally {
            $log = $this->stop($token);
        }

        return $log;
    }

    /**
     * Drop every open scope. Intended for long-running processes and test teardown.
     */
    public function flush(): void
    {
        $this->scopes = [];
        $this->settings = null;
    }

    public function handle(QueryExecuted $event): void
    {
        if ($this->scopes === [] || $this->settings === null) {
            return;
        }

        $settings = $this->settings;

        if (in_array($event->connectionName, $settings->ignoreConnections, true)) {
            return;
        }

        $sql = $this->normalizer->normalize($event->sql);

        foreach ($settings->ignoreSqlPatterns as $pattern) {
            if (preg_match($pattern, $sql) === 1) {
                return;
            }
        }

        $query = new RecordedQuery(
            connection: $event->connectionName,
            sql: $sql,
            timeMs: is_float($event->time) ? $event->time : 0.0,
            bindingCount: count($event->bindings),
            caller: $this->callers->resolve($settings->ignoredCallerPaths),
        );

        foreach ($this->scopes as $log) {
            $log->add($query);
        }
    }
}
