<?php

declare(strict_types=1);

namespace Obm;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Public forms allow 5 submissions per hour per network and form.
 * The sixth is blocked. The window is 3600 real seconds, so a year boundary
 * does not reset it early or late. At exactly 3600 seconds the window resets.
 */
final class RateWindow
{
    public const MAX_HITS = 5;

    public const WINDOW_SECONDS = 3600;

    /**
     * 64-character key for the rate_limits.bucket column. The form name is mixed
     * into the hash so the stored value is not the raw IP.
     */
    public static function formBucket(string $kind, string $ip): string
    {
        if ($kind === '' || $ip === '') {
            throw new InvalidArgumentException('Rate-limit bucket needs a form and a network.');
        }

        return hash('sha256', $kind . '|' . $ip);
    }

    /**
     * @return array{hits:int, blocked:bool, window_start:string}
     */
    public static function next(?int $hits, ?string $windowStart, DateTimeImmutable $now): array
    {
        $local = $now->setTimezone(new DateTimeZone(Periods::TZ));
        $expired = true;
        if ($hits !== null && $hits >= 0 && $windowStart !== null) {
            $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $windowStart, new DateTimeZone(Periods::TZ));
            if ($start instanceof DateTimeImmutable && $start->format('Y-m-d H:i:s') === $windowStart) {
                $elapsed = $local->getTimestamp() - $start->getTimestamp();
                $expired = $elapsed >= self::WINDOW_SECONDS;
            }
        }
        if ($expired) {
            return [
                'hits' => 1,
                'blocked' => false,
                'window_start' => $local->format('Y-m-d H:i:s'),
            ];
        }

        $hits = (int) $hits + 1;

        return [
            'hits' => $hits,
            'blocked' => $hits > self::MAX_HITS,
            'window_start' => (string) $windowStart,
        ];
    }
}
