<?php

declare(strict_types=1);

namespace Obm;

use DateTimeImmutable;

final class RangeQuery
{
    /**
     * @return array{which:string, range:?DateRange, error:?string}
     */
    public static function resolve(string $which, string $from, string $to, DateTimeImmutable $now): array
    {
        if ($which === 'quarter') {
            return ['which' => 'quarter', 'range' => Periods::currentQuarter($now), 'error' => null];
        }
        if ($which === 'year') {
            return ['which' => 'year', 'range' => Periods::currentYear($now), 'error' => null];
        }
        if ($which === 'custom') {
            if (!Periods::isDate($from) || !Periods::isDate($to)) {
                return ['which' => 'custom', 'range' => null, 'error' => 'Enter a real start and end date.'];
            }
            if ($from > $to) {
                return ['which' => 'custom', 'range' => null, 'error' => 'The start date is after the end date.'];
            }

            return ['which' => 'custom', 'range' => Periods::custom($from, $to), 'error' => null];
        }

        return ['which' => 'month', 'range' => Periods::currentMonth($now), 'error' => null];
    }
}
