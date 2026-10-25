<?php

declare(strict_types=1);

use Obm\Church;
use Obm\Money;
use Obm\ScheduleFormat;
?>
<h1 class="no-print">Giving statement</h1>
<form class="form-row no-print" method="get" action="/admin/statements" accept-charset="UTF-8">
    <label>
        <span>Person</span>
        <select name="person" required>
            <option value="">Choose a person</option>
            <?php foreach ($people as $row): ?>
                <option value="<?= (int) $row['id'] ?>"<?= selected_if(isset($person['id']) ? (string) $person['id'] : '', (string) $row['id']) ?>><?= e($row['last_name'] . ', ' . $row['first_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <span>Year</span>
        <input name="year" inputmode="numeric" required maxlength="4" value="<?= e($year) ?>">
    </label>
    <button type="submit">Show statement</button>
</form>
<?php if (!empty($error)): ?>
    <p class="error" role="alert"><?= e($error) ?></p>
<?php endif; ?>
<?php if (is_array($statement) && $person !== null): ?>
    <p class="no-print"><button type="button" data-print>Print</button></p>
    <article class="statement-sheet">
        <p><?= e(Church::NAME) ?><br><?= e(Church::STREET) ?><br><?= e(Church::cityLine()) ?></p>
        <?php if (!empty($ein)): ?>
            <p>EIN <?= e($ein) ?></p>
        <?php endif; ?>
        <h2>Contribution statement for <?= e($year) ?></h2>
        <p><?= e($person['first_name'] . ' ' . $person['last_name']) ?></p>
        <p><?= e($rangeLabel) ?></p>
        <?php if (!empty($issued)): ?><p>Issued <?= e($issued) ?></p><?php endif; ?>
        <p class="statement-email"><?= e(Church::PHONE_DISPLAY) ?><br><?= e(Church::EMAIL) ?></p>
        <p class="note no-print">This statement includes gifts to funds the church receives. Other funds stay on the ledger and are left off this statement.</p>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Fund</th>
                    <th scope="col">Method</th>
                    <th class="num" scope="col">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($statement['lines'] as $line): ?>
                    <tr>
                        <td><?= e(ScheduleFormat::longDate($line['date'])) ?></td>
                        <td><?= e($line['fund']) ?></td>
                        <td><?= e($line['method'] === null || $line['method'] === '' ? '' : ucfirst((string) $line['method'])) ?></td>
                        <td class="num"><?= e(Money::format($line['cents'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row" colspan="3">Total</th>
                    <td class="num"><?= e(Money::format($statement['total_cents'])) ?></td>
                </tr>
            </tfoot>
        </table>
        </div>
        <?php foreach ($statement['language'] as $sentence): ?>
            <p><?= e($sentence) ?></p>
        <?php endforeach; ?>
        <?php if ($extra !== ''): ?>
            <p><?= e($extra) ?></p>
        <?php endif; ?>
        <?php if ($statement['lines'] === []): ?>
            <p>No contributions from this donor were recorded for <?= e($year) ?>.</p>
        <?php endif; ?>
    </article>
<?php endif; ?>
