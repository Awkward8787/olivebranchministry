<?php

declare(strict_types=1);

namespace Obm\Tests;

use InvalidArgumentException;
use DateTimeImmutable;
use DateTimeZone;
use Obm\GiftPolicy;
use Obm\GivingLedger;
use Obm\Money;
use Obm\Periods;
use Obm\Statements;
use PHPUnit\Framework\TestCase;

final class GivingLedgerTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function gifts(): array
    {
        return [
            $this->gift(1, 'General', '100.00', '2024-01-01', 1),
            $this->gift(2, 'Tithes', '250.50', '2024-02-29', 1),
            $this->gift(3, 'Offering', '5.00', '2024-03-31', 2),
            $this->gift(4, 'The Hub', '20.00', '2024-04-01', null, null, null, 'unconfirmed'),
            $this->gift(5, 'Building', '1000.00', '2024-12-31', 2, 'Dinner provided', 2500),
            $this->gift(6, 'General', '1.00', '2025-01-01', 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gift(
        int $id,
        string $fund,
        string $amount,
        string $date,
        ?int $donor,
        ?string $goods = null,
        ?int $goodsValue = null,
        string $org = 'church',
    ): array {
        return [
            'id' => $id,
            'fund' => $fund,
            'cents' => Money::parseCents($amount),
            'date' => $date,
            'donor_id' => $donor,
            'goods_or_services' => $goods,
            'goods_value_cents' => $goodsValue,
            'receiving_org' => $org,
            'method' => 'check',
            'reference' => (string) $id,
        ];
    }

    public function testYearTotalsReconcileIncludingLeapDayAndDecember31(): void
    {
        $funds = ['General', 'Tithes', 'Offering', 'The Hub', 'Building'];
        $report = GivingLedger::reconcile($this->gifts(), Periods::year(2024), $funds);

        $this->assertTrue($report['balanced']);
        $this->assertSame(137550, $report['total']);
        $this->assertSame(137550, $report['fund_total']);
        $this->assertSame(Money::sum(array_values($report['by_fund'])), $report['total']);
        $this->assertSame(10000, $report['by_fund']['General']);
        $this->assertSame(25050, $report['by_fund']['Tithes']);
        $this->assertSame(500, $report['by_fund']['Offering']);
        $this->assertSame(2000, $report['by_fund']['The Hub']);
        $this->assertSame(100000, $report['by_fund']['Building']);
        $this->assertSame(35050, $report['by_donor'][1]);
        $this->assertSame(100500, $report['by_donor'][2]);
        $this->assertSame(135550, $report['donor_statement_total']);
        $this->assertSame(2000, $report['anonymous_total']);
        $this->assertSame(
            $report['total'],
            Money::sum([$report['donor_statement_total'], $report['anonymous_total']]),
        );
        $this->assertSame(5, $report['count']);
    }

    public function testStatementTotalsEqualTheDonorBucketsAndExcludeTheOtherYear(): void
    {
        $gifts = $this->gifts();
        $year = Periods::year(2024);
        $report = GivingLedger::reconcile($gifts, $year, ['General', 'Tithes', 'Offering', 'The Hub', 'Building']);
        $sum = 0;
        foreach ([1, 2] as $donorId) {
            $built = Statements::forDonor($gifts, $donorId, $year);
            $this->assertSame(GivingLedger::donorTotal($gifts, $donorId, $year), $built['total_cents']);
            $this->assertSame($built['total_cents'], Statements::total($built['lines']));
            $sum = Money::sum([$sum, $built['total_cents']]);
        }
        $this->assertSame($report['donor_statement_total'], $sum);
        $this->assertSame($report['total'], Money::sum([$sum, $report['anonymous_total']]));

        $next = Statements::forDonor($gifts, 1, Periods::year(2025));
        $this->assertSame(100, $next['total_cents']);
        $this->assertSame(['2025-01-01'], array_column($next['lines'], 'date'));
    }

    public function testNamedGiftsMakeStatementSumEqualTheLedger(): void
    {
        $gifts = [
            $this->gift(1, 'Tithes', '10.00', '2024-12-31', 1),
            $this->gift(2, 'Offering', '0.01', '2024-02-29', 2),
        ];
        $report = GivingLedger::reconcile($gifts, Periods::year(2024), ['Tithes', 'Offering', 'General']);
        $this->assertSame(0, $report['anonymous_total']);
        $this->assertSame($report['total'], $report['fund_total']);
        $this->assertSame($report['total'], $report['donor_statement_total']);
        $this->assertSame(1001, $report['total']);
        $this->assertSame(0, $report['by_fund']['General']);
    }

    public function testBoundariesAreInclusiveAndQuartersDoNotShareAnEdge(): void
    {
        $gifts = $this->gifts();
        $feb = GivingLedger::reconcile($gifts, Periods::month(2024, 2));
        $this->assertSame(25050, $feb['total']);
        $march = GivingLedger::reconcile($gifts, Periods::month(2024, 3));
        $this->assertSame(500, $march['total']);
        $q1 = GivingLedger::reconcile($gifts, Periods::quarter(2024, 1));
        $this->assertSame(35550, $q1['total']);
        $this->assertFalse(Periods::quarter(2024, 1)->contains('2024-04-01'));
        $q2 = GivingLedger::reconcile($gifts, Periods::quarter(2024, 2));
        $this->assertSame(2000, $q2['total']);

        $dec = GivingLedger::reconcile($gifts, Periods::month(2024, 12));
        $this->assertSame(100000, $dec['total']);
        $jan = GivingLedger::reconcile($gifts, Periods::month(2025, 1));
        $this->assertSame(100, $jan['total']);

        $span = GivingLedger::reconcile($gifts, Periods::custom('2024-12-31', '2025-01-01'));
        $this->assertSame(100100, $span['total']);
        $this->assertSame(2, $span['count']);
    }

    public function testEmptyRangeReconcilesToZero(): void
    {
        $report = GivingLedger::reconcile([], Periods::year(2024), ['General', 'Tithes']);
        $this->assertTrue($report['balanced']);
        $this->assertSame(0, $report['total']);
        $this->assertSame(0, $report['fund_total']);
        $this->assertSame(0, $report['donor_statement_total']);
        $this->assertSame(0, $report['anonymous_total']);
        $this->assertSame(0, $report['by_fund']['General']);
    }

    public function testStringInputsForTheDemoShapeReconcile(): void
    {
        $rows = [
            ['fund' => 'Tithes', 'amount' => '500.00', 'date' => '2026-01-01', 'donor_id' => 1],
            ['fund' => 'Offering', 'amount' => '40.25', 'date' => '2026-02-28', 'donor_id' => 1],
            ['fund' => 'General', 'amount' => '150.00', 'date' => '2026-10-04', 'donor_id' => 1],
            ['fund' => 'The Hub', 'amount' => '75.10', 'date' => '2026-10-18', 'donor_id' => 1, 'receiving_org' => 'unconfirmed'],
            ['fund' => 'General', 'amount' => '25.00', 'date' => '2026-10-11', 'donor_id' => null],
            ['fund' => 'Building', 'amount' => '100.00', 'date' => '2026-10-20', 'donor_id' => 2, 'goods_or_services' => 'meal', 'goods_value_cents' => 1500],
            ['fund' => 'Tithes', 'amount' => '300.00', 'date' => '2025-12-31', 'donor_id' => 1],
            ['fund' => 'General', 'amount' => '50.00', 'date' => '2027-01-01', 'donor_id' => 1],
            ['fund' => 'Tithes', 'amount' => '18.00', 'date' => '2024-02-29', 'donor_id' => 1],
        ];
        $gifts = [];
        foreach ($rows as $i => $row) {
            $gifts[] = [
                'id' => $i + 1,
                'fund' => $row['fund'],
                'cents' => Money::parseCents($row['amount']),
                'date' => $row['date'],
                'donor_id' => $row['donor_id'],
                'goods_or_services' => $row['goods_or_services'] ?? null,
                'goods_value_cents' => $row['goods_value_cents'] ?? null,
                'receiving_org' => $row['receiving_org'] ?? 'church',
            ];
        }
        $year = GivingLedger::reconcile($gifts, Periods::year(2026), ['General', 'Tithes', 'Offering', 'The Hub', 'Building']);
        $this->assertSame(89035, $year['total']);
        $this->assertSame(76535, $year['by_donor'][1]);
        $this->assertSame(10000, $year['by_donor'][2]);
        $this->assertSame(2500, $year['anonymous_total']);
        $this->assertSame('$890.35', Money::format($year['total']));
        $church = Statements::forDonor($gifts, 1, Periods::year(2026));
        $this->assertSame(69025, $church['total_cents']);
        $this->assertSame('$690.25', Money::format($church['total_cents']));
        $this->assertNotContains('The Hub', array_column($church['lines'], 'fund'));
        $this->assertSame($year['by_donor'][1], Money::sum([$church['total_cents'], 7510]));
        $octoberChurch = Statements::forDonor($gifts, 1, Periods::month(2026, 10));
        $this->assertSame(15000, $octoberChurch['total_cents']);

        $october = GivingLedger::reconcile($gifts, Periods::month(2026, 10));
        $this->assertSame(35010, $october['total']);
        $this->assertSame('$350.10', Money::format($october['total']));

        $this->assertSame(1800, GivingLedger::reconcile($gifts, Periods::month(2024, 2))['total']);
        $this->assertSame(30000, GivingLedger::reconcile($gifts, Periods::custom('2025-12-31', '2025-12-31'))['total']);
        $this->assertSame(0, GivingLedger::reconcile($gifts, Periods::year(2026))['by_fund']['Tithes'] === 50000 ? 0 : 1);
        $this->assertSame(50000, $year['by_fund']['Tithes']);
    }

    public function testInvalidDonorAndFloatCentsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GivingLedger::reconcile([
            ['fund' => 'General', 'cents' => 10.5, 'date' => '2026-01-01', 'donor_id' => null],
        ], Periods::year(2026));
    }

    public function testDatabaseShapedStringCentsStillReconcile(): void
    {
        $gifts = [[
            'id' => 1,
            'fund' => 'General',
            'cents' => Money::fromDatabase('10050'),
            'date' => '2024-12-31',
            'donor_id' => 4,
        ]];
        $report = GivingLedger::reconcile($gifts, Periods::year(2024));
        $this->assertSame(10050, $report['total']);
        $this->assertSame(10050, Statements::forDonor($gifts, 4, Periods::year(2024))['total_cents']);
    }

    public function testStaffCannotRewriteAFiveHundredDollarGiftToOneDollar(): void
    {
        $from = Money::parseCents('500.00');
        $to = Money::parseCents('1.00');
        $this->assertSame(50000, $from);
        $this->assertSame(100, $to);
        $net = GivingLedger::netCents($from, [
            ['kind' => 'adjustment', 'amount_cents' => -49900],
        ]);
        $this->assertSame($to, $net);
    }

    public function testVoidAndAdjustmentNetsStillReconcile(): void
    {
        $original = Money::parseCents('500.00');
        $this->assertSame(40000, GivingLedger::netCents($original, [
            ['kind' => 'adjustment', 'amount_cents' => -10000],
        ]));
        $this->assertSame(0, GivingLedger::netCents($original, [
            ['kind' => 'adjustment', 'amount_cents' => -10000],
            ['kind' => 'void', 'amount_cents' => -40000],
        ]));
        $gifts = [
            [
                'id' => 1,
                'fund' => 'Tithes',
                'cents' => 0,
                'original_cents' => $original,
                'date' => '2026-01-01',
                'donor_id' => 1,
                'voided' => true,
                'receiving_org' => 'church',
            ],
            $this->gift(2, 'General', '1.00', '2026-01-02', 1),
        ];
        $report = GivingLedger::reconcile($gifts, Periods::year(2026), ['Tithes', 'General']);
        $this->assertTrue($report['balanced']);
        $this->assertSame(100, $report['total']);
        $this->assertSame(100, $report['by_fund']['General']);
        $this->assertSame(0, $report['by_fund']['Tithes']);
        $statement = Statements::forDonor($gifts, 1, Periods::year(2026));
        $this->assertSame(100, $statement['total_cents']);
        $this->assertCount(1, $statement['lines']);
    }

    public function testClosedYearsAreLockedToAdministrators(): void
    {
        $zone = new DateTimeZone('America/New_York');
        $jan31 = new DateTimeImmutable('2026-01-31 23:59:00', $zone);
        $feb1 = new DateTimeImmutable('2026-02-01 00:00:00', $zone);
        $this->assertFalse(GiftPolicy::yearClosedToStaff('2025-12-31', $jan31));
        $this->assertTrue(GiftPolicy::yearClosedToStaff('2025-12-31', $feb1));
        $this->assertFalse(GiftPolicy::yearClosedToStaff('2026-06-01', $feb1));
        $this->assertTrue(GiftPolicy::yearClosedToStaff('2024-01-01', $jan31));
        $this->assertTrue(GiftPolicy::canWriteYear('admin', '2024-01-01', $feb1));
        $this->assertFalse(GiftPolicy::canWriteYear('staff', '2024-01-01', $feb1));
        $this->assertTrue(GiftPolicy::canWriteYear('staff', '2025-06-01', $jan31));
        $this->assertNull(GiftPolicy::closedYearMessage('staff', '2025-06-01', $jan31));
    }

    public function testStaffCannotRecordANewGiftInAClosedYear(): void
    {
        $zone = new DateTimeZone('America/New_York');
        $october = new DateTimeImmutable('2026-10-25 12:00:00', $zone);
        $this->assertFalse(GiftPolicy::canWriteYear('staff', '2025-06-01', $october));
        $this->assertSame(
            GiftPolicy::CLOSED_YEAR,
            GiftPolicy::closedYearMessage('staff', '2025-06-01', $october),
        );
        $this->assertTrue(GiftPolicy::canWriteYear('admin', '2025-06-01', $october));
        $this->assertNull(GiftPolicy::closedYearMessage('admin', '2025-06-01', $october));
        $this->assertTrue(GiftPolicy::canWriteYear('staff', '2026-06-01', $october));
        $this->assertNull(GiftPolicy::closedYearMessage('staff', '2026-06-01', $october));
    }
}
