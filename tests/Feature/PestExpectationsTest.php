<?php

declare(strict_types=1);

use ChakraCoder\QueryBudget\Exceptions\QueryBudgetExpectationFailed;
use ChakraCoder\QueryBudget\Testing\PestExpectations;
use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    $this->seedBlog(3, 2);
});

it('is available when pest is installed', function (): void {
    expect(PestExpectations::isAvailable())->toBeTrue();
});

it('passes the expectations on the eager route', function (): void {
    expect(fn () => $this->get('/posts-eager'))
        ->toRunAtMostQueries(2)
        ->toRunExactlyQueries(2)
        ->not->toHaveDuplicateQueries()
        ->not->toHaveNPlusOne();
});

it('fails toRunAtMostQueries on the lazy route with the report', function (): void {
    expect(fn () => expect(fn () => $this->get('/posts-lazy'))->toRunAtMostQueries(2))
        ->toThrow(ExpectationFailedException::class, 'Failed asserting that at most 2 queries were executed, but 4 ran.');
});

it('fails toRunExactlyQueries when the count differs', function (): void {
    expect(fn () => expect(fn () => $this->get('/posts-lazy'))->toRunExactlyQueries(2))
        ->toThrow(ExpectationFailedException::class, 'exactly 2 queries were executed, but 4 ran.');
});

it('fails not->toHaveNPlusOne with the detailed report', function (): void {
    expect(fn () => expect(fn () => $this->get('/posts-lazy'))->not->toHaveNPlusOne())
        ->toThrow(QueryBudgetExpectationFailed::class, 'Possible N+1 queries (1):');
});

it('fails not->toHaveDuplicateQueries with the detailed report', function (): void {
    expect(fn () => expect(fn () => $this->get('/posts-duplicates'))->not->toHaveDuplicateQueries())
        ->toThrow(fn (QueryBudgetExpectationFailed $e) => expect($e->getMessage())
            ->toMatch('/3x select count\(\*\) as "?aggregate"? from "comments"/'));
});

it('supports the positive forms', function (): void {
    expect(fn () => $this->get('/posts-lazy'))->toHaveDuplicateQueries()->toHaveNPlusOne();

    expect(fn () => expect(fn () => $this->get('/posts-eager'))->toHaveDuplicateQueries())
        ->toThrow(ExpectationFailedException::class, 'ran duplicate queries');

    expect(fn () => expect(fn () => $this->get('/posts-eager'))->toHaveNPlusOne())
        ->toThrow(ExpectationFailedException::class, 'ran an N+1 query pattern');
});

it('requires a closure', function (): void {
    expect(fn () => expect('not a closure')->toRunAtMostQueries(1))
        ->toThrow(InvalidArgumentException::class, 'require a closure');
});

it('registers only once', function (): void {
    (new PestExpectations)->register();
    (new PestExpectations)->register();

    expect(fn () => $this->get('/quiet'))->toRunAtMostQueries(0);
});

it('passes negated expectations when nothing is found', function (): void {
    expect(fn () => $this->get('/quiet'))->not->toHaveNPlusOne()->not->toHaveDuplicateQueries();
});
