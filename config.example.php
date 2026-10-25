<?php

declare(strict_types=1);

/**
 * Copy this file to config.php. config.php is not committed.
 * Do not put The Hub's EIN in statement.ein. That organization is separate.
 */
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'olive_branch',
        'user' => 'olive_branch',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        // Public site origin, no trailing slash. Used for canonical links and the sitemap.
        // Leave blank to use the current request host. Local example: http://127.0.0.1:8080
        'url' => '',
        // production or local. Demo seeding refuses to run when this is production.
        'env' => 'production',
        // Set true only if HTTPS is terminated in front of PHP and the host sends X-Forwarded-Proto.
        'trust_proxy' => false,
    ],
    'giving' => [
        // HTTPS link for online giving. The public page hides it when this is empty.
        'online_url' => '',
    ],
    'mail' => [
        // Inbox for contact and prayer notifications. Mail is not sent when this is empty.
        'notify_to' => '',
        // From address on this site's own domain. Required before a notification is sent.
        // Do not use a Gmail address. Leave blank to store the message and skip mail.
        'from' => '',
    ],
    'privacy' => [
        // Handled contact messages and prayer requests are deleted after this many days.
        'handled_days' => 90,
    ],
    'statement' => [
        // Church EIN only. Leave empty to omit it from year-end statements.
        'ein' => '',
        // Optional sentence approved by the church. Printed only when set.
        'extra' => '',
    ],
];
