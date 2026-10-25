<?php

declare(strict_types=1);

use Obm\Money;
use Obm\ScheduleFormat;

if (($mode ?? 'list') === 'list'):
?>
<h1>Giving</h1>
<p class="actions no-print"><a class="button" href="/admin/giving/new">Record a gift</a> <a href="/admin/statements">Year-end statement</a></p>
<?php include dirname(__DIR__) . '/partials/range.php'; ?>
<?php if (!empty($rangeLabel)): ?>
    <p><?= e($rangeLabel) ?>. Both dates are included.</p>
<?php endif; ?>
<?php if ($report === null && empty($rangeError) && !empty($rangeLabel)): ?>
    <p class="error" role="alert">Giving totals could not be reconciled.</p>
<?php elseif (is_array($report)): ?>
    <div class="table-wrap">
        <table class="totals">
            <caption>Totals</caption>
            <thead>
                <tr><th scope="col">Fund</th><th class="num" scope="col">Amount</th></tr>
            </thead>
            <tbody>
                <?php foreach ($report['by_fund'] as $fund => $cents): ?>
                    <tr>
                        <td><?= e($fund) ?></td>
                        <td class="num"><?= e(Money::format($cents)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row">Ledger total</th>
                    <td class="num"><?= e(Money::format($report['total'])) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p>Fund totals <?= e(Money::format($report['fund_total'])) ?>. Named donors <?= e(Money::format($report['donor_statement_total'])) ?>. Anonymous <?= e(Money::format($report['anonymous_total'])) ?>. These match the ledger total.</p>
<?php endif; ?>
<?php if (!empty($rangeLabel)): ?>
    <div class="table-wrap">
        <table class="stack">
            <caption>Gifts</caption>
            <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Person</th>
                    <th scope="col">Fund</th>
                    <th scope="col">Method</th>
                    <th scope="col">Reference</th>
                    <th class="num" scope="col">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gifts as $gift): ?>
                    <tr>
                        <td data-label="Date"><a href="/admin/giving/<?= (int) $gift['id'] ?>"><?= e(ScheduleFormat::longDate($gift['date'])) ?></a></td>
                        <td data-label="Person"><?= e($gift['donor_name'] ?? 'Anonymous') ?></td>
                        <td data-label="Fund"><?= e($gift['fund']) ?></td>
                        <td data-label="Method"><?= e($gift['method']) ?></td>
                        <td data-label="Reference"><?= e($gift['reference'] ?? '') ?></td>
                        <td class="num" data-label="Amount"><?= e(Money::format($gift['cents'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($gifts === []): ?>
        <p>No gifts in this range.</p>
    <?php endif; ?>
<?php endif; ?>
<?php else:
    $form = $form ?? [];
    $errors = $errors ?? [];
    $action = ($giftId ?? 0) === 0 ? '/admin/giving/new' : '/admin/giving/' . (int) $giftId;
?>
<h1><?= ($giftId ?? 0) === 0 ? 'Record a gift' : 'Gift' ?></h1>
<p><a href="/admin/giving">Back to the ledger</a></p>
<form method="post" action="<?= e($action) ?>" accept-charset="UTF-8">
    <?= $csrfField ?>
    <label>
        <span>Amount</span>
        <input name="amount" required inputmode="decimal" autocomplete="off" value="<?= e($form['amount'] ?? '') ?>"<?= isset($errors['amount']) ? ' aria-invalid="true" aria-describedby="err-amount"' : '' ?>>
    </label>
    <?php if (isset($errors['amount'])): ?><p class="error" id="err-amount" role="alert"><?= e($errors['amount']) ?></p><?php endif; ?>
    <label>
        <span>Date</span>
        <input type="date" name="date" required value="<?= e($form['date'] ?? '') ?>"<?= isset($errors['date']) ? ' aria-invalid="true" aria-describedby="err-date"' : '' ?>>
    </label>
    <?php if (isset($errors['date'])): ?><p class="error" id="err-date" role="alert"><?= e($errors['date']) ?></p><?php endif; ?>
    <label>
        <span>Fund</span>
        <select name="fund_id" required>
            <option value="">Choose a fund</option>
            <?php foreach ($funds as $fund): ?>
                <option value="<?= (int) $fund['id'] ?>"<?= selected_if($form['fund_id'] ?? '', (string) $fund['id']) ?>><?= e($fund['name']) ?><?= (int) $fund['active'] === 1 ? '' : ' (inactive)' ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if (isset($errors['fund_id'])): ?><p class="error" role="alert"><?= e($errors['fund_id']) ?></p><?php endif; ?>
    <label>
        <span>Person</span>
        <select name="person_id">
            <option value="">Anonymous</option>
            <?php foreach ($people as $person): ?>
                <option value="<?= (int) $person['id'] ?>"<?= selected_if($form['person_id'] ?? '', (string) $person['id']) ?>><?= e($person['last_name'] . ', ' . $person['first_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if (isset($errors['person_id'])): ?><p class="error" role="alert"><?= e($errors['person_id']) ?></p><?php endif; ?>
    <label>
        <span>Method</span>
        <select name="method">
            <?php foreach (['cash' => 'Cash', 'check' => 'Check', 'online' => 'Online', 'other' => 'Other'] as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= selected_if($form['method'] ?? '', $value) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if (isset($errors['method'])): ?><p class="error" role="alert"><?= e($errors['method']) ?></p><?php endif; ?>
    <label>
        <span>Check number or reference</span>
        <input name="reference" maxlength="80" value="<?= e($form['reference'] ?? '') ?>"<?= isset($errors['reference']) ? ' aria-invalid="true" aria-describedby="err-ref"' : '' ?>>
    </label>
    <?php if (isset($errors['reference'])): ?><p class="error" id="err-ref" role="alert"><?= e($errors['reference']) ?></p><?php endif; ?>
    <label>
        <span>Note</span>
        <input name="note" maxlength="500" value="<?= e($form['note'] ?? '') ?>">
    </label>
    <?php if (isset($errors['note'])): ?><p class="error" role="alert"><?= e($errors['note']) ?></p><?php endif; ?>
    <label>
        <span>Goods or services provided</span>
        <input name="goods_or_services" maxlength="500" value="<?= e($form['goods_or_services'] ?? '') ?>">
    </label>
    <p class="note">Leave that blank when nothing was provided in return. It is printed on the donor's statement.</p>
    <?php if (isset($errors['goods_or_services'])): ?><p class="error" role="alert"><?= e($errors['goods_or_services']) ?></p><?php endif; ?>
    <button type="submit">Save gift</button>
</form>
<?php if (!empty($isAdmin) && ($giftId ?? 0) > 0): ?>
    <p><a href="/admin/giving/<?= (int) $giftId ?>/delete">Delete this gift</a></p>
<?php endif; ?>
<?php endif; ?>
