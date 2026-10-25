<?php

declare(strict_types=1);

namespace Obm;

final class Auth
{
    private bool $timedOut = false;

    public function __construct(private Repository $repo)
    {
    }

    /** @return array{id:int, email:string, role:string, name:string}|null */
    public function user(): ?array
    {
        $id = $_SESSION['uid'] ?? null;
        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            return null;
        }
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        $issued = (int) ($_SESSION['issued'] ?? 0);
        $seen = (int) ($_SESSION['seen'] ?? 0);
        $now = Clock::now()->getTimestamp();
        if (SessionPolicy::expired($seen, $issued, $now)) {
            $this->timedOut = true;
            $this->logout();

            return null;
        }
        $row = $this->repo->user($id);
        if ($row === null) {
            $this->logout();

            return null;
        }
        $disabled = $row['disabled_at'] !== null;
        $storedVersion = (int) ($row['session_version'] ?? 0);
        $sessionVersion = (int) ($_SESSION['session_version'] ?? 0);
        if (!SessionPolicy::stillValid($storedVersion, $sessionVersion, $disabled)) {
            $this->logout();

            return null;
        }
        $_SESSION['seen'] = $now;

        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'role' => (string) $row['role'],
            'name' => (string) $row['name'],
        ];
    }

    /** @return array{id:int, email:string, role:string, name:string} */
    public function requireUser(): array
    {
        $user = $this->user();
        if ($user === null) {
            redirect_to($this->timedOut ? '/admin/login?timeout=1' : '/admin/login');
        }

        return $user;
    }

    public function attempt(string $email, string $password, string $ip): string
    {
        $email = strtolower(trim($email));
        $now = Clock::now();
        $this->repo->purgeAttempts($now->modify('-2 days')->format('Y-m-d H:i:s'));
        if ($email === '' || $password === '' || strlen($password) > 72 || str_contains($password, "\0")) {
            return 'invalid';
        }
        $counts = $this->repo->recentFailureCounts($email, $ip, LoginGuard::windowStart($now));
        if (LoginGuard::locked($counts['pair'], $counts['ip'])) {
            password_verify($password, $this->dummyHash());

            return 'throttled';
        }
        $user = $this->repo->userByEmail($email);
        $hash = is_array($user) ? (string) $user['password_hash'] : $this->dummyHash();
        $disabled = is_array($user) && $user['disabled_at'] !== null;
        $ok = is_array($user) && !$disabled && password_verify($password, $hash);
        if (!is_array($user) || $disabled) {
            password_verify($password, $hash);
        }
        $stamp = $now->format('Y-m-d H:i:s');
        $this->repo->recordAttempt($email, $ip, $ok, $stamp);
        if (!$ok || !is_array($user)) {
            return 'invalid';
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['session_version'] = (int) $user['session_version'];
        $_SESSION['issued'] = $now->getTimestamp();
        $_SESSION['seen'] = $now->getTimestamp();
        Csrf::rotate();
        $this->repo->touchLogin((int) $user['id'], $stamp);

        return 'ok';
    }

    public function timedOut(): bool
    {
        return $this->timedOut;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] !== '' ? $params['path'] : '/',
                'domain' => (string) ($params['domain'] ?? ''),
                'secure' => (bool) $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_destroy();
        }
    }

    private function dummyHash(): string
    {
        static $hash = null;
        if ($hash === null) {
            $hash = password_hash('not-a-usable-password', PASSWORD_DEFAULT);
        }

        return $hash;
    }
}
