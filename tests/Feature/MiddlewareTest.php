<?php

declare(strict_types=1);

use ChakraCoder\QueryBudget\Exceptions\QueryBudgetExceeded;
use ChakraCoder\QueryBudget\Http\Middleware\EnforceQueryBudget;
use ChakraCoder\QueryBudget\Recording\QueryRecorder;
use ChakraCoder\QueryBudget\Tests\Fixtures\Models\Post;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\TestHandler;

beforeEach(function (): void {
    $this->seedBlog(3, 2);
});

describe('throw mode', function (): void {
    it('lets a request within budget through', function (): void {
        $this->withoutExceptionHandling()->get('/budgeted/eager')->assertOk();
        $this->withoutExceptionHandling()->get('/budgeted/quiet')->assertOk();
    });

    it('throws QueryBudgetExceeded with the report when the budget is exceeded', function (): void {
        try {
            $this->withoutExceptionHandling()->get('/budgeted/lazy');
            $this->fail('Expected the budget to be exceeded.');
        } catch (QueryBudgetExceeded $e) {
            expect($e->getMessage())
                ->toContain('Query budget exceeded for this GET /budgeted/lazy: 4 queries executed, budget is 3')
                ->toContain('1 duplicate statement')
                ->toContain('1 possible N+1 pattern')
                ->toContain('Possible N+1 queries (1):')
                ->toContain('tests/Fixtures/Controllers/PostController.php:')
                ->and($e->report()->totalCount)->toBe(4)
                ->and($e->violations())->toHaveCount(3);
        }
    });

    it('flags duplicates even when the count is within budget', function (): void {
        $this->withoutExceptionHandling()->get('/budgeted/duplicates');
    })->throws(QueryBudgetExceeded::class, '1 duplicate statement');

    it('renders a 500 with the report in debug mode', function (): void {
        config(['app.debug' => true]);

        $this->get('/budgeted/lazy')
            ->assertStatus(500)
            ->assertSee('Query budget exceeded for this GET /budgeted/lazy', false)
            ->assertSee('Possible N+1 queries', false);
    });

    it('renders a bare 500 without debug mode', function (): void {
        config(['app.debug' => false]);

        $response = $this->get('/budgeted/lazy')->assertStatus(500);

        expect($response->getContent())->toBe('Server Error');
    });

    it('does not leave a recording scope open after throwing', function (): void {
        $this->get('/budgeted/lazy');

        expect($this->app->make(QueryRecorder::class)->isRecording())->toBeFalse();
    });

    it('closes the scope when the controller throws', function (): void {
        Route::get('/boom', fn () => throw new RuntimeException('boom'))->middleware('query.budget:5');

        $this->get('/boom')->assertStatus(500);

        expect($this->app->make(QueryRecorder::class)->isRecording())->toBeFalse();
    });
});

describe('budget resolution', function (): void {
    it('uses default_budget when the middleware has no parameter', function (): void {
        Route::get('/default', fn () => response()->json(Post::count()))->middleware('query.budget');
        config(['query-budget.default_budget' => 0]);

        $this->withoutExceptionHandling()->get('/default');
    })->throws(QueryBudgetExceeded::class, '1 queries executed, budget is 0');

    it('has no count limit without a parameter or default', function (): void {
        Route::get('/unbounded', fn () => response()->json(Post::count()))->middleware('query.budget');

        $this->withoutExceptionHandling()->get('/unbounded')->assertOk();
    });

    it('lets the route parameter override the default', function (): void {
        Route::get('/override', fn () => response()->json(Post::count()))->middleware('query.budget:5');
        config(['query-budget.default_budget' => 0]);

        $this->withoutExceptionHandling()->get('/override')->assertOk();
    });

    it('rejects a malformed budget parameter', function (): void {
        Route::get('/bad', fn () => 'x')->middleware('query.budget:lots');

        $this->withoutExceptionHandling()->get('/bad');
    })->throws(InvalidArgumentException::class, 'must be a non-negative integer, "lots" given');

    it('can be registered as a global middleware', function (): void {
        $this->app->make(Kernel::class)->pushMiddleware(EnforceQueryBudget::class);
        config(['query-budget.default_budget' => 1]);

        $this->withoutExceptionHandling()->get('/posts-eager');
    })->throws(QueryBudgetExceeded::class, '2 queries executed, budget is 1');

    it('can disable duplicate detection', function (): void {
        config(['query-budget.detect_duplicates' => false, 'query-budget.detect_n_plus_one' => true]);

        $this->withoutExceptionHandling()->get('/budgeted/duplicates');
    })->throws(QueryBudgetExceeded::class, '1 possible N+1 pattern');

    it('can disable all detection and fall back to the count alone', function (): void {
        config(['query-budget.detect_duplicates' => false, 'query-budget.detect_n_plus_one' => false]);

        $this->withoutExceptionHandling()->get('/budgeted/duplicates')->assertOk();
    });

    it('honours ignore_sql_patterns', function (): void {
        config(['query-budget.ignore_sql_patterns' => ['/from "comments"/']]);

        $this->withoutExceptionHandling()->get('/budgeted/duplicates')->assertOk();
    });
});

describe('header mode', function (): void {
    beforeEach(function (): void {
        config(['query-budget.mode' => 'header']);
    });

    it('adds the query count', function (): void {
        $this->get('/budgeted/eager')
            ->assertOk()
            ->assertHeader('X-Query-Count', '2')
            ->assertHeaderMissing('X-Query-Budget-Exceeded');
    });

    it('adds the exceeded header when over budget', function (): void {
        $this->get('/budgeted/lazy')
            ->assertOk()
            ->assertHeader('X-Query-Count', '4')
            ->assertHeader('X-Query-Budget-Exceeded', '1');
    });
});

describe('log mode', function (): void {
    beforeEach(function (): void {
        config([
            'query-budget.mode' => 'log',
            'query-budget.log_channel' => 'budget',
            'logging.channels.budget' => ['driver' => 'monolog', 'handler' => TestHandler::class],
        ]);
    });

    it('logs a warning with the report and does not fail the request', function (): void {
        $this->withoutExceptionHandling()->get('/budgeted/lazy')->assertOk();

        /** @var TestHandler $handler */
        $handler = Log::channel('budget')->getLogger()->getHandlers()[0];

        expect($handler->getRecords())->toHaveCount(1);

        $record = $handler->getRecords()[0];

        expect($record->level->getName())->toBe('WARNING')
            ->and($record->message)
            ->toContain('Query budget exceeded for GET /budgeted/lazy')
            ->toContain('Possible N+1 queries (1):');
    });

    it('logs nothing within budget', function (): void {
        $this->get('/budgeted/eager')->assertOk();

        /** @var TestHandler $handler */
        $handler = Log::channel('budget')->getLogger()->getHandlers()[0];

        expect($handler->getRecords())->toBe([]);
    });

    it('logs the route pattern, not the concrete url', function (): void {
        Route::get('/users/{email}', fn (string $email) => response()->json(
            [Post::count(), Post::count()],
        ))->middleware('query.budget:1');

        $this->get('/users/alice@example.com')->assertOk();

        /** @var TestHandler $handler */
        $handler = Log::channel('budget')->getLogger()->getHandlers()[0];
        $message = $handler->getRecords()[0]->message;

        expect($message)->toContain('GET /users/{email}')->not->toContain('alice@example.com');
    });
});

describe('environment safety', function (): void {
    afterEach(function (): void {
        // Production would make the migration rollback in tearDown prompt for confirmation.
        $this->app['env'] = 'testing';
    });

    it('is completely inert in production, even with enabled=true and a declared budget', function (): void {
        $this->app['env'] = 'production';
        config(['query-budget.enabled' => true, 'query-budget.mode' => 'throw']);

        $recorder = $this->app->make(QueryRecorder::class);
        $sawRecording = false;
        Route::get('/spy', function () use ($recorder, &$sawRecording) {
            $sawRecording = $recorder->isRecording();

            return response()->json(1);
        })->middleware('query.budget:0');

        $this->withoutExceptionHandling()->get('/budgeted/lazy')->assertOk()->assertHeaderMissing('X-Query-Count');
        $this->withoutExceptionHandling()->get('/spy')->assertOk();

        expect($sawRecording)->toBeFalse();
    });

    it('is inert in production for every mode', function (string $mode): void {
        $this->app['env'] = 'production';
        config(['query-budget.mode' => $mode]);

        $this->get('/budgeted/lazy')->assertOk()
            ->assertHeaderMissing('X-Query-Count')
            ->assertHeaderMissing('X-Query-Budget-Exceeded');
    })->with(['throw', 'log', 'header']);

    it('is inert in an environment that is not listed', function (): void {
        $this->app['env'] = 'staging';

        $this->get('/budgeted/lazy')->assertOk();
    });

    it('can be enabled for another environment explicitly', function (): void {
        $this->app['env'] = 'staging';
        config(['query-budget.enabled_environments' => ['staging']]);

        $this->withoutExceptionHandling()->get('/budgeted/lazy');
    })->throws(QueryBudgetExceeded::class);

    it('is inert when enabled is false, even in a listed environment', function (): void {
        config(['query-budget.enabled' => false]);

        $this->get('/budgeted/lazy')->assertOk();
    });

    it('is inert when no environment is enabled', function (): void {
        config(['query-budget.enabled_environments' => []]);

        $this->get('/budgeted/lazy')->assertOk();
    });

    it('does not even parse the budget parameter when inert', function (): void {
        $this->app['env'] = 'production';
        Route::get('/bad-inert', fn () => 'x')->middleware('query.budget:lots');

        $this->get('/bad-inert')->assertOk();
    });
});
