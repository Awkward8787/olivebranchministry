<?php

declare(strict_types=1);

namespace Obm;

use InvalidArgumentException;

/**
 * A visitor is awaiting follow-up when the status is new, or when a follow-up
 * date is set and is today or earlier in America/New_York. Members are not
 * included. A contacted visitor with no date, or with a future date, is not waiting.
 */
final class FollowUp
{
    /**
     * @param array{kind?:string, follow_up_status?:string, follow_up_on?:?string} $person
     */
    public static function awaiting(array $person, string $today): bool
    {
        if (!Periods::isDate($today)) {
            throw new InvalidArgumentException('Enter a real calendar date.');
        }
        if (($person['kind'] ?? '') !== 'visitor') {
            return false;
        }
        if (($person['follow_up_status'] ?? '') === 'new') {
            return true;
        }
        $date = $person['follow_up_on'] ?? null;
        if (!is_string($date) || $date === '') {
            return false;
        }
        if (!Periods::isDate($date)) {
            return false;
        }

        return $date <= $today;
    }
}
