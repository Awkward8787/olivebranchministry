<?php

declare(strict_types=1);

use Obm\ScheduleFormat;

if (($mode ?? 'list') === 'list'):
?>
<h1>People</h1>
<p><a class="button" href="/admin/people/new">Add a person</a></p>
<?php if ($people === []): ?>
    <p>No one is in the directory yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="stack">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Kind</th>
                    <th scope="col">Follow-up</th>
                    <th scope="col">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($people as $person): ?>
                    <tr>
                        <td data-label="Name"><a href="/admin/people/<?= (int) $person['id'] ?>"><?= e($person['last_name'] . ', ' . $person['first_name']) ?></a></td>
                        <td data-label="Kind"><?= e($person['kind']) ?></td>
                        <td data-label="Follow-up"><?= e($person['follow_up_status']) ?><?php if (!empty($person['awaiting'])): ?> · Awaiting<?php endif; ?></td>
                        <td data-label="Date"><?= !empty($person['follow_up_on']) ? e(ScheduleFormat::longDate((string) $person['follow_up_on'])) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php else:
    $form = $form ?? [];
    $errors = $errors ?? [];
?>
<h1><?= ($personId ?? 0) === 0 ? 'New person' : 'Person' ?></h1>
<p><a href="/admin/people">Back to people</a></p>
<form method="post" action="<?= ($personId ?? 0) === 0 ? '/admin/people/new' : '/admin/people/' . (int) $personId ?>" accept-charset="UTF-8">
    <?= $csrfField ?>
    <label>
        <span>First name</span>
        <input name="first_name" required maxlength="80" value="<?= e($form['first_name'] ?? '') ?>"<?= isset($errors['first_name']) ? ' aria-invalid="true" aria-describedby="err-first"' : '' ?>>
    </label>
    <?php if (isset($errors['first_name'])): ?><p class="error" id="err-first" role="alert"><?= e($errors['first_name']) ?></p><?php endif; ?>
    <label>
        <span>Last name</span>
        <input name="last_name" required maxlength="80" value="<?= e($form['last_name'] ?? '') ?>"<?= isset($errors['last_name']) ? ' aria-invalid="true" aria-describedby="err-last"' : '' ?>>
    </label>
    <?php if (isset($errors['last_name'])): ?><p class="error" id="err-last" role="alert"><?= e($errors['last_name']) ?></p><?php endif; ?>
    <label>
        <span>Email</span>
        <input type="email" name="email" maxlength="190" value="<?= e($form['email'] ?? '') ?>"<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="err-email"' : '' ?>>
    </label>
    <?php if (isset($errors['email'])): ?><p class="error" id="err-email" role="alert"><?= e($errors['email']) ?></p><?php endif; ?>
    <label>
        <span>Phone</span>
        <input name="phone" maxlength="40" value="<?= e($form['phone'] ?? '') ?>"<?= isset($errors['phone']) ? ' aria-invalid="true" aria-describedby="err-phone"' : '' ?>>
    </label>
    <?php if (isset($errors['phone'])): ?><p class="error" id="err-phone" role="alert"><?= e($errors['phone']) ?></p><?php endif; ?>
    <label>
        <span>Kind</span>
        <select name="kind">
            <option value="member"<?= selected_if($form['kind'] ?? '', 'member') ?>>Member</option>
            <option value="visitor"<?= selected_if($form['kind'] ?? '', 'visitor') ?>>Visitor</option>
        </select>
    </label>
    <?php if (isset($errors['kind'])): ?><p class="error" role="alert"><?= e($errors['kind']) ?></p><?php endif; ?>
    <label>
        <span>Follow-up status</span>
        <select name="follow_up_status">
            <option value="new"<?= selected_if($form['follow_up_status'] ?? '', 'new') ?>>New</option>
            <option value="contacted"<?= selected_if($form['follow_up_status'] ?? '', 'contacted') ?>>Contacted</option>
        </select>
    </label>
    <?php if (isset($errors['follow_up_status'])): ?><p class="error" role="alert"><?= e($errors['follow_up_status']) ?></p><?php endif; ?>
    <label>
        <span>Follow-up date</span>
        <input type="date" name="follow_up_on" value="<?= e($form['follow_up_on'] ?? '') ?>"<?= isset($errors['follow_up_on']) ? ' aria-invalid="true" aria-describedby="err-follow"' : '' ?>>
    </label>
    <?php if (isset($errors['follow_up_on'])): ?><p class="error" id="err-follow" role="alert"><?= e($errors['follow_up_on']) ?></p><?php endif; ?>
    <label>
        <span>Notes</span>
        <textarea name="notes" maxlength="2000"><?= e($form['notes'] ?? '') ?></textarea>
    </label>
    <?php if (isset($errors['notes'])): ?><p class="error" role="alert"><?= e($errors['notes']) ?></p><?php endif; ?>
    <button type="submit">Save person</button>
</form>
<?php if (($personId ?? 0) > 0): ?>
    <p><a href="/admin/statements?person=<?= (int) $personId ?>">Year-end statement</a></p>
<?php endif; ?>
<?php endif; ?>
