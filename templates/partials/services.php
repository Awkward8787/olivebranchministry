<?php

declare(strict_types=1);

use Obm\ScheduleFormat;
?>
<table class="schedule stack">
    <caption>Service times</caption>
    <thead>
        <tr>
            <th scope="col">Service</th>
            <th scope="col">Day</th>
            <th scope="col">Time</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($services as $service): ?>
            <tr>
                <td data-label="Service"><?= e($service['name']) ?></td>
                <td data-label="Day"><?= e(ScheduleFormat::weekday((int) $service['weekday'])) ?><?php if (!empty($service['note'])): ?>, <?= e($service['note']) ?><?php endif; ?></td>
                <td data-label="Time"><?= e(ScheduleFormat::clockRange((string) $service['start_time'], $service['end_time'] ?? null)) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
