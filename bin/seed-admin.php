<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this from the command line.\n");
    exit(1);
}
if ($argc > 1) {
    fwrite(STDERR, "Do not pass the password as an argument. Set ADMIN_EMAIL and ADMIN_PASSWORD in the environment.\n");
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

use Obm\Clock;
use Obm\Config;
use Obm\Database;
use Obm\Repository;
use Obm\Rules;

$config = Config::load(dirname(__DIR__) . '/config.php');
$repo = new Repository(Database::connect($config));
$email = getenv('ADMIN_EMAIL');
$password = getenv('ADMIN_PASSWORD');
$name = getenv('ADMIN_NAME');
$checked = Rules::user([
    'name' => is_string($name) && trim($name) !== '' ? $name : 'Administrator',
    'email' => is_string($email) ? $email : '',
    'role' => 'admin',
    'password' => is_string($password) ? $password : '',
], true);
if ($checked['errors'] !== []) {
    foreach ($checked['errors'] as $message) {
        fwrite(STDERR, $message . "\n");
    }
    exit(1);
}

$data = $checked['data'];
$data['password_hash'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
$existing = $repo->userByEmail((string) $data['email']);
$now = Clock::now()->format('Y-m-d H:i:s');
if ($existing === null) {
    $id = $repo->saveUser($data, null, $now);
    fwrite(STDOUT, "Created administrator {$data['email']} (id {$id}).\n");
    exit(0);
}
$repo->saveUser($data, (int) $existing['id'], $now);
fwrite(STDOUT, "Updated {$data['email']} and set the role to administrator.\n");
