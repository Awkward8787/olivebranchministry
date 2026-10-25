<?php

declare(strict_types=1);

namespace Obm;

use DateTimeImmutable;
use DateTimeZone;

final class Clock
{
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(Periods::TZ));
    }
}
