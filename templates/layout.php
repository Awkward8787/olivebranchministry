<?php

declare(strict_types=1);

use Obm\Church;
use Obm\Http;

$documentTitle = ($page ?? '') === 'home' ? (string) $title : $title . ' | ' . Church::NAME;
$nav = [
    'home' => ['Home', '/'],
    'about' => ['About', '/about'],
    'leadership' => ['Leadership', '/leadership'],
    'ministries' => ['Ministries', '/ministries'],
    'hub' => ['The Hub', '/the-hub'],
    'events' => ['Events', '/events'],
    'visit' => ['Plan your visit', '/plan-your-visit'],
    'give' => ['Give', '/give'],
    'contact' => ['Contact', '/contact'],
];
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($documentTitle) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <?php if (!empty($robots)): ?>
        <meta name="robots" content="<?= e($robots) ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?= e($documentTitle) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?= e($image) ?>">
    <meta property="og:locale" content="en_US">
    <link rel="stylesheet" href="/assets/css/site.css">
    <?php if (!empty($jsonLd)): ?>
        <script type="application/ld+json" nonce="<?= e(Http::$cspNonce) ?>"><?= json_encode($jsonLd, $flags) ?></script>
    <?php endif; ?>
</head>
<body>
<a class="skip" href="#content">Skip to content</a>
<header class="site-header">
    <div class="wrap header-row">
        <?php if (($page ?? '') === 'home'): ?>
            <h1 class="wordmark"><a href="/"><?= e(Church::NAME) ?></a></h1>
        <?php else: ?>
            <p class="wordmark"><a href="/"><?= e(Church::NAME) ?></a></p>
        <?php endif; ?>
        <p class="tagline"><?= e(Church::TAGLINE) ?></p>
    </div>
    <nav class="site-nav wrap" aria-label="Primary">
        <ul>
            <?php foreach ($nav as $key => $item): ?>
                <li><a href="<?= e($item[1]) ?>"<?= ($page ?? '') === $key ? ' aria-current="page"' : '' ?>><?= e($item[0]) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>
</header>
<div class="gold-rule" aria-hidden="true"></div>
<main id="content" tabindex="-1">
    <?= $content ?>
</main>
<footer class="site-footer">
    <div class="wrap">
        <p><?= e(Church::NAME) ?></p>
        <p><?= e(Church::STREET) ?><br><?= e(Church::cityLine()) ?></p>
        <p><a href="tel:<?= e(Church::PHONE_TEL) ?>"><?= e(Church::PHONE_DISPLAY) ?></a></p>
        <p><a href="mailto:<?= e(Church::EMAIL) ?>"><?= e(Church::EMAIL) ?></a></p>
        <p><a href="<?= e(Church::FACEBOOK) ?>">Facebook</a></p>
        <p class="tagline"><?= e(Church::TAGLINE) ?></p>
    </div>
</footer>
<script src="/assets/js/site.js" defer></script>
</body>
</html>
