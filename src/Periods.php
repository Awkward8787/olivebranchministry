<?php

declare(strict_types=1);

namespace Obm;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Calendar periods for the church. Dates are America/New_York wall dates.
 * Month, quarter, year, and custom ranges are inclusive on both ends.
 */
final class Periods
{
    public const TZ = 'America/New_York';

    public static function isDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone(self::TZ));
        $errors = DateTimeImmutable::getLastErrors();
        if ($dt === false) {
            return false;
        }
        if (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
            return false;
        }

        return $dt->format('Y-m-d') === $value;
    }

    public static function assertDate(string $value): string
    {
        if (!self::isDate($value)) {
            throw new InvalidArgumentException('Enter a real calendar date.');
        }

        return $value;
    }

    public static function month(int $year, int $month): DateRange
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Month must be 1 through 12.');
        }
        if ($year < 1900 || $year > 9999) {
            throw new InvalidArgumentException('Year is out of range.');
        }
        $start = sprintf('%04d-%02d-01', $year, $month);
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $start, new DateTimeZone(self::TZ));
        if ($dt === false) {
            throw new InvalidArgumentException('Enter a real calendar date.');
        }
        $end = $dt->modify('last day of this month')->format('Y-m-d');

        return new DateRange($start, $end);
    }

    public static function quarter(int $year, int $quarter): DateRange
    {
        if ($quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException('Quarter must be 1 through 4.');
        }
        $startMonth = (($quarter - 1) * 3) + 1;

        return new DateRange(
            self::month($year, $startMonth)->start,
            self::month($year, $startMonth + 2)->end,
        );
    }

    public static function year(int $year): DateRange
    {
        return new DateRange(self::month($year, 1)->start, self::month($year, 12)->end);
    }

    public static function custom(string $start, string $end): DateRange
    {
        return new DateRange($start, $end);
    }

    public static function currentMonth(DateTimeImmutable $now): DateRange
    {
        $local = $now->setTimezone(new DateTimeZone(self::TZ));

        return self::month((int) $local->format('Y'), (int) $local->format('n'));
    }

    public static function currentQuarter(DateTimeImmutable $now): DateRange
    {
        $local = $now->setTimezone(new DateTimeZone(self::TZ));
        $month = (int) $local->format('n');

        return self::quarter((int) $local->format('Y'), intdiv($month - 1, 3) + 1);
    }

    public static function currentYear(DateTimeImmutable $now): DateRange
    {
        $local = $now->setTimezone(new DateTimeZone(self::TZ));

        return self::year((int) $local->format('Y'));
    }

    /**
     * Sunday through Saturday, inclusive, containing the given date.
     */
    public static function weekContaining(string $date): DateRange
    {
        self::assertDate($date);
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone(self::TZ));
        if ($dt === false) {
            throw new InvalidArgumentException('Enter a real calendar date.');
        }
        $dow = (int) $dt->format('w');
        $start = $dt->modify('-' . $dow . ' days');

        return new DateRange($start->format('Y-m-d'), $start->modify('+6 days')->format('Y-m-d'));
    }

    /**
     * Store a New York wall-clock time. Rejects dates that do not exist,
     * including the spring-forward gap (02:00 to 02:59).
     */
    public static function normalizeDateTime(string $value): string
    {
        $value = trim(str_replace('T', ' ', $value));
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
            $value .= ':00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) !== 1) {
            throw new InvalidArgumentException('Enter a real date and time.');
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone(self::TZ));
        $errors = DateTimeImmutable::getLastErrors();
        if ($dt === false) {
            throw new InvalidArgumentException('Enter a real date and time.');
        }
        if (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
            throw new InvalidArgumentException('Enter a real date and time.');
        }
        if ($dt->format('Y-m-d H:i:s') !== $value) {
            throw new InvalidArgumentException('That time does not exist in America/New_York.');
        }

        return $dt->format('Y-m-d H:i:s');
    }

    public static function tryDateTime(string $value): ?string
    {
        try {
            return self::normalizeDateTime($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public static function isoOffset(string $stored): string
    {
        $stored = self::normalizeDateTime($stored);
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $stored, new DateTimeZone(self::TZ));
        if ($dt === false) {
            throw new InvalidArgumentException('Enter a real date and time.');
        }

        return $dt->format('c');
    }
}
