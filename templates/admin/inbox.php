<?php

declare(strict_types=1);

use Obm\ScheduleFormat;
?>
<h1>Inbox</h1>
<?php if (!empty($confirmDelete)): ?>
    <form method="post" action="/admin/inbox" accept-charset="UTF-8">
        <?= $csrfField ?>
        <input type="hidden" name="type" value="<?= e((string) $confirmDelete['type']) ?>">
        <input type="hidden" name="id" value="<?= (int) $confirmDelete['id'] ?>">
        <p role="alert">Delete this <?= $confirmDelete['type'] === 'prayer' ? 'prayer request' : 'contact message' ?><?= !empty($confirmDelete['name']) ? ' from ' . e((string) $confirmDelete['name']) : '' ?>? This cannot be undone.</p>
        <p class="confirm-actions">
            <button class="danger" type="submit" name="action" value="confirm-delete">Confirm delete</button>
            <a href="/admin/inbox">Cancel</a>
        </p>
    </form>
<?php endif; ?>
<h2>Contact messages</h2>
<?php if ($handledDays !== null): ?>
    <p class="note">Handled messages and prayer requests are deleted after <?= (int) $handledDays ?> days. That deletion runs when this page is opened.</p>
<?php else: ?>
    <p class="note">Handled messages and prayer requests are kept until the church sets a retention period.</p>
<?php endif; ?>
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
                    <button type="submit" name="action" value="handle">Mark handled</button>
                <?php else: ?>
                    <p class="note">Handled <?= e(ScheduleFormat::longDateTime((string) $item['handled_at'])) ?></p>
                    <button class="quiet" type="submit" name="action" value="reopen">Reopen</button>
                <?php endif; ?>
                <button class="quiet" type="submit" name="action" value="delete">Delete</button>
            </form>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
<?php if (($contactPage ?? 1) > 1 || !empty($contactsMore)): ?>
    <p>
        <?php if (($contactPage ?? 1) > 1): ?><a href="/admin/inbox?contact_page=<?= (int) $contactPage - 1 ?>&amp;prayer_page=<?= (int) ($prayerPage ?? 1) ?>">Newer messages</a><?php endif; ?>
        <?php if (!empty($contactsMore)): ?> <a href="/admin/inbox?contact_page=<?= (int) ($contactPage ?? 1) + 1 ?>&amp;prayer_page=<?= (int) ($prayerPage ?? 1) ?>">Older messages</a><?php endif; ?>
    </p>
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
                    <button type="submit" name="action" value="handle">Mark handled</button>
                <?php else: ?>
                    <p class="note">Handled <?= e(ScheduleFormat::longDateTime((string) $item['handled_at'])) ?></p>
                    <button class="quiet" type="submit" name="action" value="reopen">Reopen</button>
                <?php endif; ?>
                <button class="quiet" type="submit" name="action" value="delete">Delete</button>
            </form>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
<?php if (($prayerPage ?? 1) > 1 || !empty($prayersMore)): ?>
    <p>
        <?php if (($prayerPage ?? 1) > 1): ?><a href="/admin/inbox?contact_page=<?= (int) ($contactPage ?? 1) ?>&amp;prayer_page=<?= (int) $prayerPage - 1 ?>">Newer requests</a><?php endif; ?>
        <?php if (!empty($prayersMore)): ?> <a href="/admin/inbox?contact_page=<?= (int) ($contactPage ?? 1) ?>&amp;prayer_page=<?= (int) ($prayerPage ?? 1) + 1 ?>">Older requests</a><?php endif; ?>
    </p>
<?php endif; ?>
