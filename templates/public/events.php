<?php

declare(strict_types=1);

use Obm\ScheduleFormat;
?>
<div class="wrap prose">
    <h1>Events</h1>
    <?php if ($events === []): ?>
        <p>No upcoming events are posted.</p>
    <?php else: ?>
        <?php foreach ($events as $event): ?>
            <article>
                <h2><?= e($event['title']) ?></h2>
                <p><?= e(ScheduleFormat::longDateTime((string) $event['starts_at'])) ?><?php if (!empty($event['ends_at'])): ?> – <?= e(ScheduleFormat::longDateTime((string) $event['ends_at'])) ?><?php endif; ?></p>
                <?php if (!empty($event['location'])): ?>
                    <p><?= e($event['location']) ?></p>
                <?php endif; ?>
                <p><?= e_nl($event['summary']) ?></p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
