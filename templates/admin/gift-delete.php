<?php

declare(strict_types=1);

use Obm\Money;
use Obm\ScheduleFormat;
?>
<h1>Delete gift</h1>
<p><?= e(ScheduleFormat::longDate($gift['date'])) ?>, <?= e($gift['donor_name'] ?? 'Anonymous') ?>, <?= e($gift['fund']) ?>, <?= e(Money::format($gift['cents'])) ?>.</p>
<form method="post" action="/admin/giving/<?= (int) $gift['id'] ?>/delete" accept-charset="UTF-8">
    <?= $csrfField ?>
    <input type="hidden" name="confirm" value="yes">
    <button class="danger" type="submit">Delete gift</button>
    <a class="button quiet" href="/admin/giving/<?= (int) $gift['id'] ?>">Keep it</a>
</form>
