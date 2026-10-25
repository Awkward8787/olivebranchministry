<?php

declare(strict_types=1);

use Obm\ScheduleFormat;

if (($mode ?? 'list') === 'list'):
?>
<h1>Users</h1>
<p><a class="button" href="/admin/users/new">Add a user</a></p>
<div class="table-wrap">
    <table class="stack">
        <thead>
            <tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Last sign-in</th></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $row): ?>
                <tr>
                    <td data-label="Name"><a href="/admin/users/<?= (int) $row['id'] ?>"><?= e($row['name']) ?></a></td>
                    <td data-label="Email"><?= e($row['email']) ?></td>
                    <td data-label="Role"><?= e($row['role']) ?><?= !empty($row['disabled_at']) ? ' disabled' : '' ?></td>
                    <td data-label="Last sign-in"><?= !empty($row['last_login_at']) ? e(ScheduleFormat::longDateTime((string) $row['last_login_at'])) : '' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else:
    $form = $form ?? [];
    $errors = $errors ?? [];
    $action = ($editId ?? 0) === 0 ? '/admin/users/new' : '/admin/users/' . (int) $editId;
?>
<h1><?= ($editId ?? 0) === 0 ? 'New user' : 'User' ?></h1>
<p><a href="/admin/users">Back to users</a></p>
<form method="post" action="<?= e($action) ?>" accept-charset="UTF-8">
    <?= $csrfField ?>
    <label>
        <span>Name</span>
        <input name="name" required maxlength="120" value="<?= e($form['name'] ?? '') ?>">
    </label>
    <?php if (isset($errors['name'])): ?><p class="error" role="alert"><?= e($errors['name']) ?></p><?php endif; ?>
    <label>
        <span>Email</span>
        <input type="email" name="email" required maxlength="190" autocomplete="off" value="<?= e($form['email'] ?? '') ?>">
    </label>
    <?php if (isset($errors['email'])): ?><p class="error" role="alert"><?= e($errors['email']) ?></p><?php endif; ?>
    <label>
        <span>Role</span>
        <select name="role">
            <option value="staff"<?= selected_if($form['role'] ?? '', 'staff') ?>>Staff</option>
            <option value="admin"<?= selected_if($form['role'] ?? '', 'admin') ?>>Administrator</option>
        </select>
    </label>
    <?php if (isset($errors['role'])): ?><p class="error" role="alert"><?= e($errors['role']) ?></p><?php endif; ?>
    <label>
        <span>Password<?= ($editId ?? 0) === 0 ? '' : ' (leave blank to keep the current one)' ?></span>
        <input type="password" name="password" maxlength="72" autocomplete="new-password"<?= ($editId ?? 0) === 0 ? ' required' : '' ?>>
    </label>
    <?php if (isset($errors['password'])): ?><p class="error" role="alert"><?= e($errors['password']) ?></p><?php endif; ?>
    <?php if (($editId ?? 0) > 0): ?>
        <label class="check">
            <input type="checkbox" name="disabled" value="1"<?= checked_if(($form['disabled'] ?? '') === '1') ?>>
            <span>Disable this account</span>
        </label>
        <?php if (isset($errors['disabled'])): ?><p class="error" role="alert"><?= e($errors['disabled']) ?></p><?php endif; ?>
    <?php endif; ?>
    <p class="note">Use 12 to 72 characters. Changing the password ends that person's other sessions. There is no email reset.</p>
    <button type="submit">Save user</button>
</form>
<?php endif; ?>
