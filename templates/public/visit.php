<?php

declare(strict_types=1);

use Obm\Church;
?>
<div class="wrap">
    <h1>Plan your visit</h1>
    <?php include dirname(__DIR__) . '/partials/services.php'; ?>
    <h2>Where we meet</h2>
    <address>
        <?= e(Church::NAME) ?><br>
        <?= e(Church::STREET) ?><br>
        <?= e(Church::cityLine()) ?>
    </address>
    <p><a href="tel:<?= e(Church::PHONE_TEL) ?>"><?= e(Church::PHONE_DISPLAY) ?></a></p>
    <p><a href="<?= e($mapLink) ?>">Open the address in Google Maps</a></p>
    <p><button type="button" data-load-map>Show the map</button></p>
    <div id="map-slot" data-map="<?= e($mapEmbed) ?>"></div>
</div>
