<?php

declare(strict_types=1);

namespace Obm;

use PDO;

final class Database
{
    public static function connect(Config $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) $config->get('db.host', 'localhost'),
            (int) $config->get('db.port', 3306),
            (string) $config->get('db.name', ''),
            (string) $config->get('db.charset', 'utf8mb4'),
        );
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
            $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = false;
        }

        return new PDO(
            $dsn,
            (string) $config->get('db.user', ''),
            (string) $config->get('db.pass', ''),
            $options,
        );
    }
}
