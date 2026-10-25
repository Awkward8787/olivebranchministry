<?php

declare(strict_types=1);

use Obm\Church;
?>
<div class="wrap prose">
    <h1>About</h1>
    <blockquote class="mission"><?= e(Church::MISSION) ?></blockquote>
    <p><?= e(Church::NAME) ?> meets at <?= e(Church::oneLineAddress()) ?>.</p>
</div>
