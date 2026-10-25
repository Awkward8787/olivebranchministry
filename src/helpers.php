<?php

declare(strict_types=1);

function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? '1' : '';
    }
    if (!is_scalar($value)) {
        return '';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function e_nl(mixed $value): string
{
    return nl2br(e($value), false);
}

function redirect_to(string $path): never
{
    $pathOnly = $path;
    $query = '';
    if (str_contains($path, '?')) {
        [$pathOnly, $query] = explode('?', $path, 2);
        $query = '?' . $query;
    }
    $safePath = preg_match('#^/[A-Za-z0-9._~/-]*$#', $pathOnly) === 1 && !str_contains($pathOnly, '//');
    $safeQuery = $query === '' || preg_match('#^\?[A-Za-z0-9._~=&%-]*$#', $query) === 1;
    if (!$safePath || !$safeQuery) {
        $path = '/';
    }
    header('Location: ' . $path, true, 303);
    exit;
}

function selected_if(mixed $current, mixed $value): string
{
    return (string) $current === (string) $value ? ' selected' : '';
}

function checked_if(mixed $condition): string
{
    return $condition ? ' checked' : '';
}
