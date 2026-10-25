<?php

declare(strict_types=1);

namespace Obm\Tests;

use Obm\AttendanceStats;
use Obm\Periods;
use PHPUnit\Framework\TestCase;

final class AttendanceStatsTest extends TestCase
{
    public function testRoundingIsHalfUpToOneDecimalAndZeroRecordsDoNotDivide(): void
    {
        $empty = AttendanceStats::summarize([]);
        $this->assertNull($empty['average_tenths']);
        $this->assertNull($empty['average_display']);
        $this->assertSame(0, $empty['sum']);
        $this->assertNull(AttendanceStats::averageTenths(0, 0));

        $this->assertSame('0.0', AttendanceStats::summarize([0])['average_display']);
        $this->assertSame(15, AttendanceStats::averageTenths(3, 2));
        $this->assertSame('1.5', AttendanceStats::formatTenths(15));
        $this->assertSame(23, AttendanceStats::averageTenths(7, 3));
        $this->assertSame('2.3', AttendanceStats::formatTenths(23));
        $this->assertSame(13, AttendanceStats::averageTenths(5, 4));
        $this->assertSame('1.3', AttendanceStats::formatTenths(13));
        $this->assertSame(17, AttendanceStats::averageTenths(5, 3));
        $this->assertSame('1.7', AttendanceStats::formatTenths(17));
        $this->assertSame(3, AttendanceStats::averageTenths(1, 3));
        $this->assertSame('0.3', AttendanceStats::formatTenths(3));
        $this->assertSame(7, AttendanceStats::averageTenths(2, 3));
        $this->assertSame(1, AttendanceStats::averageTenths(1, 8));
        $this->assertSame('0.1', AttendanceStats::formatTenths(1));
        $this->assertSame(1, AttendanceStats::averageTenths(1, 20));
        $this->assertSame('0.1', AttendanceStats::formatTenths(AttendanceStats::averageTenths(1, 20)));
        $this->assertSame(20, AttendanceStats::averageTenths(39, 20));
        $this->assertSame('2.0', AttendanceStats::formatTenths(20));
        $this->assertSame('5.0', AttendanceStats::summarize([0, 10])['average_display']);
        $this->assertSame('10.0', AttendanceStats::summarize([10, 10])['average_display']);
    }

    public function testAverageIsMeanOfRecordedServicesNotCalendarSlots(): void
    {
        $summary = AttendanceStats::summarize([10, 10]);
        $this->assertSame(20, $summary['sum']);
        $this->assertSame(2, $summary['records']);
        $this->assertSame('10.0', $summary['average_display']);
    }

    public function testOctober2026DemoCountsAverageTo29Point3(): void
    {
        $counts = [48, 22, 51, 45, 16, 14, 9];
        $summary = AttendanceStats::summarize($counts);
        $this->assertSame(205, $summary['sum']);
        $this->assertSame(7, $summary['records']);
        $this->assertSame(293, $summary['average_tenths']);
        $this->assertSame('29.3', $summary['average_display']);
    }

    public function testWeekCrossingNewYearDoesNotPullOutsideDatesIntoTheRange(): void
    {
        $rows = [
            ['date' => '2025-12-31', 'count' => 10],
            ['date' => '2026-01-01', 'count' => 20],
            ['date' => '2025-12-27', 'count' => 99],
        ];
        $range = Periods::custom('2025-12-31', '2026-01-01');
        $weeks = AttendanceStats::byWeek($rows, $range);
        $this->assertCount(1, $weeks);
        $this->assertSame('2025-12-28', $weeks[0]['start']);
        $this->assertSame('2026-01-03', $weeks[0]['end']);
        $this->assertSame(30, $weeks[0]['sum']);
        $this->assertSame(2, $weeks[0]['records']);
        $this->assertSame('15.0', $weeks[0]['average_display']);

        $januaryOnly = AttendanceStats::byWeek($rows, Periods::custom('2026-01-01', '2026-01-01'));
        $this->assertSame(20, $januaryOnly[0]['sum']);
        $this->assertSame(1, $januaryOnly[0]['records']);
        $this->assertSame('20.0', $januaryOnly[0]['average_display']);
    }

    public function testMonthBucketsSplitDecember31FromJanuary1AndLeaveGapsNull(): void
    {
        $rows = [
            ['date' => '2025-12-31', 'count' => 8],
            ['date' => '2026-01-01', 'count' => 4],
            ['date' => '2025-12-30', 'count' => 100],
            ['date' => '2026-03-01', 'count' => 2],
        ];
        $months = AttendanceStats::byMonth($rows, Periods::custom('2025-12-31', '2026-03-15'));
        $this->assertCount(4, $months);
        $this->assertSame('2025-12-01', $months[0]['start']);
        $this->assertSame(8, $months[0]['sum']);
        $this->assertSame(1, $months[0]['records']);
        $this->assertSame(4, $months[1]['sum']);
        $this->assertSame('2026-02-01', $months[2]['start']);
        $this->assertSame('2026-02-28', $months[2]['end']);
        $this->assertSame(0, $months[2]['records']);
        $this->assertNull($months[2]['average_tenths']);
        $this->assertSame(2, $months[3]['sum']);

        $leap = AttendanceStats::byMonth(
            [['date' => '2024-02-29', 'count' => 6]],
            Periods::month(2024, 2),
        );
        $this->assertSame('2024-02-29', $leap[0]['end']);
        $this->assertSame(6, $leap[0]['sum']);
        $this->assertSame('6.0', $leap[0]['average_display']);
    }

    public function testSundayBoundaryBelongsToThatWeekOnly(): void
    {
        $rows = [
            ['date' => '2026-10-25', 'count' => 3],
            ['date' => '2026-10-31', 'count' => 5],
            ['date' => '2026-11-01', 'count' => 7],
        ];
        $weeks = AttendanceStats::byWeek($rows, Periods::custom('2026-10-25', '2026-11-01'));
        $this->assertSame('2026-10-25', $weeks[0]['start']);
        $this->assertSame('2026-10-31', $weeks[0]['end']);
        $this->assertSame(8, $weeks[0]['sum']);
        $this->assertSame('4.0', $weeks[0]['average_display']);
        $this->assertSame(7, $weeks[1]['sum']);
        $this->assertSame('2026-11-01', $weeks[1]['start']);
    }
}
