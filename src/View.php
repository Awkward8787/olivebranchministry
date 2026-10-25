<?php

declare(strict_types=1);

namespace Obm;

use RuntimeException;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data): string
    {
        $file = dirname(__DIR__) . '/templates/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Missing template.');
        }
        $render = static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            include $__file;

            return (string) ob_get_clean();
        };

        return $render($file, $data);
    }

    /** @param array<string, mixed> $data */
    public static function html(string $template, array $data): string
    {
        $data['content'] = self::render('public/' . $template, $data);

        return self::render('layout', $data);
    }

    /** @param array<string, mixed> $data */
    public static function page(string $template, array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo self::html($template, $data);
    }

    /** @param array<string, mixed> $data */
    public static function adminHtml(string $template, array $data): string
    {
        $data['content'] = self::render('admin/' . $template, $data);

        return self::render('admin-layout', $data);
    }

    /** @param array<string, mixed> $data */
    public static function admin(string $template, array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo self::adminHtml($template, $data);
    }
}
