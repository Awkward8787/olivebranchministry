<?php

declare(strict_types=1);

namespace Obm;

final class Http
{
    public static string $cspNonce = '';
    public static function method(): string
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        return $method === 'HEAD' ? 'GET' : $method;
    }

    public static function path(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/';
        }
        if ($path !== '/') {
            $path = rtrim($path, '/') ?: '/';
        }

        return $path;
    }

    public static function input(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    public static function query(string $key): string
    {
        $value = $_GET[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    public static function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip === '' || strlen($ip) > 45) {
            return 'unknown';
        }

        return $ip;
    }

    public static function isHttps(Config $config): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }
        if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }
        if ($config->get('app.trust_proxy') === true) {
            return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        }

        return false;
    }

    public static function baseUrl(Config $config): string
    {
        $set = rtrim((string) $config->get('app.url', ''), '/');
        if ($set !== '') {
            if (Links::httpsUrl($set) !== null) {
                return $set;
            }
            if ($config->get('app.env') === 'local' && preg_match('#^http://[A-Za-z0-9.-]+(?::\d+)?$#', $set) === 1) {
                return $set;
            }
        }
        $host = Links::safeHost((string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?? 'localhost';

        return (self::isHttps($config) ? 'https' : 'http') . '://' . $host;
    }

    public static function sendSecurityHeaders(Config $config): void
    {
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        if (self::isHttps($config)) {
            header('Strict-Transport-Security: max-age=31536000');
        }
        $script = "script-src 'self'";
        if (self::$cspNonce !== '' && preg_match('/^[A-Za-z0-9]+$/', self::$cspNonce) === 1) {
            $script .= " 'nonce-" . self::$cspNonce . "'";
        }
        header(
            "Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self'; "
            . $script
            . "; frame-src https://maps.google.com https://www.google.com; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'",
        );
    }

    public static function sendNoStore(): void
    {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
    }
}
