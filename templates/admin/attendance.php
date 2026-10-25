<?php

declare(strict_types=1);

use Obm\ScheduleFormat;

if (($mode ?? 'list') === 'list'):
?>
<h1>Attendance</h1>
<p class="note">The average is the mean of recorded headcounts in the range, rounded half up to one decimal. It is not divided by the number of weeks. A count of zero is included. Weeks run Sunday through Saturday. A week that crosses the range counts only the records inside the range.</p>
<p><a class="button" href="/admin/attendance/new">Record attendance</a></p>
<?php include dirname(__DIR__) . '/partials/range.php'; ?>
<?php if (!empty($rangeLabel)): ?>
    <p><?= e($rangeLabel) ?></p>
<?php endif; ?>
<?php if (is_array($summary)): ?>
    <?php if ($summary['records'] === 0): ?>
        <p>No attendance recorded.</p>
    <?php else: ?>
        <p><?= (int) $summary['records'] ?> records. Total headcount <?= (int) $summary['sum'] ?>. Average <?= e($summary['average_display']) ?>.</p>
    <?php endif; ?>
<?php endif; ?>
<?php if (!empty($rangeLabel)): ?>
    <h2>By week</h2>
    <div class="table-wrap">
        <table class="stack">
            <thead>
                <tr><th scope="col">Week</th><th class="num" scope="col">Records</th><th class="num" scope="col">Total</th><th class="num" scope="col">Average</th></tr>
            </thead>
            <tbody>
                <?php foreach ($weeks as $bucket): ?>
                    <tr>
                        <td data-label="Week"><?= e(ScheduleFormat::longDate($bucket['start'])) ?> – <?= e(ScheduleFormat::longDate($bucket['end'])) ?></td>
                        <td class="num" data-label="Records"><?= (int) $bucket['records'] ?></td>
                        <td class="num" data-label="Total"><?= (int) $bucket['sum'] ?></td>
                        <td class="num" data-label="Average"><?= $bucket['average_display'] === null ? 'No records' : e($bucket['average_display']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <h2>By month</h2>
    <div class="table-wrap">
        <table class="stack">
            <thead>
                <tr><th scope="col">Month</th><th class="num" scope="col">Records</th><th class="num" scope="col">Total</th><th class="num" scope="col">Average</th></tr>
            </thead>
            <tbody>
                <?php foreach ($months as $bucket): ?>
                    <tr>
                        <td data-label="Month"><?= e(ScheduleFormat::longDate($bucket['start'])) ?></td>
                        <td class="num" data-label="Records"><?= (int) $bucket['records'] ?></td>
                        <td class="num" data-label="Total"><?= (int) $bucket['sum'] ?></td>
                        <td class="num" data-label="Average"><?= $bucket['average_display'] === null ? 'No records' : e($bucket['average_display']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <h2>Records</h2>
    <div class="table-wrap">
        <table class="stack">
            <thead>
                <tr><th scope="col">Date</th><th scope="col">Service</th><th class="num" scope="col">Count</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td data-label="Date"><a href="/admin/attendance/<?= (int) $row['id'] ?>"><?= e(ScheduleFormat::longDate($row['date'])) ?></a></td>
                        <td data-label="Service"><?= e($row['label']) ?></td>
                        <td class="num" data-label="Count"><?= (int) $row['count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php else:
    $form = $form ?? [];
    $errors = $errors ?? [];
    $action = ($recordId ?? 0) === 0 ? '/admin/attendance/new' : '/admin/attendance/' . (int) $recordId;
?>
<h1><?= ($recordId ?? 0) === 0 ? 'Record attendance' : 'Attendance record' ?></h1>
<p><a href="/admin/attendance">Back to attendance</a></p>
<form method="post" action="<?= e($action) ?>" accept-charset="UTF-8">
    <?= $csrfField ?>
    <label>
        <span>Date</span>
        <input type="date" name="date" required value="<?= e($form['date'] ?? '') ?>"<?= isset($errors['date']) ? ' aria-invalid="true" aria-describedby="err-date"' : '' ?>>
    </label>
    <?php if (isset($errors['date'])): ?><p class="error" id="err-date" role="alert"><?= e($errors['date']) ?></p><?php endif; ?>
    <label>
        <span>Service</span>
        <select name="service_id" data-fill-label>
            <option value="">Not linked</option>
            <?php foreach ($services as $service): ?>
                <option value="<?= (int) $service['id'] ?>" data-name="<?= e($service['name']) ?>"<?= selected_if($form['service_id'] ?? '', (string) $service['id']) ?>><?= e($service['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if (isset($errors['service_id'])): ?><p class="error" role="alert"><?= e($errors['service_id']) ?></p><?php endif; ?>
    <label>
        <span>Name on the report</span>
        <input id="attendance-label" name="label" required maxlength="120" value="<?= e($form['label'] ?? '') ?>"<?= isset($errors['label']) ? ' aria-invalid="true" aria-describedby="err-label"' : '' ?>>
    </label>
    <?php if (isset($errors['label'])): ?><p class="error" id="err-label" role="alert"><?= e($errors['label']) ?></p><?php endif; ?>
    <label>
        <span>Headcount</span>
        <input name="count" required inputmode="numeric" value="<?= e($form['count'] ?? '') ?>"<?= isset($errors['count']) ? ' aria-invalid="true" aria-describedby="err-count"' : '' ?>>
    </label>
    <?php if (isset($errors['count'])): ?><p class="error" id="err-count" role="alert"><?= e($errors['count']) ?></p><?php endif; ?>
    <label>
        <span>Note</span>
        <input name="notes" maxlength="500" value="<?= e($form['notes'] ?? '') ?>">
    </label>
    <?php if (isset($errors['notes'])): ?><p class="error" role="alert"><?= e($errors['notes']) ?></p><?php endif; ?>
    <button type="submit">Save attendance</button>
</form>
<?php if (($recordId ?? 0) > 0): ?>
    <form method="post" action="<?= e($action) ?>" accept-charset="UTF-8">
        <?= $csrfField ?>
        <button class="danger" type="submit" name="delete" value="1">Delete record</button>
    </form>
<?php endif; ?>
<?php endif; ?>
