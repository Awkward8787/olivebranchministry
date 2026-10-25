<?php

declare(strict_types=1);

namespace Obm;

/**
 * A contribution line names goods or services and has no good-faith estimate.
 */
final class GoodsEstimateMissing extends \RuntimeException
{
    public function __construct(public readonly ?int $giftId)
    {
        $label = $giftId !== null && $giftId > 0 ? 'Gift #' . $giftId : 'A gift';
        parent::__construct($label . ' has a goods note but no good-faith estimate.');
    }
}
