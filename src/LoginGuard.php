<?php

declare(strict_types=1);

namespace Obm;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Eight failed sign-ins for the same email and address within 15 minutes
 * block that pair. A separate cap of 40 failures blocks one address from
 * spraying passwords. Failures against an email from somewhere else do not
 * lock the office. The window is America/New_York wall time.
 */
final class LoginGuard
{
    public const MAX_FAILURES = 8;

    public const MAX_IP_FAILURES = 40;

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

    public static function ipBlocked(int $failures): bool
    {
        if ($failures < 0) {
            throw new InvalidArgumentException('Failure count cannot be negative.');
        }

        return $failures >= self::MAX_IP_FAILURES;
    }

    public static function locked(int $pairFailures, int $ipFailures): bool
    {
        return self::blocked($pairFailures) || self::ipBlocked($ipFailures);
    }
}
