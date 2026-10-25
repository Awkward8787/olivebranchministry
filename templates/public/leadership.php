<?php

declare(strict_types=1);

use Obm\Church;
?>
<div class="wrap">
    <h1>Leadership</h1>
    <?php include dirname(__DIR__) . '/partials/portrait.php'; ?>
    <ul class="leaders">
        <?php foreach (Church::LEADERS as $name): ?>
            <li><?= e($name) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
