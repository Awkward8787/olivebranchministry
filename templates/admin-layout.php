<?php

declare(strict_types=1);

$nav = [
    'dashboard' => ['Dashboard', '/admin'],
    'inbox' => ['Inbox', '/admin/inbox'],
    'people' => ['People', '/admin/people'],
    'giving' => ['Giving', '/admin/giving'],
    'attendance' => ['Attendance', '/admin/attendance'],
    'events' => ['Events', '/admin/events'],
    'services' => ['Services', '/admin/services'],
];
if (($user['role'] ?? '') === 'admin') {
    $nav['funds'] = ['Funds', '/admin/funds'];
    $nav['users'] = ['Users', '/admin/users'];
}
$documentTitle = $title . ' | Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($documentTitle) ?></title>
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="admin">
<a class="skip" href="#content">Skip to content</a>
<?php if (!empty($demo)): ?>
    <p class="demo-banner" role="status">DEMO DATA. These figures are sample records for local review and are not church records.</p>
<?php endif; ?>
<header class="admin-bar">
    <div class="wrap bar-row">
        <p class="wordmark"><a href="/admin">Olive Branch Ministries</a></p>
        <?php if (!empty($user)): ?>
            <p class="who"><?= e($user['name']) ?>, <?= e($user['role']) ?></p>
            <form class="inline-form" method="post" action="/admin/logout" accept-charset="UTF-8">
                <?= $csrfField ?>
                <button type="submit">Sign out</button>
            </form>
        <?php endif; ?>
    </div>
    <?php if (!empty($user)): ?>
        <nav class="admin-nav wrap" aria-label="Administration">
            <ul>
                <?php foreach ($nav as $key => $item): ?>
                    <li><a href="<?= e($item[1]) ?>"<?= ($section ?? '') === $key ? ' aria-current="page"' : '' ?>><?= e($item[0]) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>
</header>
<main id="content" class="wrap" tabindex="-1">
    <?php if (!empty($flash)): ?>
        <p class="flash<?= ($flash['type'] ?? '') === 'error' ? ' error' : '' ?>" role="status"><?= e($flash['message']) ?></p>
    <?php endif; ?>
    <?= $content ?>
</main>
<script src="/assets/js/site.js" defer></script>
</body>
</html>
