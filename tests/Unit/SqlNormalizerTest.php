<?php

declare(strict_types=1);

use ChakraCoder\QueryBudget\Recording\SqlNormalizer;

beforeEach(function (): void {
    $this->normalizer = new SqlNormalizer;
});

it('collapses whitespace and lowercases keywords', function (): void {
    expect($this->normalizer->normalize("SELECT   *\n FROM\t\"posts\"   WHERE  \"id\" = ?"))
        ->toBe('select * from "posts" where "id" = ?');
});

it('keeps the case of quoted identifiers', function (): void {
    expect($this->normalizer->normalize('SELECT "UserName", `Email` FROM "Users"'))
        ->toBe('select "UserName", `Email` from "Users"');
});

it('collapses in lists of any size', function (string $sql): void {
    expect($this->normalizer->normalize($sql))->toBe('select * from "posts" where "id" in (?+)');
})->with([
    'one' => 'select * from "posts" where "id" in (?)',
    'three' => 'select * from "posts" where "id" in (?, ?, ?)',
    'spaced' => 'SELECT * FROM "posts" WHERE "id" IN ( ? ,?,   ? )',
]);

it('collapses not in lists', function (): void {
    expect($this->normalizer->normalize('select * from t where id not in (?, ?)'))->toBe('select * from t where id not in (?+)');
});

it('replaces inline string and numeric literals', function (): void {
    expect($this->normalizer->normalize("select * from users where email = 'alice@example.com' and age > 30 and score < 1.5e3 limit 10"))
        ->toBe('select * from users where email = ? and age > ? and score < ? limit ?');
});

it('handles escaped quotes inside string literals', function (): void {
    expect($this->normalizer->normalize("select * from t where a = 'it''s' and b = 'x\\'y' and c = 1"))
        ->toBe('select * from t where a = ? and b = ? and c = ?');
});

it('swallows an unterminated literal so nothing after it leaks', function (): void {
    expect($this->normalizer->normalize("select * from t where a = 'secret"))->toBe('select * from t where a = ?');
});

it('does not touch digits inside identifiers', function (): void {
    expect($this->normalizer->normalize('select t1.col2, "a1" from table3 as t1 where t1.id = $1 and x = :p2'))
        ->toBe('select t1.col2, "a1" from table3 as t1 where t1.id = $1 and x = :p2');
});

it('does not treat quotes inside quoted identifiers as literals', function (): void {
    expect($this->normalizer->normalize('select "it\'s" from t where a = 5'))
        ->toBe('select "it\'s" from t where a = ?');
});

it('strips comments and trailing semicolons', function (): void {
    expect($this->normalizer->normalize("select 1 /* hint */ from t -- trailing note\n where a = ?;;  "))
        ->toBe('select ? from t where a = ?');
});

it('treats an unterminated block comment or identifier safely', function (): void {
    expect($this->normalizer->normalize('select * from t /* open'))->toBe('select * from t');
    expect($this->normalizer->normalize('select "open'))->toBe('select "open');
    expect($this->normalizer->normalize('select 1 -- only a comment'))->toBe('select ?');
});

it('produces the same output for statements that differ only in literals', function (): void {
    expect($this->normalizer->normalize('select * from users where id = 1'))
        ->toBe($this->normalizer->normalize('select  *  from USERS where id = 2'));
});

it('never keeps literal values in the output', function (): void {
    $out = $this->normalizer->normalize("insert into users (email, token) values ('alice@example.com', 'hunter2-secret') ");

    expect($out)->not->toContain('alice')->and($out)->not->toContain('hunter2');
});

it('does not replace numbers that are part of a longer token', function (): void {
    expect($this->normalizer->normalize('select 5abc, 1.2.3 from t'))->toBe('select 5abc, 1.2.3 from t');
});
