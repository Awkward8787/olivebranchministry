<?php

declare(strict_types=1);

/**
 * Router for the built-in PHP server:
 *   php -S 127.0.0.1:8080 -t public bin/php-server-router.php
 */
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (!is_string($path) || $path === '') {
    $path = '/';
}
$file = dirname(__DIR__) . '/public' . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
require dirname(__DIR__) . '/public/index.php';
