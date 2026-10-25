<?php

declare(strict_types=1);

namespace Obm;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Recorded gifts are append-only. A void or an adjustment is a new row.
 * A calendar year stays open to staff through January 31 of the next year.
 * After that, only an administrator can record a gift, a void, or an adjustment.
 *
 * The first-release schema inserts the Hub fund as id 4 with code "hub".
 * Later code matches that flag, not the fund's display name.
 */
final class GiftPolicy
{
    public const CHURCH = 'church';

    public const UNCONFIRMED = 'unconfirmed';

    public const HUB_CODE = 'hub';

    public const HUB_SEED_ID = 4;

    public const CLOSED_YEAR = 'That year is closed to staff. Ask an administrator.';

    public static function yearClosedToStaff(string $giftDate, DateTimeImmutable $today): bool
    {
        if (!Periods::isDate($giftDate)) {
            return true;
        }
        $giftYear = (int) substr($giftDate, 0, 4);
        $close = sprintf('%04d-01-31', $giftYear + 1);
        $todayDate = $today->setTimezone(new DateTimeZone(Periods::TZ))->format('Y-m-d');

        return $todayDate > $close;
    }

    public static function canWriteYear(string $role, string $giftDate, DateTimeImmutable $today): bool
    {
        if ($role === 'admin') {
            return true;
        }
        if ($role !== 'staff') {
            return false;
        }

        return !self::yearClosedToStaff($giftDate, $today);
    }

    public static function closedYearMessage(string $role, string $giftDate, DateTimeImmutable $today): ?string
    {
        if (self::canWriteYear($role, $giftDate, $today)) {
            return null;
        }

        return self::CLOSED_YEAR;
    }
}
