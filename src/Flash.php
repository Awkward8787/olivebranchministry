<?php

declare(strict_types=1);

namespace Obm;

final class Flash
{
    public static function set(string $message, string $type = 'ok'): void
    {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }

    /** @return array{message:string, type:string}|null */
    public static function pull(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        if (!is_array($flash) || !isset($flash['message']) || !is_string($flash['message'])) {
            return null;
        }

        return [
            'message' => $flash['message'],
            'type' => is_string($flash['type'] ?? null) ? $flash['type'] : 'ok',
        ];
    }
}
