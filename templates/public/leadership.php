<?php

declare(strict_types=1);

use Obm\Church;
?>
<div class="wrap">
    <h1>Leadership</h1>
    <?php include dirname(__DIR__) . '/partials/portrait.php'; ?>
    <ul class="leaders">
        <?php foreach (Church::LEADERS as $leader): ?>
            <li>
                <span class="leader-name"><?= e($leader['name']) ?></span>
                <span class="role"><?= e($leader['role']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
