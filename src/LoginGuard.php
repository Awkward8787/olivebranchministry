<?php

declare(strict_types=1);

namespace Obm;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Eight failed sign-ins for the same email or address within 15 minutes
 * block another attempt. The window is America/New_York wall time.
 */
final class LoginGuard
{
    public const MAX_FAILURES = 8;

    public const WINDOW_MINUTES = 15;

    public static function windowStart(DateTimeImmutable $now): string
    {
        $local = $now->setTimezone(new DateTimeZone(Periods::TZ));

        return $local->modify('-' . self::WINDOW_MINUTES . ' minutes')->format('Y-m-d H:i:s');
    }

    public static function blocked(int $failures): bool
    {
        if ($failures < 0) {
            throw new InvalidArgumentException('Failure count cannot be negative.');
        }

        return $failures >= self::MAX_FAILURES;
    }
}
