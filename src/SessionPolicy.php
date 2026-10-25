<?php

declare(strict_types=1);

namespace Obm;

/**
 * Idle limit is 4 hours. Absolute limit is 12 hours.
 * A session at exactly the limit is expired. Corrupt timestamps are expired.
 */
final class SessionPolicy
{
    public const IDLE_SECONDS = 14400;

    public const ABSOLUTE_SECONDS = 43200;

    public static function expired(int $seenAt, int $issuedAt, int $now): bool
    {
        if ($seenAt > $now || $issuedAt > $now || $issuedAt > $seenAt) {
            return true;
        }
        if (($now - $seenAt) >= self::IDLE_SECONDS) {
            return true;
        }
        if (($now - $issuedAt) >= self::ABSOLUTE_SECONDS) {
            return true;
        }

        return false;
    }
}
