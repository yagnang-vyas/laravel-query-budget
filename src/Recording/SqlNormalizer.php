<?php

declare(strict_types=1);

namespace ChakraCoder\QueryBudget\Recording;

/**
 * Turns SQL into a stable, privacy-safe form so equal statements group together.
 *
 * - whitespace and comments are collapsed or removed
 * - keywords and unquoted identifiers are lowercased; quoted identifiers keep their case
 * - inline string and numeric literals become "?"
 * - "in (?, ?, ?)" becomes "in (?+)" regardless of the list size
 * - trailing semicolons are removed
 */
final class SqlNormalizer
{
    public function normalize(string $sql): string
    {
        $out = '';
        $length = strlen($sql);
        $i = 0;

        while ($i < $length) {
            $char = $sql[$i];

            if ($char === "'") {
                $i = $this->skipString($sql, $i, $length);
                $out .= '?';

                continue;
            }

            if ($char === '"' || $char === '`') {
                $end = $this->skipQuotedIdentifier($sql, $i, $length, $char);
                $out .= substr($sql, $i, $end - $i);
                $i = $end;

                continue;
            }

            if ($char === '-' && ($sql[$i + 1] ?? '') === '-') {
                $newline = strpos($sql, "\n", $i);
                $i = $newline === false ? $length : $newline;
                $out = $this->withSpace($out);

                continue;
            }

            if ($char === '/' && ($sql[$i + 1] ?? '') === '*') {
                $close = strpos($sql, '*/', $i + 2);
                $i = $close === false ? $length : $close + 2;
                $out = $this->withSpace($out);

                continue;
            }

            if (ctype_space($char)) {
                while ($i < $length && ctype_space($sql[$i])) {
                    $i++;
                }
                $out = $this->withSpace($out);

                continue;
            }

            if (ctype_digit($char) && ! $this->isWordCharacter(substr($out, -1))) {
                $end = $this->skipNumber($sql, $i, $length);

                if (! $this->isWordCharacter($sql[$end] ?? '')) {
                    $out .= '?';
                    $i = $end;

                    continue;
                }
            }

            $out .= strtolower($char);
            $i++;
        }

        $out = trim($out);
        $out = (string) preg_replace('/\bin\s*\(\s*\?(?:\s*,\s*\?)*\s*\)/', 'in (?+)', $out);

        return rtrim($out, "; \t\n\r\0\x0B");
    }

    private function withSpace(string $out): string
    {
        return str_ends_with($out, ' ') ? $out : $out.' ';
    }

    private function skipString(string $sql, int $start, int $length): int
    {
        $i = $start + 1;

        while ($i < $length) {
            if ($sql[$i] === '\\') {
                $i += 2;

                continue;
            }

            if ($sql[$i] === "'") {
                if (($sql[$i + 1] ?? '') === "'") {
                    $i += 2;

                    continue;
                }

                return $i + 1;
            }

            $i++;
        }

        // Unterminated literal: swallow the rest so nothing after it can leak.
        return $length;
    }

    private function skipQuotedIdentifier(string $sql, int $start, int $length, string $quote): int
    {
        $i = $start + 1;

        while ($i < $length) {
            if ($sql[$i] === $quote) {
                if (($sql[$i + 1] ?? '') === $quote) {
                    $i += 2;

                    continue;
                }

                return $i + 1;
            }

            $i++;
        }

        return $length;
    }

    private function skipNumber(string $sql, int $start, int $length): int
    {
        $i = $start;

        while ($i < $length && ctype_digit($sql[$i])) {
            $i++;
        }

        if (($sql[$i] ?? '') === '.' && ctype_digit($sql[$i + 1] ?? '')) {
            $i++;

            while ($i < $length && ctype_digit($sql[$i])) {
                $i++;
            }
        }

        if (in_array($sql[$i] ?? '', ['e', 'E'], true)) {
            $j = $i + 1;

            if (in_array($sql[$j] ?? '', ['+', '-'], true)) {
                $j++;
            }

            if (ctype_digit($sql[$j] ?? '')) {
                while ($j < $length && ctype_digit($sql[$j])) {
                    $j++;
                }
                $i = $j;
            }
        }

        return $i;
    }

    private function isWordCharacter(string $char): bool
    {
        return $char !== '' && (ctype_alnum($char) || $char === '_' || $char === '$' || $char === '.' || $char === '@' || $char === ':');
    }
}
