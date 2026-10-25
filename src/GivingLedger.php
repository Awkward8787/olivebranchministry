<?php

declare(strict_types=1);

namespace Obm;

use InvalidArgumentException;
use RuntimeException;

/**
 * Ledger math over integer cents.
 *
 * Identity for any inclusive date range:
 *   sum of per-fund totals
 *   = ledger total
 *   = sum of named donor statement totals + anonymous total
 *
 * Anonymous gifts stay in the ledger and in their fund. They are not placed
 * on a person's year-end statement. When every gift names a donor, the sum of
 * statement totals equals the ledger total.
 */
final class GivingLedger
{
    /**
     * @param array<string, mixed> $gift
     * @return array{
     *   id:?int,
     *   fund:string,
     *   cents:int,
     *   date:string,
     *   donor_id:?int,
     *   method:?string,
     *   reference:?string,
     *   note:?string,
     *   goods_or_services:?string
     * }
     */
    public static function normalize(array $gift): array
    {
        foreach (['cents', 'date', 'fund', 'donor_id'] as $key) {
            if (!array_key_exists($key, $gift)) {
                throw new InvalidArgumentException('Gift is missing ' . $key . '.');
            }
        }
        $fund = $gift['fund'];
        if (!is_string($fund) || $fund === '') {
            throw new InvalidArgumentException('Gift fund is missing.');
        }
        $donor = $gift['donor_id'];
        if ($donor !== null && (!is_int($donor) || $donor <= 0)) {
            throw new InvalidArgumentException('Donor id must be a positive integer or null.');
        }
        if (!Periods::isDate((string) $gift['date'])) {
            throw new InvalidArgumentException('Gift date is not a real calendar date.');
        }

        return [
            'id' => isset($gift['id']) && is_int($gift['id']) ? $gift['id'] : null,
            'fund' => $fund,
            'cents' => Money::assertCents($gift['cents']),
            'date' => (string) $gift['date'],
            'donor_id' => $donor,
            'method' => isset($gift['method']) && is_string($gift['method']) ? $gift['method'] : null,
            'reference' => isset($gift['reference']) && is_string($gift['reference']) && $gift['reference'] !== '' ? $gift['reference'] : null,
            'note' => isset($gift['note']) && is_string($gift['note']) && $gift['note'] !== '' ? $gift['note'] : null,
            'goods_or_services' => isset($gift['goods_or_services']) && is_string($gift['goods_or_services']) && trim($gift['goods_or_services']) !== ''
                ? trim($gift['goods_or_services'])
                : null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $gifts
     * @param list<string> $fundNames
     * @return array{
     *   total:int,
     *   by_fund:array<string,int>,
     *   fund_total:int,
     *   donor_statement_total:int,
     *   anonymous_total:int,
     *   by_donor:array<int,int>,
     *   count:int,
     *   balanced:bool
     * }
     */
    public static function reconcile(array $gifts, DateRange $range, array $fundNames = []): array
    {
        $byFund = [];
        foreach ($fundNames as $name) {
            if (!is_string($name) || $name === '') {
                throw new InvalidArgumentException('Fund name is missing.');
            }
            $byFund[$name] = 0;
        }

        $byDonor = [];
        $all = [];
        $anonymous = [];
        $count = 0;

        foreach ($gifts as $gift) {
            $row = self::normalize($gift);
            if (!$range->contains($row['date'])) {
                continue;
            }
            $count++;
            if (!array_key_exists($row['fund'], $byFund)) {
                $byFund[$row['fund']] = 0;
            }
            $byFund[$row['fund']] = Money::sum([$byFund[$row['fund']], $row['cents']]);
            $all[] = $row['cents'];
            if ($row['donor_id'] === null) {
                $anonymous[] = $row['cents'];
                continue;
            }
            $byDonor[$row['donor_id']] = Money::sum([$byDonor[$row['donor_id']] ?? 0, $row['cents']]);
        }

        $total = Money::sum($all);
        $fundTotal = Money::sum(array_values($byFund));
        $donorTotal = Money::sum(array_values($byDonor));
        $anonymousTotal = Money::sum($anonymous);
        $balanced = $fundTotal === $total && Money::sum([$donorTotal, $anonymousTotal]) === $total;
        if (!$balanced) {
            throw new RuntimeException('Giving totals did not reconcile.');
        }

        return [
            'total' => $total,
            'by_fund' => $byFund,
            'fund_total' => $fundTotal,
            'donor_statement_total' => $donorTotal,
            'anonymous_total' => $anonymousTotal,
            'by_donor' => $byDonor,
            'count' => $count,
            'balanced' => true,
        ];
    }

    /**
     * @param list<array<string, mixed>> $gifts
     */
    public static function donorTotal(array $gifts, int $donorId, DateRange $range): int
    {
        $report = self::reconcile($gifts, $range);

        return $report['by_donor'][$donorId] ?? 0;
    }
}
