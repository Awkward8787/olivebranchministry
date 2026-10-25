<?php

declare(strict_types=1);

namespace Obm;

use InvalidArgumentException;

/**
 * Inclusive calendar-date range. Both ends are real Y-m-d dates in America/New_York.
 */
final class DateRange
{
    public function __construct(
        public readonly string $start,
        public readonly string $end,
    ) {
        if (!Periods::isDate($start) || !Periods::isDate($end)) {
            throw new InvalidArgumentException('Date range needs real calendar dates.');
        }
        if ($start > $end) {
            throw new InvalidArgumentException('The start date is after the end date.');
        }
    }

    public function contains(string $date): bool
    {
        if (!Periods::isDate($date)) {
            return false;
        }

        return $date >= $this->start && $date <= $this->end;
    }
}
