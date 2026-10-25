<?php

declare(strict_types=1);

use Obm\ScheduleFormat;

$byName = [];
foreach ($services as $service) {
    $byName[$service['name']] = $service;
}
?>
<div class="wrap prose">
    <h1>Ministries</h1>
    <section>
        <h2>Men's Ministry</h2>
    </section>
    <section>
        <h2>Youth Ministry</h2>
        <?php if (isset($byName['Bible Study & Youth'])): ?>
            <p><?= e(ScheduleFormat::sentence($byName['Bible Study & Youth'])) ?></p>
        <?php endif; ?>
    </section>
    <section>
        <h2>Prayer &amp; Fasting</h2>
        <?php if (isset($byName['Prayer & Fasting'])): ?>
            <p><?= e(ScheduleFormat::sentence($byName['Prayer & Fasting'])) ?></p>
        <?php endif; ?>
    </section>
</div>
