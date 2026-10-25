<?php

declare(strict_types=1);

use Obm\Church;
use Obm\ScheduleFormat;

$byName = [];
foreach ($services as $service) {
    $byName[$service['name']] = $service;
}
?>
<div class="wrap prose">
    <h1>Ministries</h1>
    <ul>
        <?php foreach (Church::MINISTRIES as $name): ?>
            <?php if ($name === "Men's Ministry"): ?>
                <li><?= e($name) ?></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
    <section>
        <h2>Youth Ministry</h2>
        <?php if (isset($byName['Bible Study & Youth'])): ?>
            <p><?= e(ScheduleFormat::when($byName['Bible Study & Youth'])) ?></p>
        <?php endif; ?>
    </section>
    <section>
        <h2>Prayer &amp; Fasting</h2>
        <?php if (isset($byName['Prayer & Fasting'])): ?>
            <p><?= e(ScheduleFormat::when($byName['Prayer & Fasting'])) ?></p>
        <?php endif; ?>
    </section>
</div>
