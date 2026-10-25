<?php

declare(strict_types=1);

namespace Obm;

final class Links
{
    public static function httpsUrl(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/[\s<>"\'\\\\]/', $value) === 1) {
            return null;
        }
        $parts = parse_url($value);
        if (!is_array($parts)) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if ($scheme !== 'https' || $host === '' || str_contains($host, '..')) {
            return null;
        }

        return $value;
    }

    public static function safeHost(string $host): ?string
    {
        if (preg_match('/^[A-Za-z0-9.-]+(?::\d+)?$/', $host) !== 1) {
            return null;
        }

        return $host;
    }
}
