<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this from the command line.\n");
    exit(1);
}

$flags = array_slice($argv, 1);
$resetPassword = false;
$makeAdmin = false;
foreach ($flags as $flag) {
    if ($flag === '--reset-password') {
        $resetPassword = true;
        continue;
    }
    if ($flag === '--make-admin') {
        $makeAdmin = true;
        continue;
    }
    fwrite(STDERR, "Unknown argument. Keep the password in the environment. Optional flags: --reset-password --make-admin\n");
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
$emailRaw = getenv('ADMIN_EMAIL');
$email = strtolower(trim(is_string($emailRaw) ? $emailRaw : ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Set ADMIN_EMAIL to a valid email in the environment.\n");
    exit(1);
}
$existing = $repo->userByEmail($email);
if ($existing !== null && !$resetPassword && !$makeAdmin) {
    fwrite(STDOUT, "Account exists; nothing changed\n");
    exit(0);
}

$password = getenv('ADMIN_PASSWORD');
$name = getenv('ADMIN_NAME');
$needsPassword = $existing === null || $resetPassword;
$checked = Rules::user([
    'name' => is_string($name) && trim($name) !== ''
        ? $name
        : (is_array($existing) ? (string) $existing['name'] : 'Administrator'),
    'email' => $email,
    'role' => 'admin',
    'password' => $needsPassword && is_string($password) ? $password : '',
], $needsPassword);
if ($checked['errors'] !== []) {
    foreach ($checked['errors'] as $message) {
        fwrite(STDERR, $message . "\n");
    }
    exit(1);
}

$data = $checked['data'];
$now = Clock::now()->format('Y-m-d H:i:s');
if ($existing === null) {
    $data['password_hash'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
    $id = $repo->saveUser($data, null, $now);
    fwrite(STDOUT, "Created administrator {$data['email']} (id {$id}).\n");
    exit(0);
}

$id = (int) $existing['id'];
if ($resetPassword) {
    $repo->resetPassword($id, password_hash((string) $data['password'], PASSWORD_DEFAULT));
    fwrite(STDOUT, "Warning: the password for {$email} was reset. Other sessions for that account will end.\n");
}
if ($makeAdmin) {
    $repo->promoteAdmin($id);
    fwrite(STDOUT, "Warning: {$email} is now an administrator.\n");
}
if (!$resetPassword && !$makeAdmin) {
    fwrite(STDOUT, "Account exists; nothing changed\n");
}
