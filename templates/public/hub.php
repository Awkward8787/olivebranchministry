<?php

declare(strict_types=1);

use Obm\Church;
?>
<div class="hub-band">
    <div class="wrap">
        <h1><?= e(Church::HUB_NAME) ?></h1>
        <p>A community resource center at <?= e(Church::oneLineAddress()) ?>.</p>
    </div>
</div>
<div class="wrap">
    <dl class="facts">
        <dt>Organization</dt>
        <dd><?= e(Church::HUB_LEGAL) ?></dd>
        <dt>Status</dt>
        <dd>A separate 501(c)(3)</dd>
        <dt>EIN</dt>
        <dd><?= e(Church::HUB_EIN) ?></dd>
        <dt>Opened</dt>
        <dd><?= e(Church::HUB_OPENED) ?></dd>
        <dt>Address</dt>
        <dd><?= e(Church::oneLineAddress()) ?></dd>
    </dl>
    <h2>Partners</h2>
    <ul class="partners">
        <?php foreach (Church::HUB_PARTNERS as $partner): ?>
            <li><?= e($partner) ?></li>
        <?php endforeach; ?>
    </ul>
    <h2><?= e(Church::HUB_DRIVE) ?></h2>
    <p><?= e(Church::HUB_NAME) ?> runs a <?= e(Church::HUB_DRIVE) ?> drive.</p>
</div>
