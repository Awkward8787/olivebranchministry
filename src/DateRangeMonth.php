<?php

declare(strict_types=1);

namespace Obm;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Month stepping used by attendance trends. Kept separate so week iteration
 * does not reimplement leap-year month length.
 */
final class DateRangeMonth
{
    public static function firstOf(string $date): DateRange
    {
        Periods::assertDate($date);
        $year = (int) substr($date, 0, 4);
        $month = (int) substr($date, 5, 2);

        return Periods::month($year, $month);
    }

    public static function next(DateRange $month): DateRange
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $month->start, new DateTimeZone(Periods::TZ));
        if ($dt === false) {
            throw new \InvalidArgumentException('Enter a real calendar date.');
        }
        $next = $dt->modify('first day of next month');

        return Periods::month((int) $next->format('Y'), (int) $next->format('n'));
    }
}
