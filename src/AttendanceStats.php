<?php

declare(strict_types=1);

namespace Obm;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use OverflowException;

/**
 * Attendance averages.
 *
 * The average for a period is the arithmetic mean of recorded service headcounts
 * in that period. It is not divided by the number of calendar weeks or months.
 * A headcount of zero is a real record and is included.
 *
 * Rounding is half up (away from zero) to one decimal place, done in integer
 * tenths. 1.25 becomes 1.3. 2/3 becomes 0.7. No binary float is used.
 *
 * When the period has no records the average is null. Callers must not divide
 * by zero. Empty weeks or months inside a trend are reported with a null average.
 *
 * Weeks are Sunday through Saturday in America/New_York. A bucket includes only
 * records whose date falls inside the selected range, so a December 31 count
 * cannot leak into a January report through a week that crosses January 1.
 */
final class AttendanceStats
{
    public static function assertCount(mixed $count): int
    {
        if (!is_int($count) || $count < 0) {
            throw new InvalidArgumentException('Headcount must be a whole number, zero or more.');
        }

        return $count;
    }

    /**
     * @return array{sum:int, records:int, average_tenths:?int, average_display:?string}
     */
    public static function summarize(array $counts): array
    {
        $sum = 0;
        $clean = [];
        foreach ($counts as $count) {
            $count = self::assertCount($count);
            if ($count > PHP_INT_MAX - $sum) {
                throw new OverflowException('Attendance total is too large.');
            }
            $sum += $count;
            $clean[] = $count;
        }
        $tenths = self::averageTenths($sum, count($clean));

        return [
            'sum' => $sum,
            'records' => count($clean),
            'average_tenths' => $tenths,
            'average_display' => $tenths === null ? null : self::formatTenths($tenths),
        ];
    }

    public static function averageTenths(int $sum, int $count): ?int
    {
        if ($sum < 0 || $count < 0) {
            throw new InvalidArgumentException('Attendance figures cannot be negative.');
        }
        if ($count === 0) {
            return null;
        }
        if ($sum > intdiv(PHP_INT_MAX, 10)) {
            throw new OverflowException('Attendance total is too large to average.');
        }
        $numerator = $sum * 10;
        $quotient = intdiv($numerator, $count);
        $remainder = $numerator % $count;
        $twice = $remainder * 2;
        if (!is_int($twice)) {
            throw new OverflowException('Attendance total is too large to average.');
        }
        if ($twice >= $count) {
            $quotient++;
        }

        return $quotient;
    }

    public static function formatTenths(int $tenths): string
    {
        if ($tenths < 0) {
            throw new InvalidArgumentException('Average cannot be negative.');
        }
        $whole = intdiv($tenths, 10);
        $frac = $tenths % 10;

        return $whole . '.' . $frac;
    }

    /**
     * @param list<array{date:string, count:int}> $rows
     * @return list<array{start:string, end:string, sum:int, records:int, average_tenths:?int, average_display:?string}>
     */
    public static function byWeek(array $rows, DateRange $range): array
    {
        $buckets = [];
        $cursor = Periods::weekContaining($range->start);
        while ($cursor->start <= $range->end) {
            $buckets[$cursor->start] = [
                'start' => $cursor->start,
                'end' => $cursor->end,
                'counts' => [],
            ];
            $end = DateTimeImmutable::createFromFormat('!Y-m-d', $cursor->end, new DateTimeZone(Periods::TZ));
            if ($end === false) {
                break;
            }
            $next = Periods::weekContaining($end->modify('+1 day')->format('Y-m-d'));
            if ($next->start === $cursor->start) {
                break;
            }
            $cursor = $next;
        }

        foreach (self::rowsInRange($rows, $range) as $row) {
            $week = Periods::weekContaining($row['date']);
            if (!isset($buckets[$week->start])) {
                continue;
            }
            $buckets[$week->start]['counts'][] = $row['count'];
        }

        return self::finishBuckets($buckets);
    }

    /**
     * @param list<array{date:string, count:int}> $rows
     * @return list<array{start:string, end:string, sum:int, records:int, average_tenths:?int, average_display:?string}>
     */
    public static function byMonth(array $rows, DateRange $range): array
    {
        $buckets = [];
        $cursor = DateRangeMonth::firstOf($range->start);
        while ($cursor->start <= $range->end) {
            $buckets[$cursor->start] = [
                'start' => $cursor->start,
                'end' => $cursor->end,
                'counts' => [],
            ];
            $cursor = DateRangeMonth::next($cursor);
        }

        foreach (self::rowsInRange($rows, $range) as $row) {
            $month = DateRangeMonth::firstOf($row['date']);
            if (!isset($buckets[$month->start])) {
                continue;
            }
            $buckets[$month->start]['counts'][] = $row['count'];
        }

        return self::finishBuckets($buckets);
    }

    /**
     * @param list<array{date:string, count:int}> $rows
     * @return list<array{date:string, count:int}>
     */
    private static function rowsInRange(array $rows, DateRange $range): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!isset($row['date'], $row['count']) || !is_string($row['date'])) {
                throw new InvalidArgumentException('Attendance row is incomplete.');
            }
            if (!Periods::isDate($row['date'])) {
                throw new InvalidArgumentException('Attendance date is not a real calendar date.');
            }
            $count = self::assertCount($row['count']);
            if (!$range->contains($row['date'])) {
                continue;
            }
            $out[] = ['date' => $row['date'], 'count' => $count];
        }

        return $out;
    }

    /**
     * @param array<string, array{start:string, end:string, counts:list<int>}> $buckets
     * @return list<array{start:string, end:string, sum:int, records:int, average_tenths:?int, average_display:?string}>
     */
    private static function finishBuckets(array $buckets): array
    {
        $out = [];
        foreach ($buckets as $bucket) {
            $summary = self::summarize($bucket['counts']);
            $out[] = [
                'start' => $bucket['start'],
                'end' => $bucket['end'],
                'sum' => $summary['sum'],
                'records' => $summary['records'],
                'average_tenths' => $summary['average_tenths'],
                'average_display' => $summary['average_display'],
            ];
        }

        return $out;
    }
}
