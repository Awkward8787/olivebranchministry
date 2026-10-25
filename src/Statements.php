<?php

declare(strict_types=1);

namespace Obm;

/**
 * Year-end contribution statements.
 *
 * When no gift records goods or services, the acknowledgment is the
 * single sentence from IRS Publication 1771. A gift that records goods
 * or services also needs a good-faith estimate. The church EIN is printed
 * only when configured, and the Hub EIN is refused even if it is pasted
 * into church config.
 */
final class Statements
{
    public const HUB_EIN_DIGITS = '415032500';

    public const NO_GOODS = 'No goods or services were provided other than intangible religious benefits.';

    public const INTANGIBLE_BESIDES = 'Other than the goods or services estimated above, intangible religious benefits were provided. Those benefits are not usually sold in a commercial transaction.';

    public static function statementEin(?string $configured): ?string
    {
        $configured = trim((string) $configured);
        if ($configured === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $configured);
        if ($digits === self::HUB_EIN_DIGITS) {
            return null;
        }

        return $configured;
    }

    /**
     * @param list<array<string, mixed>> $gifts
     * @return array{lines:list<array<string, mixed>>, total_cents:int, language:list<string>}
     */
    public static function forDonor(array $gifts, int $donorId, DateRange $range): array
    {
        if ($donorId <= 0) {
            throw new \InvalidArgumentException('Donor id must be a positive integer.');
        }
        $lines = [];
        foreach ($gifts as $gift) {
            $row = GivingLedger::normalize($gift);
            if ($row['donor_id'] !== $donorId || !$range->contains($row['date'])) {
                continue;
            }
            if ($row['receiving_org'] !== GiftPolicy::CHURCH || $row['cents'] === 0) {
                continue;
            }
            $lines[] = $row;
        }
        usort($lines, static function (array $a, array $b): int {
            if ($a['date'] === $b['date']) {
                return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
            }

            return $a['date'] <=> $b['date'];
        });

        return [
            'lines' => $lines,
            'total_cents' => self::total($lines),
            'language' => self::contributionLanguage($lines),
        ];
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    public static function total(array $lines): int
    {
        $cents = [];
        foreach ($lines as $line) {
            $cents[] = Money::assertCents($line['cents']);
        }

        return Money::sum($cents);
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return list<string>
     */
    public static function contributionLanguage(array $lines): array
    {
        if ($lines === []) {
            return [];
        }
        $noted = [];
        foreach ($lines as $line) {
            $goods = trim((string) ($line['goods_or_services'] ?? ''));
            if ($goods === '') {
                continue;
            }
            if (!array_key_exists('goods_value_cents', $line) || $line['goods_value_cents'] === null) {
                $giftId = $line['id'] ?? null;
                throw new GoodsEstimateMissing(is_int($giftId) ? $giftId : null);
            }
            $value = Money::assertCents($line['goods_value_cents']);
            $noted[] = ScheduleFormat::longDate((string) $line['date']) . ': ' . $goods . '. Good-faith estimate ' . Money::format($value) . '.';
        }
        if ($noted === []) {
            return [self::NO_GOODS];
        }
        if (count($noted) === count($lines)) {
            return array_merge(
                ['Goods or services were provided in exchange for these contributions, as noted below.'],
                $noted,
                [self::INTANGIBLE_BESIDES],
            );
        }

        return array_merge(
            ['No goods or services were provided in exchange for these contributions, except as noted below.'],
            $noted,
            [self::INTANGIBLE_BESIDES],
        );
    }
}
