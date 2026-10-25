<?php

declare(strict_types=1);

namespace Obm;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Recorded gifts are append-only. A void or an adjustment is a new row.
 * A calendar year stays open to staff through January 31 of the next year.
 * After that, only an administrator can post a void or an adjustment.
 */
final class GiftPolicy
{
    public const CHURCH = 'church';

    public const UNCONFIRMED = 'unconfirmed';

    public static function refusesInPlaceAmountChange(string $role, int $fromCents, int $toCents): bool
    {
        if ($role !== 'admin' && $role !== 'staff') {
            return true;
        }
        Money::assertCents($fromCents);
        Money::assertCents($toCents);

        return true;
    }

    public static function newGiftUsesActiveFund(bool $active): bool
    {
        return $active;
    }

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

    public static function canPostAdjustment(string $role, string $giftDate, DateTimeImmutable $today): bool
    {
        if ($role === 'admin') {
            return true;
        }
        if ($role !== 'staff') {
            return false;
        }

        return !self::yearClosedToStaff($giftDate, $today);
    }
}
