<?php

declare(strict_types=1);

namespace Obm\Tests;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Obm\Periods;
use Obm\RangeQuery;
use Obm\ScheduleFormat;
use PHPUnit\Framework\TestCase;

final class PeriodsTest extends TestCase
{
    public function testLeapDayAndNonLeapFebruary(): void
    {
        $this->assertTrue(Periods::isDate('2024-02-29'));
        $this->assertFalse(Periods::isDate('2023-02-29'));
        $this->assertSame('2024-02-29', Periods::month(2024, 2)->end);
        $this->assertSame('2023-02-28', Periods::month(2023, 2)->end);
        $this->assertSame('1900-02-28', Periods::month(1900, 2)->end);
        $this->assertSame('2000-02-29', Periods::month(2000, 2)->end);
        $this->assertFalse(Periods::month(2023, 2)->contains('2023-02-29'));
        $this->assertTrue(Periods::month(2024, 2)->contains('2024-02-29'));
        $this->assertFalse(Periods::isDate('2024-04-31'));
        $this->assertFalse(Periods::isDate('2024-13-01'));
        $this->assertFalse(Periods::isDate('2024-00-01'));
        $this->assertFalse(Periods::isDate('2024-1-01'));
    }

    public function testQuarterAndYearEdges(): void
    {
        $q1 = Periods::quarter(2024, 1);
        $this->assertSame('2024-01-01', $q1->start);
        $this->assertSame('2024-03-31', $q1->end);
        $this->assertTrue($q1->contains('2024-02-29'));
        $this->assertTrue($q1->contains('2024-03-31'));
        $this->assertFalse($q1->contains('2024-04-01'));
        $this->assertFalse(Periods::quarter(2024, 2)->contains('2024-02-29'));

        $this->assertSame('2026-04-01', Periods::quarter(2026, 2)->start);
        $this->assertSame('2026-06-30', Periods::quarter(2026, 2)->end);
        $this->assertFalse(Periods::quarter(2026, 1)->contains('2026-04-01'));
        $this->assertTrue(Periods::quarter(2026, 2)->contains('2026-04-01'));
        $this->assertSame('2026-09-30', Periods::quarter(2026, 3)->end);
        $this->assertTrue(Periods::quarter(2026, 3)->contains('2026-09-30'));
        $this->assertFalse(Periods::quarter(2026, 3)->contains('2026-10-01'));
        $this->assertTrue(Periods::quarter(2026, 4)->contains('2026-10-01'));
        $this->assertSame('2026-12-31', Periods::quarter(2026, 4)->end);

        $year = Periods::year(2024);
        $this->assertTrue($year->contains('2024-02-29'));
        $this->assertTrue($year->contains('2024-12-31'));
        $this->assertFalse($year->contains('2025-01-01'));
        $this->assertTrue(Periods::year(2025)->contains('2025-01-01'));
        $this->assertFalse(Periods::year(2025)->contains('2024-12-31'));
        $this->assertTrue(Periods::month(2024, 12)->contains('2024-12-31'));
        $this->assertFalse(Periods::month(2025, 1)->contains('2024-12-31'));
        $this->assertTrue(Periods::month(2025, 1)->contains('2025-01-01'));
    }

    public function testCustomRangeIsInclusive(): void
    {
        $range = Periods::custom('2025-12-31', '2026-01-01');
        $this->assertTrue($range->contains('2025-12-31'));
        $this->assertTrue($range->contains('2026-01-01'));
        $this->assertFalse($range->contains('2025-12-30'));
        $this->assertFalse($range->contains('2026-01-02'));
        $single = Periods::custom('2024-02-29', '2024-02-29');
        $this->assertTrue($single->contains('2024-02-29'));
        $this->assertFalse($single->contains('2024-02-28'));
    }

    public function testCustomRangeRejectsInvertedAndInvalidDates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Periods::custom('2026-01-02', '2026-01-01');
    }

    public function testQuarterNumberIsRejectedOutsideOneToFour(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Periods::quarter(2026, 0);
    }

    public function testNewYorkIsStillInThePreviousYearWhenUtcHasRolled(): void
    {
        $now = new DateTimeImmutable('2026-01-01 04:30:00', new DateTimeZone('UTC'));
        $month = Periods::currentMonth($now);
        $this->assertSame('2025-12-01', $month->start);
        $this->assertSame('2025-12-31', $month->end);
        $this->assertSame('2025-10-01', Periods::currentQuarter($now)->start);
        $this->assertSame('2025-12-31', Periods::currentQuarter($now)->end);
        $this->assertSame('2025-01-01', Periods::currentYear($now)->start);
        $this->assertSame('2025-12-31', Periods::currentYear($now)->end);
    }

    public function testNewYorkNewYearAfterMidnight(): void
    {
        $now = new DateTimeImmutable('2026-01-01 05:00:00', new DateTimeZone('UTC'));
        $this->assertSame('2026-01-01', Periods::currentMonth($now)->start);
        $this->assertSame('2026-01-31', Periods::currentMonth($now)->end);
        $this->assertSame('2026-01-01', Periods::currentYear($now)->start);
        $this->assertSame('2026-03-31', Periods::currentQuarter($now)->end);
    }

    public function testEasternDaylightMonthBoundary(): void
    {
        $stillJune = new DateTimeImmutable('2026-07-01 03:30:00', new DateTimeZone('UTC'));
        $this->assertSame('2026-06-01', Periods::currentMonth($stillJune)->start);
        $this->assertSame('2026-06-30', Periods::currentMonth($stillJune)->end);
        $july = new DateTimeImmutable('2026-07-01 04:00:00', new DateTimeZone('UTC'));
        $this->assertSame('2026-07-01', Periods::currentMonth($july)->start);
    }

    public function testWeeksAreSundayThroughSaturdayAndCrossTheNewYear(): void
    {
        $october = Periods::weekContaining('2026-10-25');
        $this->assertSame('2026-10-25', $october->start);
        $this->assertSame('2026-10-31', $october->end);

        $newYear = Periods::weekContaining('2026-01-01');
        $this->assertSame('2025-12-28', $newYear->start);
        $this->assertSame('2026-01-03', $newYear->end);
        $this->assertTrue($newYear->contains('2025-12-31'));
        $this->assertTrue($newYear->contains('2026-01-01'));
        $this->assertFalse($newYear->contains('2025-12-27'));
        $this->assertFalse($newYear->contains('2026-01-04'));

        $dst = Periods::weekContaining('2026-03-10');
        $this->assertSame('2026-03-08', $dst->start);
        $this->assertSame('2026-03-14', $dst->end);
        $start = new DateTimeImmutable($dst->start, new DateTimeZone('America/New_York'));
        $this->assertSame('0', $start->format('w'));
    }

    public function testSpringForwardGapIsRejectedAndOffsetsAreExplicit(): void
    {
        $this->assertNull(Periods::tryDateTime('2026-03-08T02:30'));
        $this->assertSame('2026-03-08 03:30:00', Periods::normalizeDateTime('2026-03-08T03:30'));
        $this->assertSame(
            '2026-01-15T18:30:00-05:00',
            Periods::isoOffset('2026-01-15 18:30:00'),
        );
        $this->assertSame(
            '2026-07-15T18:30:00-04:00',
            Periods::isoOffset('2026-07-15 18:30:00'),
        );
        $this->assertSame(
            '2026-11-01T01:30:00-04:00',
            Periods::isoOffset('2026-11-01 01:30:00'),
        );
    }

    public function testClockAndLongDateDoNotDependOnLocale(): void
    {
        $this->assertSame('12:00 AM', ScheduleFormat::clock('00:00:00'));
        $this->assertSame('12:00 PM', ScheduleFormat::clock('12:00'));
        $this->assertSame('9:30 AM', ScheduleFormat::clock('09:30:00'));
        $this->assertSame('6:30 PM', ScheduleFormat::clock('18:30:00'));
        $this->assertSame('11:05 PM', ScheduleFormat::clock('23:05:00'));
        $this->assertSame('10:00 AM–12:00 PM', ScheduleFormat::clockRange('10:00:00', '12:00:00'));
        $this->assertSame('Sunday, October 25, 2026, 11:00 AM', ScheduleFormat::longDateTime('2026-10-25 11:00:00'));
        $this->assertSame('February 29, 2024', ScheduleFormat::longDate('2024-02-29'));
    }

    public function testRangeQueryUsesNewYorkAndInclusiveCustomDates(): void
    {
        $now = new DateTimeImmutable('2026-01-01 04:30:00', new DateTimeZone('UTC'));
        $month = RangeQuery::resolve('nope', '', '', $now);
        $this->assertSame('month', $month['which']);
        $this->assertSame('2025-12-01', $month['range']->start);
        $this->assertSame('2025-12-31', $month['range']->end);

        $custom = RangeQuery::resolve('custom', '2025-12-31', '2026-01-01', $now);
        $this->assertTrue($custom['range']->contains('2025-12-31'));
        $this->assertTrue($custom['range']->contains('2026-01-01'));
        $this->assertNull(RangeQuery::resolve('custom', '2026-02-01', '2026-01-01', $now)['range']);
        $this->assertNull(RangeQuery::resolve('custom', '2023-02-29', '2023-03-01', $now)['range']);
    }
}
