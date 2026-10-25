<h1>Sign in</h1>
<?php if (!empty($timeout)): ?>
    <p role="status">You were signed out after a period of inactivity.</p>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <p class="error" role="alert"><?= e($error) ?></p>
<?php endif; ?>
<form method="post" action="/admin/login" accept-charset="UTF-8">
    <?= $csrfField ?>
    <label>
        <span>Email</span>
        <input type="email" name="email" required maxlength="190" autocomplete="username" value="<?= e($email ?? '') ?>">
    </label>
    <label>
        <span>Password</span>
        <input type="password" name="password" required maxlength="72" autocomplete="current-password">
    </label>
    <button type="submit">Sign in</button>
</form>
