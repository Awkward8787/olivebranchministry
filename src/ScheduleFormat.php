<?php

declare(strict_types=1);

namespace Obm;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class ScheduleFormat
{
    /** @var array<int, string> */
    public const WEEKDAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    /** @var array<int, string> */
    public const MONTHS = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];

    public static function weekday(int $day): string
    {
        if (!isset(self::WEEKDAYS[$day])) {
            throw new InvalidArgumentException('Weekday must be 0 through 6.');
        }

        return self::WEEKDAYS[$day];
    }

    public static function clock(string $time): string
    {
        if (preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $time, $m) !== 1) {
            throw new InvalidArgumentException('Invalid time.');
        }
        $hour = (int) $m[1];
        $minute = (int) $m[2];
        if ($hour > 23 || $minute > 59) {
            throw new InvalidArgumentException('Invalid time.');
        }
        $suffix = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour % 12;
        if ($hour12 === 0) {
            $hour12 = 12;
        }

        return $hour12 . ':' . $m[2] . ' ' . $suffix;
    }

    public static function clockRange(string $start, ?string $end): string
    {
        $text = self::clock($start);
        if ($end === null || $end === '') {
            return $text;
        }

        return $text . '–' . self::clock($end);
    }

    /**
     * @param array{name:string, weekday:int, start_time:string, end_time?:?string, note?:?string} $service
     */
    public static function sentence(array $service): string
    {
        $parts = [
            (string) $service['name'],
            self::weekday((int) $service['weekday']),
            self::clockRange((string) $service['start_time'], isset($service['end_time']) ? (string) $service['end_time'] : null),
        ];
        if (!empty($service['note'])) {
            $parts[] = (string) $service['note'];
        }

        return implode(', ', $parts) . '.';
    }

    public static function longDate(string $ymd): string
    {
        Periods::assertDate($ymd);
        $year = (int) substr($ymd, 0, 4);
        $month = (int) substr($ymd, 5, 2);
        $day = (int) substr($ymd, 8, 2);

        return self::MONTHS[$month] . ' ' . $day . ', ' . $year;
    }

    public static function longDateTime(string $stored): string
    {
        $stored = Periods::normalizeDateTime($stored);
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $stored, new DateTimeZone(Periods::TZ));
        if ($dt === false) {
            throw new InvalidArgumentException('Enter a real date and time.');
        }

        return self::weekday((int) $dt->format('w'))
            . ', '
            . self::longDate(substr($stored, 0, 10))
            . ', '
            . self::clock(substr($stored, 11, 8));
    }
}
