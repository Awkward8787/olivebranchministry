<?php

declare(strict_types=1);

use Obm\ScheduleFormat;

if (($mode ?? 'list') === 'list'):
?>
<h1>Service times</h1>
<p>Active services are shown on the public site. A service that is not weekly, such as the fourth Sunday evening service, is listed but is not treated as every week.</p>
<p><a class="button" href="/admin/services/new">Add a service time</a></p>
<div class="table-wrap">
    <table class="stack">
        <thead>
            <tr><th scope="col">Service</th><th scope="col">When</th><th scope="col">Shown</th></tr>
        </thead>
        <tbody>
            <?php foreach ($services as $service): ?>
                <tr>
                    <td data-label="Service"><a href="/admin/services/<?= (int) $service['id'] ?>"><?= e($service['name']) ?></a></td>
                    <td data-label="When"><?= e(ScheduleFormat::sentence($service)) ?></td>
                    <td data-label="Shown"><?= (int) $service['active'] === 1 ? 'Active' : 'Hidden' ?><?= (int) $service['weekly'] === 1 ? '' : ', not weekly' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else:
    $form = $form ?? [];
    $errors = $errors ?? [];
    $action = ($serviceId ?? 0) === 0 ? '/admin/services/new' : '/admin/services/' . (int) $serviceId;
?>
<h1><?= ($serviceId ?? 0) === 0 ? 'New service time' : 'Service time' ?></h1>
<p><a href="/admin/services">Back to service times</a></p>
<form method="post" action="<?= e($action) ?>" accept-charset="UTF-8">
    <?= $csrfField ?>
    <label>
        <span>Name</span>
        <input name="name" required maxlength="120" value="<?= e($form['name'] ?? '') ?>">
    </label>
    <?php if (isset($errors['name'])): ?><p class="error" role="alert"><?= e($errors['name']) ?></p><?php endif; ?>
    <label>
        <span>Day</span>
        <select name="weekday">
            <?php foreach ($weekdays as $number => $label): ?>
                <option value="<?= (int) $number ?>"<?= selected_if($form['weekday'] ?? '', (string) $number) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if (isset($errors['weekday'])): ?><p class="error" role="alert"><?= e($errors['weekday']) ?></p><?php endif; ?>
    <label>
        <span>Start</span>
        <input type="time" name="start_time" required value="<?= e($form['start_time'] ?? '') ?>">
    </label>
    <?php if (isset($errors['start_time'])): ?><p class="error" role="alert"><?= e($errors['start_time']) ?></p><?php endif; ?>
    <label>
        <span>End</span>
        <input type="time" name="end_time" value="<?= e($form['end_time'] ?? '') ?>">
    </label>
    <?php if (isset($errors['end_time'])): ?><p class="error" role="alert"><?= e($errors['end_time']) ?></p><?php endif; ?>
    <label>
        <span>Note</span>
        <input name="note" maxlength="180" value="<?= e($form['note'] ?? '') ?>">
    </label>
    <?php if (isset($errors['note'])): ?><p class="error" role="alert"><?= e($errors['note']) ?></p><?php endif; ?>
    <label>
        <span>Sort order</span>
        <input name="sort_order" inputmode="numeric" value="<?= e($form['sort_order'] ?? '0') ?>">
    </label>
    <?php if (isset($errors['sort_order'])): ?><p class="error" role="alert"><?= e($errors['sort_order']) ?></p><?php endif; ?>
    <label class="check">
        <input type="checkbox" name="active" value="1"<?= checked_if(($form['active'] ?? '') === '1') ?>>
        <span>Active on the public site</span>
    </label>
    <label class="check">
        <input type="checkbox" name="weekly" value="1"<?= checked_if(($form['weekly'] ?? '') === '1') ?>>
        <span>Every week</span>
    </label>
    <button type="submit">Save service time</button>
</form>
<?php endif; ?>
