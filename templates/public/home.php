<?php

declare(strict_types=1);

use Obm\Church;

include dirname(__DIR__) . '/partials/banner.php';
?>
<div class="wrap home-grid">
    <section>
        <h2>Mission</h2>
        <blockquote class="mission"><?= e(Church::MISSION) ?></blockquote>
        <p class="actions">
            <a class="button" href="/plan-your-visit">Plan your visit</a>
            <a class="quiet" href="/give">Give</a>
            <a class="quiet" href="/the-hub">The Hub</a>
            <a class="quiet" href="/contact">Contact</a>
        </p>
    </section>
    <section>
        <?php include dirname(__DIR__) . '/partials/services.php'; ?>
        <p><?= e(Church::STREET) ?><br><?= e(Church::cityLine()) ?></p>
    </section>
</div>
