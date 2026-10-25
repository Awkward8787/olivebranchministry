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
 * on a person's year-end statement. The ledger total is the sum of net cents
 * (the original gift, plus adjustments, plus voids). A church statement
 * includes only funds the church receives. When every included gift names a
 * donor, those statement totals plus anonymous gifts plus gifts to other
 * organizations equal the ledger.
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
     *   goods_or_services:?string,
     *   goods_value_cents:?int,
     *   receiving_org:string,
     *   original_cents:int,
     *   voided:bool
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
            'goods_value_cents' => self::goodsValue($gift),
            'receiving_org' => self::receivingOrg($gift),
            'original_cents' => array_key_exists('original_cents', $gift)
                ? Money::assertCents($gift['original_cents'])
                : Money::assertCents($gift['cents']),
            'voided' => !empty($gift['voided']),
        ];
    }

    /**
     * Net cents are the original amount plus each adjustment and void.
     * A void row stores the negation of the remaining net.
     *
     * @param list<array{kind:string, amount_cents:int}> $entries
     */
    public static function netCents(int $originalCents, array $entries): int
    {
        $net = Money::assertCents($originalCents);
        foreach ($entries as $entry) {
            $kind = (string) ($entry['kind'] ?? '');
            $delta = $entry['amount_cents'] ?? null;
            if (!is_int($delta)) {
                throw new InvalidArgumentException('Adjustment cents must be an integer.');
            }
            if ($kind === 'void') {
                if ($delta !== -$net) {
                    throw new InvalidArgumentException('A void must reverse the remaining amount.');
                }
                $net = 0;
                continue;
            }
            if ($kind !== 'adjustment') {
                throw new InvalidArgumentException('Unknown gift entry.');
            }
            if ($delta === 0) {
                throw new InvalidArgumentException('An adjustment cannot be zero.');
            }
            $net = self::combine($net, $delta);
        }

        return $net;
    }

    public static function combine(int $original, int $signedDelta): int
    {
        $original = Money::assertCents($original);
        if ($signedDelta >= 0) {
            return Money::sum([$original, $signedDelta]);
        }
        $reduce = -$signedDelta;
        if ($reduce > $original) {
            throw new RuntimeException('A gift net is below zero.');
        }

        return $original - $reduce;
    }

    /** @param array<string, mixed> $gift */
    private static function receivingOrg(array $gift): string
    {
        if (!array_key_exists('receiving_org', $gift) || $gift['receiving_org'] === null || $gift['receiving_org'] === '') {
            return GiftPolicy::CHURCH;
        }
        if (!is_string($gift['receiving_org'])) {
            throw new InvalidArgumentException('Receiving organization is missing.');
        }

        return $gift['receiving_org'] === GiftPolicy::CHURCH ? GiftPolicy::CHURCH : GiftPolicy::UNCONFIRMED;
    }

    /** @param array<string, mixed> $gift */
    private static function goodsValue(array $gift): ?int
    {
        if (!array_key_exists('goods_value_cents', $gift) || $gift['goods_value_cents'] === null || $gift['goods_value_cents'] === '') {
            return null;
        }

        return Money::assertCents($gift['goods_value_cents']);
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
