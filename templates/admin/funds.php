<?php

declare(strict_types=1);

if (($mode ?? 'list') === 'list'):
?>
<h1>Funds</h1>
<p>Inactive funds stay on gifts already recorded. New gifts use active funds only.</p>
<p><a class="button" href="/admin/funds/new">Add a fund</a></p>
<div class="table-wrap">
    <table>
        <thead>
            <tr><th scope="col">Fund</th><th scope="col">Order</th><th scope="col">Receives</th><th scope="col">Status</th></tr>
        </thead>
        <tbody>
            <?php foreach ($funds as $fund): ?>
                <tr>
                    <td><a href="/admin/funds/<?= (int) $fund['id'] ?>"><?= e($fund['name']) ?></a></td>
                    <td><?= (int) $fund['sort_order'] ?></td>
                    <td><?= ($fund['receiving_org'] ?? 'church') === 'church' ? 'Church' : 'Unconfirmed' ?></td>
                    <td><?= (int) $fund['active'] === 1 ? 'Active' : 'Inactive' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else:
    $form = $form ?? [];
    $errors = $errors ?? [];
    $action = ($fundId ?? 0) === 0 ? '/admin/funds/new' : '/admin/funds/' . (int) $fundId;
?>
<h1><?= ($fundId ?? 0) === 0 ? 'New fund' : 'Fund' ?></h1>
<p><a href="/admin/funds">Back to funds</a></p>
<form method="post" action="<?= e($action) ?>" accept-charset="UTF-8">
    <?= $csrfField ?>
    <label>
        <span>Name</span>
        <input name="name" required maxlength="80" value="<?= e($form['name'] ?? '') ?>">
    </label>
    <?php if (isset($errors['name'])): ?><p class="error" role="alert"><?= e($errors['name']) ?></p><?php endif; ?>
    <label>
        <span>Sort order</span>
        <input name="sort_order" inputmode="numeric" value="<?= e($form['sort_order'] ?? '0') ?>">
    </label>
    <?php if (isset($errors['sort_order'])): ?><p class="error" role="alert"><?= e($errors['sort_order']) ?></p><?php endif; ?>
    <label class="check">
        <input type="checkbox" name="active" value="1"<?= checked_if(($form['active'] ?? '1') === '1') ?>>
        <span>Active</span>
    </label>
    <label>
        <span>Receiving organization</span>
        <select name="receiving_org">
            <option value="church"<?= selected_if($form['receiving_org'] ?? 'church', 'church') ?>>Church</option>
            <option value="unconfirmed"<?= selected_if($form['receiving_org'] ?? '', 'unconfirmed') ?>>Unconfirmed</option>
        </select>
    </label>
    <?php if (isset($errors['receiving_org'])): ?><p class="error" role="alert"><?= e($errors['receiving_org']) ?></p><?php endif; ?>
    <p class="note">Unconfirmed funds stay on the ledger and are left off church contribution statements.</p>
    <button type="submit">Save fund</button>
</form>
<?php endif; ?>
