<form class="form-row no-print" method="get" action="" accept-charset="UTF-8">
    <label>
        <span>Period</span>
        <select name="which">
            <option value="month"<?= selected_if($which ?? 'month', 'month') ?>>This month</option>
            <option value="quarter"<?= selected_if($which ?? '', 'quarter') ?>>This quarter</option>
            <option value="year"<?= selected_if($which ?? '', 'year') ?>>This year</option>
            <option value="custom"<?= selected_if($which ?? '', 'custom') ?>>Custom range</option>
        </select>
    </label>
    <label>
        <span>From</span>
        <input type="date" name="from" value="<?= e($from ?? '') ?>">
    </label>
    <label>
        <span>To</span>
        <input type="date" name="to" value="<?= e($to ?? '') ?>">
    </label>
    <button type="submit">Show</button>
</form>
<?php if (!empty($rangeError)): ?>
    <p class="error" role="alert"><?= e($rangeError) ?></p>
<?php endif; ?>
