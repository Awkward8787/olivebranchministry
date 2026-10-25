<?php

declare(strict_types=1);

namespace Obm;

/**
 * Year-end contribution statements.
 *
 * The goods-or-services sentence is the standard acknowledgment:
 * no goods or services were provided, unless a gift records that field.
 * The church EIN is printed only when configured, and The Hub's EIN is refused
 * even if it is pasted into church config. The Hub is a separate organization.
 */
final class Statements
{
    public const HUB_EIN_DIGITS = '415032500';

    public const NO_GOODS = 'No goods or services were provided in exchange for these contributions.';

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
            $noted[] = (string) $line['date'] . ': ' . $goods;
        }
        if ($noted === []) {
            return [self::NO_GOODS];
        }
        if (count($noted) === count($lines)) {
            return array_merge(
                ['Goods or services were provided in exchange for these contributions, as noted below.'],
                $noted,
            );
        }

        return array_merge(
            ['No goods or services were provided in exchange for these contributions, except as noted below.'],
            $noted,
        );
    }
}
