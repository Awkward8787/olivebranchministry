<?php

declare(strict_types=1);

use Obm\ScheduleFormat;

if (($mode ?? 'list') === 'list'):
?>
<h1>Events</h1>
<p>Only published events with a start time from today onward appear on the public site.</p>
<p><a class="button" href="/admin/events/new">Add an event</a></p>
<?php if ($events === []): ?>
    <p>No events.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="stack">
            <thead>
                <tr><th scope="col">When</th><th scope="col">Title</th><th scope="col">On the site</th></tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td data-label="When"><?= e(ScheduleFormat::longDateTime((string) $event['starts_at'])) ?></td>
                        <td data-label="Title"><a href="/admin/events/<?= (int) $event['id'] ?>"><?= e($event['title']) ?></a></td>
                        <td data-label="On the site"><?= (int) $event['published'] === 1 ? 'Published' : 'Hidden' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php else:
    $form = $form ?? [];
    $errors = $errors ?? [];
    $action = ($eventId ?? 0) === 0 ? '/admin/events/new' : '/admin/events/' . (int) $eventId;
?>
<h1><?= ($eventId ?? 0) === 0 ? 'New event' : 'Event' ?></h1>
<p><a href="/admin/events">Back to events</a></p>
<form method="post" action="<?= e($action) ?>" accept-charset="UTF-8">
    <?= $csrfField ?>
    <label>
        <span>Title</span>
        <input name="title" required maxlength="180" value="<?= e($form['title'] ?? '') ?>"<?= isset($errors['title']) ? ' aria-invalid="true" aria-describedby="err-title"' : '' ?>>
    </label>
    <?php if (isset($errors['title'])): ?><p class="error" id="err-title" role="alert"><?= e($errors['title']) ?></p><?php endif; ?>
    <label>
        <span>Summary</span>
        <textarea name="summary" required maxlength="5000"><?= e($form['summary'] ?? '') ?></textarea>
    </label>
    <?php if (isset($errors['summary'])): ?><p class="error" role="alert"><?= e($errors['summary']) ?></p><?php endif; ?>
    <label>
        <span>Starts</span>
        <input type="datetime-local" name="starts_at" required value="<?= e($form['starts_at'] ?? '') ?>"<?= isset($errors['starts_at']) ? ' aria-invalid="true" aria-describedby="err-start"' : '' ?>>
    </label>
    <?php if (isset($errors['starts_at'])): ?><p class="error" id="err-start" role="alert"><?= e($errors['starts_at']) ?></p><?php endif; ?>
    <label>
        <span>Ends</span>
        <input type="datetime-local" name="ends_at" value="<?= e($form['ends_at'] ?? '') ?>">
    </label>
    <?php if (isset($errors['ends_at'])): ?><p class="error" role="alert"><?= e($errors['ends_at']) ?></p><?php endif; ?>
    <label>
        <span>Location</span>
        <input name="location" maxlength="180" value="<?= e($form['location'] ?? '') ?>">
    </label>
    <?php if (isset($errors['location'])): ?><p class="error" role="alert"><?= e($errors['location']) ?></p><?php endif; ?>
    <label class="check">
        <input type="checkbox" name="published" value="1"<?= checked_if(($form['published'] ?? '') === '1') ?>>
        <span>Published on the public site</span>
    </label>
    <button type="submit">Save event</button>
</form>
<?php if (($eventId ?? 0) > 0): ?>
    <form method="post" action="<?= e($action) ?>" accept-charset="UTF-8">
        <?= $csrfField ?>
        <button class="danger" type="submit" name="delete" value="1">Delete event</button>
    </form>
<?php endif; ?>
<?php endif; ?>
