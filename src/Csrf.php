<?php

declare(strict_types=1);

namespace Obm;

use RuntimeException;

final class CsrfException extends RuntimeException
{
}

final class Csrf
{
    public static function token(): string
    {
        $token = $_SESSION['csrf'] ?? '';
        if (!is_string($token) || strlen($token) < 32) {
            throw new CsrfException('The form expired. Go back and try again.');
        }

        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf" value="' . e(self::token()) . '">';
    }

    public static function verify(string $posted): void
    {
        $token = $_SESSION['csrf'] ?? '';
        if (!is_string($token) || $token === '' || !hash_equals($token, $posted)) {
            throw new CsrfException('The form expired. Go back and try again.');
        }
    }

    public static function rotate(): void
    {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}
