<?php

declare(strict_types=1);

use Obm\Church;
?>
<div class="wrap prose">
    <h1>Give</h1>
    <p>Give in person at a service, or mail a check to:</p>
    <address>
        <?= e(Church::NAME) ?><br>
        <?= e(Church::STREET) ?><br>
        <?= e(Church::cityLine()) ?>
    </address>
    <p>Make checks payable to <?= e(Church::NAME) ?>.</p>
    <?php if (!empty($onlineUrl)): ?>
        <p><a href="<?= e($onlineUrl) ?>">Give online</a></p>
    <?php endif; ?>
</div>
