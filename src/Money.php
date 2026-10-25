<?php

declare(strict_types=1);

namespace Obm;

use InvalidArgumentException;
use OverflowException;

/**
 * Money is integer cents. User input is parsed from strings. Floats are rejected.
 */
final class Money
{
    public static function parseCents(string $input): int
    {
        $s = trim($input);
        $s = str_replace(['$', ' ', "\u{00A0}"], '', $s);
        if ($s === '') {
            throw new InvalidArgumentException('Enter a dollar amount with up to two decimal places.');
        }

        if (preg_match('/^\.(\d{1,2})$/', $s, $match) === 1) {
            $dollars = '0';
            $fraction = $match[1];
        } elseif (preg_match('/^(\d+|\d{1,3}(?:,\d{3})+)(?:\.(\d{1,2}))?$/', $s, $match) === 1) {
            $dollars = str_replace(',', '', $match[1]);
            $fraction = $match[2] ?? '';
        } else {
            throw new InvalidArgumentException('Enter a dollar amount with up to two decimal places.');
        }

        if (strlen($fraction) === 1) {
            $fraction .= '0';
        } elseif ($fraction === '') {
            $fraction = '00';
        }

        return self::intFromDigits($dollars . $fraction);
    }

    public static function assertCents(mixed $cents): int
    {
        if (!is_int($cents)) {
            throw new InvalidArgumentException('Amounts must be integer cents.');
        }
        if ($cents < 0) {
            throw new InvalidArgumentException('Amounts cannot be negative.');
        }

        return $cents;
    }

    public static function fromDatabase(mixed $value): int
    {
        if (is_int($value)) {
            return self::assertCents($value);
        }
        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return self::intFromDigits($value);
        }

        throw new InvalidArgumentException('Invalid cents from the database.');
    }

    public static function fromSignedDatabase(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/^-?\d+$/', $value) !== 1) {
            throw new InvalidArgumentException('Invalid cents from the database.');
        }
        $negative = str_starts_with($value, '-');
        $digits = $negative ? substr($value, 1) : $value;
        $amount = self::fromDatabase($digits === '' ? '0' : $digits);

        return $negative ? -$amount : $amount;
    }

    /**
     * @param list<int> $amounts
     */
    public static function sum(array $amounts): int
    {
        $total = 0;
        foreach ($amounts as $amount) {
            $amount = self::assertCents($amount);
            if ($amount > PHP_INT_MAX - $total) {
                throw new OverflowException('The total exceeds the integer range.');
            }
            $total += $amount;
        }

        return $total;
    }

    public static function format(int $cents): string
    {
        $negative = $cents < 0;
        $raw = $negative ? substr((string) $cents, 1) : (string) $cents;
        $raw = str_pad($raw, 3, '0', STR_PAD_LEFT);
        $centsPart = substr($raw, -2);
        $dollars = ltrim(substr($raw, 0, -2), '0');
        if ($dollars === '') {
            $dollars = '0';
        }
        $withCommas = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $dollars);
        if (!is_string($withCommas)) {
            $withCommas = $dollars;
        }

        return ($negative ? '-$' : '$') . $withCommas . '.' . $centsPart;
    }

    public static function toInput(int $cents): string
    {
        $cents = self::assertCents($cents);
        $raw = str_pad((string) $cents, 3, '0', STR_PAD_LEFT);
        $dollars = ltrim(substr($raw, 0, -2), '0');
        if ($dollars === '') {
            $dollars = '0';
        }

        return $dollars . '.' . substr($raw, -2);
    }

    private static function intFromDigits(string $digits): int
    {
        if (preg_match('/^\d+$/', $digits) !== 1) {
            throw new InvalidArgumentException('Enter a dollar amount with up to two decimal places.');
        }
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 0;
        }
        $max = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($max) || (strlen($digits) === strlen($max) && $digits > $max)) {
            throw new InvalidArgumentException('That amount is too large.');
        }

        return (int) $digits;
    }
}
