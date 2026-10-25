<?php

declare(strict_types=1);

use Obm\Church;

$errors = $errors ?? [];
$form = $form ?? [];
$errorKind = $errorKind ?? '';
?>
<div class="wrap">
    <h1>Contact</h1>
    <address>
        <?= e(Church::NAME) ?><br>
        <?= e(Church::STREET) ?><br>
        <?= e(Church::cityLine()) ?>
    </address>
    <p><a href="tel:<?= e(Church::PHONE_TEL) ?>"><?= e(Church::PHONE_DISPLAY) ?></a></p>
    <p class="contact-email"><a href="mailto:<?= e(Church::EMAIL) ?>"><?= e(Church::EMAIL) ?></a></p>

    <h2>Write to the church</h2>
    <form method="post" action="/contact" accept-charset="UTF-8" novalidate>
        <?= $csrfField ?>
        <p class="honeypot" aria-hidden="true">
            <label>Leave this blank <input type="text" name="obm_leave_blank" tabindex="-1" autocomplete="off"></label>
        </p>
        <label>
            <span>Name</span>
            <input name="name" required maxlength="120" autocomplete="name" value="<?= e($form['name'] ?? '') ?>"<?= $errorKind === 'contact' && isset($errors['name']) ? ' aria-invalid="true" aria-describedby="contact-name-error"' : '' ?>>
        </label>
        <?php if ($errorKind === 'contact' && isset($errors['name'])): ?><p class="error" id="contact-name-error" role="alert"><?= e($errors['name']) ?></p><?php endif; ?>
        <label>
            <span>Email</span>
            <input type="email" name="email" required maxlength="190" autocomplete="email" value="<?= e($form['email'] ?? '') ?>"<?= $errorKind === 'contact' && isset($errors['email']) ? ' aria-invalid="true" aria-describedby="contact-email-error"' : '' ?>>
        </label>
        <?php if ($errorKind === 'contact' && isset($errors['email'])): ?><p class="error" id="contact-email-error" role="alert"><?= e($errors['email']) ?></p><?php endif; ?>
        <label>
            <span>Phone <span class="note">(optional)</span></span>
            <input type="tel" name="phone" maxlength="40" autocomplete="tel" value="<?= e($form['phone'] ?? '') ?>"<?= $errorKind === 'contact' && isset($errors['phone']) ? ' aria-invalid="true" aria-describedby="contact-phone-error"' : '' ?>>
        </label>
        <?php if ($errorKind === 'contact' && isset($errors['phone'])): ?><p class="error" id="contact-phone-error" role="alert"><?= e($errors['phone']) ?></p><?php endif; ?>
        <label>
            <span>Message</span>
            <textarea name="message" required maxlength="5000"<?= $errorKind === 'contact' && isset($errors['message']) ? ' aria-invalid="true" aria-describedby="contact-message-error"' : '' ?>><?= e($form['message'] ?? '') ?></textarea>
        </label>
        <?php if ($errorKind === 'contact' && isset($errors['message'])): ?><p class="error" id="contact-message-error" role="alert"><?= e($errors['message']) ?></p><?php endif; ?>
        <button type="submit">Send message</button>
    </form>

    <h2>Prayer request</h2>
    <p>Prayer requests are kept for pastors and staff. They are not posted on this site.</p>
    <form method="post" action="/prayer" accept-charset="UTF-8" novalidate>
        <?= $csrfField ?>
        <p class="honeypot" aria-hidden="true">
            <label>Leave this blank <input type="text" name="obm_leave_blank" tabindex="-1" autocomplete="off"></label>
        </p>
        <label>
            <span>Name</span>
            <input name="name" required maxlength="120" autocomplete="name" value="<?= e($errorKind === 'prayer' ? ($form['name'] ?? '') : '') ?>"<?= $errorKind === 'prayer' && isset($errors['name']) ? ' aria-invalid="true" aria-describedby="prayer-name-error"' : '' ?>>
        </label>
        <?php if ($errorKind === 'prayer' && isset($errors['name'])): ?><p class="error" id="prayer-name-error" role="alert"><?= e($errors['name']) ?></p><?php endif; ?>
        <label>
            <span>Email <span class="note">(optional)</span></span>
            <input type="email" name="email" maxlength="190" autocomplete="email" value="<?= e($errorKind === 'prayer' ? ($form['email'] ?? '') : '') ?>"<?= $errorKind === 'prayer' && isset($errors['email']) ? ' aria-invalid="true" aria-describedby="prayer-email-error"' : '' ?>>
        </label>
        <?php if ($errorKind === 'prayer' && isset($errors['email'])): ?><p class="error" id="prayer-email-error" role="alert"><?= e($errors['email']) ?></p><?php endif; ?>
        <label>
            <span>Phone <span class="note">(optional)</span></span>
            <input type="tel" name="phone" maxlength="40" autocomplete="tel" value="<?= e($errorKind === 'prayer' ? ($form['phone'] ?? '') : '') ?>"<?= $errorKind === 'prayer' && isset($errors['phone']) ? ' aria-invalid="true" aria-describedby="prayer-phone-error"' : '' ?>>
        </label>
        <?php if ($errorKind === 'prayer' && isset($errors['phone'])): ?><p class="error" id="prayer-phone-error" role="alert"><?= e($errors['phone']) ?></p><?php endif; ?>
        <label>
            <span>Prayer request</span>
            <textarea name="request" required maxlength="5000"<?= $errorKind === 'prayer' && isset($errors['request']) ? ' aria-invalid="true" aria-describedby="prayer-request-error"' : '' ?>><?= e($errorKind === 'prayer' ? ($form['request'] ?? '') : '') ?></textarea>
        </label>
        <?php if ($errorKind === 'prayer' && isset($errors['request'])): ?><p class="error" id="prayer-request-error" role="alert"><?= e($errors['request']) ?></p><?php endif; ?>
        <button type="submit">Send prayer request</button>
    </form>
</div>
