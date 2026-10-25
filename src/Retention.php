<?php

declare(strict_types=1);

namespace Obm;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Handled inbox rows are kept until the church sets privacy.handled_days.
 * A missing, blank, or non-numeric value does not delete anything.
 */
final class Retention
{
    public static function handledDays(mixed $configured): ?int
    {
        if (is_int($configured)) {
            $days = $configured;
        } elseif (is_string($configured) && preg_match('/^[1-9]\d{0,4}$/', trim($configured)) === 1) {
            $days = (int) $configured;
        } else {
            return null;
        }
        if ($days < 1 || $days > 36500) {
            return null;
        }

        return $days;
    }

    public static function cutoff(DateTimeImmutable $now, int $days): string
    {
        if ($days < 1) {
            throw new InvalidArgumentException('Retention days must be at least 1.');
        }

        return $now->setTimezone(new DateTimeZone(Periods::TZ))
            ->modify('-' . $days . ' days')
            ->format('Y-m-d H:i:s');
    }
}
