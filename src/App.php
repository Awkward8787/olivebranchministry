<?php

declare(strict_types=1);

namespace Obm;

use PDOException;
use RuntimeException;
use Throwable;

final class App
{
    public static function run(): void
    {
        Http::$cspNonce = bin2hex(random_bytes(16));
        try {
            $config = Config::load(dirname(__DIR__) . '/config.php');
            Http::sendSecurityHeaders();
            Session::start($config);
            $path = Http::path();
            $method = Http::method();
            $db = Database::connect($config);
            $repo = new Repository($db);
            if (str_starts_with($path, '/admin')) {
                Http::sendNoStore();
                (new AdminController($config, $repo, new Auth($repo)))->dispatch($method, $path);

                return;
            }
            (new PublicController($config, $repo, new Mailer($config)))->dispatch($method, $path);
        } catch (CsrfException $e) {
            self::fail(400, 'Form expired', $e->getMessage());
        } catch (PDOException) {
            self::fail(500, 'Unavailable', 'The church records are unavailable right now.');
        } catch (RuntimeException $e) {
            $message = str_contains($e->getMessage(), 'config.php')
                ? 'The site is not configured yet.'
                : 'The page could not be shown.';
            self::fail(500, 'Unavailable', $message);
        } catch (Throwable) {
            self::fail(500, 'Unavailable', 'The page could not be shown.');
        }
    }

    private static function fail(int $status, string $title, string $message): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . e($title) . '</title>';
        echo '<link rel="stylesheet" href="/assets/css/site.css">';
        echo '</head><body><a class="skip" href="#content">Skip to content</a>';
        echo '<main id="content" class="wrap"><h1>' . e($title) . '</h1><p>' . e($message) . '</p>';
        echo '<p><a href="/">Olive Branch Ministries Inc.</a></p></main></body></html>';
    }
}
