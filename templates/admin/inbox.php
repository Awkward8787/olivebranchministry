<?php

declare(strict_types=1);

use Obm\ScheduleFormat;
?>
<h1>Inbox</h1>
<h2>Contact messages</h2>
<?php if ($contacts === []): ?>
    <p>No messages.</p>
<?php else: ?>
    <?php foreach ($contacts as $item): ?>
        <article>
            <h3><?= e($item['name']) ?></h3>
            <p><?= e(ScheduleFormat::longDateTime((string) $item['created_at'])) ?>
                <?php if (!empty($item['email'])): ?> · <a href="mailto:<?= e($item['email']) ?>"><?= e($item['email']) ?></a><?php endif; ?>
                <?php if (!empty($item['phone'])): ?> · <?= e($item['phone']) ?><?php endif; ?>
            </p>
            <p><?= e_nl($item['message']) ?></p>
            <form method="post" action="/admin/inbox" accept-charset="UTF-8">
                <?= $csrfField ?>
                <input type="hidden" name="type" value="contact">
                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                <?php if (empty($item['handled_at'])): ?>
                    <input type="hidden" name="handled" value="1">
                    <button type="submit">Mark handled</button>
                <?php else: ?>
                    <input type="hidden" name="handled" value="0">
                    <p class="note">Handled <?= e(ScheduleFormat::longDateTime((string) $item['handled_at'])) ?></p>
                    <button class="quiet" type="submit">Reopen</button>
                <?php endif; ?>
            </form>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<h2>Prayer requests</h2>
<p>Prayer requests stay in this office. They are not on the public site.</p>
<?php if ($prayers === []): ?>
    <p>No prayer requests.</p>
<?php else: ?>
    <?php foreach ($prayers as $item): ?>
        <article>
            <h3><?= e($item['name']) ?></h3>
            <p><?= e(ScheduleFormat::longDateTime((string) $item['created_at'])) ?>
                <?php if (!empty($item['email'])): ?> · <?= e($item['email']) ?><?php endif; ?>
                <?php if (!empty($item['phone'])): ?> · <?= e($item['phone']) ?><?php endif; ?>
            </p>
            <p><?= e_nl($item['request']) ?></p>
            <form method="post" action="/admin/inbox" accept-charset="UTF-8">
                <?= $csrfField ?>
                <input type="hidden" name="type" value="prayer">
                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                <?php if (empty($item['handled_at'])): ?>
                    <input type="hidden" name="handled" value="1">
                    <button type="submit">Mark handled</button>
                <?php else: ?>
                    <input type="hidden" name="handled" value="0">
                    <p class="note">Handled <?= e(ScheduleFormat::longDateTime((string) $item['handled_at'])) ?></p>
                    <button class="quiet" type="submit">Reopen</button>
                <?php endif; ?>
            </form>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
