<?php

declare(strict_types=1);

namespace Obm;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Handled inbox rows are deleted after this many days.
 * 90 is the default in config.example.php. The church can change it.
 */
final class Retention
{
    public const DEFAULT_HANDLED_DAYS = 90;

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
