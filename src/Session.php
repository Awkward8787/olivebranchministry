<?php

declare(strict_types=1);

namespace Obm;

final class Session
{
    public static function start(Config $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('OBMSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Http::isHttps($config),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_trans_sid', '0');
        session_start();
        if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf']) || strlen($_SESSION['csrf']) < 32) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
    }
}
