<?php

declare(strict_types=1);

namespace Obm;

use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

final class Repository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function services(bool $activeOnly): array
    {
        $sql = $activeOnly
            ? 'SELECT id, name, weekday, start_time, end_time, note, sort_order, active, weekly FROM service_times WHERE active = 1 ORDER BY sort_order, id'
            : 'SELECT id, name, weekday, start_time, end_time, note, sort_order, active, weekly FROM service_times ORDER BY sort_order, id';
        $rows = [];
        foreach ($this->all($sql) as $row) {
            $rows[] = $this->mapService($row);
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function service(int $id): ?array
    {
        $row = $this->one(
            'SELECT id, name, weekday, start_time, end_time, note, sort_order, active, weekly FROM service_times WHERE id = :id',
            [':id' => $id],
        );

        return $row === null ? null : $this->mapService($row);
    }

    /** @param array<string, mixed> $data */
    public function saveService(array $data, ?int $id): int
    {
        $params = [
            ':name' => $data['name'],
            ':weekday' => $data['weekday'],
            ':start_time' => $data['start_time'],
            ':end_time' => $data['end_time'],
            ':note' => $data['note'],
            ':sort_order' => $data['sort_order'],
            ':active' => $data['active'],
            ':weekly' => $data['weekly'],
        ];
        if ($id === null) {
            $this->run(
                'INSERT INTO service_times (name, weekday, start_time, end_time, note, sort_order, active, weekly)
                 VALUES (:name, :weekday, :start_time, :end_time, :note, :sort_order, :active, :weekly)',
                $params,
            );

            return (int) $this->pdo->lastInsertId();
        }
        $params[':id'] = $id;
        $this->run(
            'UPDATE service_times
             SET name = :name, weekday = :weekday, start_time = :start_time, end_time = :end_time,
                 note = :note, sort_order = :sort_order, active = :active, weekly = :weekly
             WHERE id = :id',
            $params,
        );

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function upcomingEvents(string $todayStart): array
    {
        return $this->all(
            'SELECT id, title, summary, starts_at, ends_at, location, published
             FROM events
             WHERE published = 1 AND starts_at >= :start
             ORDER BY starts_at, id',
            [':start' => $todayStart],
        );
    }

    /** @return list<array<string, mixed>> */
    public function allEvents(): array
    {
        return $this->all(
            'SELECT id, title, summary, starts_at, ends_at, location, published
             FROM events ORDER BY starts_at DESC, id DESC',
        );
    }

    /** @return array<string, mixed>|null */
    public function event(int $id): ?array
    {
        return $this->one(
            'SELECT id, title, summary, starts_at, ends_at, location, published FROM events WHERE id = :id',
            [':id' => $id],
        );
    }

    /** @param array<string, mixed> $data */
    public function saveEvent(array $data, ?int $id, string $now): int
    {
        $params = [
            ':title' => $data['title'],
            ':summary' => $data['summary'],
            ':starts_at' => $data['starts_at'],
            ':ends_at' => $data['ends_at'],
            ':location' => $data['location'],
            ':published' => $data['published'],
        ];
        if ($id === null) {
            $params[':created_at'] = $now;
            $this->run(
                'INSERT INTO events (title, summary, starts_at, ends_at, location, published, created_at)
                 VALUES (:title, :summary, :starts_at, :ends_at, :location, :published, :created_at)',
                $params,
            );

            return (int) $this->pdo->lastInsertId();
        }
        $params[':id'] = $id;
        $this->run(
            'UPDATE events
             SET title = :title, summary = :summary, starts_at = :starts_at, ends_at = :ends_at,
                 location = :location, published = :published
             WHERE id = :id',
            $params,
        );

        return $id;
    }

    public function deleteEvent(int $id): void
    {
        $this->run('DELETE FROM events WHERE id = :id', [':id' => $id]);
    }

    /** @param array<string, mixed> $data */
    public function addContact(array $data, string $now): void
    {
        $this->run(
            'INSERT INTO contact_messages (name, email, phone, message, created_at)
             VALUES (:name, :email, :phone, :message, :created_at)',
            [
                ':name' => $data['name'],
                ':email' => $data['email'],
                ':phone' => $data['phone'],
                ':message' => $data['message'],
                ':created_at' => $now,
            ],
        );
    }

    /** @param array<string, mixed> $data */
    public function addPrayer(array $data, string $now): void
    {
        $this->run(
            'INSERT INTO prayer_requests (name, email, phone, request, created_at)
             VALUES (:name, :email, :phone, :request, :created_at)',
            [
                ':name' => $data['name'],
                ':email' => $data['email'],
                ':phone' => $data['phone'],
                ':request' => $data['request'],
                ':created_at' => $now,
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    public function contacts(): array
    {
        return $this->all(
            'SELECT id, name, email, phone, message, created_at, handled_at
             FROM contact_messages
             ORDER BY (handled_at IS NULL) DESC, created_at DESC
             LIMIT 200',
        );
    }

    /** @return list<array<string, mixed>> */
    public function prayers(): array
    {
        return $this->all(
            'SELECT id, name, email, phone, request, created_at, handled_at
             FROM prayer_requests
             ORDER BY (handled_at IS NULL) DESC, created_at DESC
             LIMIT 200',
        );
    }

    /** @return array{contact:int, prayer:int} */
    public function unhandledCounts(): array
    {
        $row = $this->one(
            'SELECT
                (SELECT COUNT(*) FROM contact_messages WHERE handled_at IS NULL) AS contact_n,
                (SELECT COUNT(*) FROM prayer_requests WHERE handled_at IS NULL) AS prayer_n',
        );

        return [
            'contact' => (int) ($row['contact_n'] ?? 0),
            'prayer' => (int) ($row['prayer_n'] ?? 0),
        ];
    }

    public function setHandled(string $type, int $id, ?int $userId, ?string $now): void
    {
        $table = match ($type) {
            'contact' => 'contact_messages',
            'prayer' => 'prayer_requests',
            default => throw new \InvalidArgumentException('Unknown inbox.'),
        };
        $this->run(
            "UPDATE {$table} SET handled_at = :at, handled_by = :uid WHERE id = :id",
            [':at' => $now, ':uid' => $userId, ':id' => $id],
        );
    }

    /** @return list<array<string, mixed>> */
    public function people(): array
    {
        return $this->all(
            'SELECT id, first_name, last_name, email, phone, kind, follow_up_status, follow_up_on, notes
             FROM people ORDER BY last_name, first_name, id',
        );
    }

    /** @return array<string, mixed>|null */
    public function person(int $id): ?array
    {
        return $this->one(
            'SELECT id, first_name, last_name, email, phone, kind, follow_up_status, follow_up_on, notes
             FROM people WHERE id = :id',
            [':id' => $id],
        );
    }

    /** @return list<int> */
    public function personIds(): array
    {
        $ids = [];
        foreach ($this->all('SELECT id FROM people') as $row) {
            $ids[] = (int) $row['id'];
        }

        return $ids;
    }

    /** @param array<string, mixed> $data */
    public function savePerson(array $data, ?int $id, string $now): int
    {
        $params = [
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':kind' => $data['kind'],
            ':follow_up_status' => $data['follow_up_status'],
            ':follow_up_on' => $data['follow_up_on'],
            ':notes' => $data['notes'],
        ];
        if ($id === null) {
            $params[':created_at'] = $now;
            $this->run(
                'INSERT INTO people (first_name, last_name, email, phone, kind, follow_up_status, follow_up_on, notes, created_at)
                 VALUES (:first_name, :last_name, :email, :phone, :kind, :follow_up_status, :follow_up_on, :notes, :created_at)',
                $params,
            );

            return (int) $this->pdo->lastInsertId();
        }
        $params[':id'] = $id;
        $this->run(
            'UPDATE people
             SET first_name = :first_name, last_name = :last_name, email = :email, phone = :phone,
                 kind = :kind, follow_up_status = :follow_up_status, follow_up_on = :follow_up_on, notes = :notes
             WHERE id = :id',
            $params,
        );

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function funds(bool $activeOnly): array
    {
        $sql = $activeOnly
            ? 'SELECT id, name, sort_order, active FROM funds WHERE active = 1 ORDER BY sort_order, id'
            : 'SELECT id, name, sort_order, active FROM funds ORDER BY sort_order, id';

        return $this->all($sql);
    }

    /** @return list<int> */
    public function fundIds(bool $activeOnly): array
    {
        $ids = [];
        foreach ($this->funds($activeOnly) as $fund) {
            $ids[] = (int) $fund['id'];
        }

        return $ids;
    }

    /** @param array<string, mixed> $data */
    public function saveFund(array $data, ?int $id): int
    {
        if ($id === null) {
            $this->run(
                'INSERT INTO funds (name, sort_order, active) VALUES (:name, :sort_order, :active)',
                [':name' => $data['name'], ':sort_order' => $data['sort_order'], ':active' => $data['active']],
            );

            return (int) $this->pdo->lastInsertId();
        }
        $this->run(
            'UPDATE funds SET name = :name, sort_order = :sort_order, active = :active WHERE id = :id',
            [':name' => $data['name'], ':sort_order' => $data['sort_order'], ':active' => $data['active'], ':id' => $id],
        );

        return $id;
    }

    /**
     * Inclusive on both calendar dates.
     *
     * @return list<array<string, mixed>>
     */
    public function ledgerGifts(string $start, string $end): array
    {
        $rows = $this->all(
            'SELECT g.id, g.person_id, g.fund_id, g.amount_cents, g.gifted_on, g.method,
                    g.reference, g.note, g.goods_or_services, f.name AS fund_name,
                    p.first_name, p.last_name
             FROM gifts g
             INNER JOIN funds f ON f.id = g.fund_id
             LEFT JOIN people p ON p.id = g.person_id
             WHERE g.gifted_on >= :start AND g.gifted_on <= :end
             ORDER BY g.gifted_on, g.id',
            [':start' => $start, ':end' => $end],
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'fund_id' => (int) $row['fund_id'],
                'fund' => (string) $row['fund_name'],
                'cents' => Money::fromDatabase($row['amount_cents']),
                'date' => (string) $row['gifted_on'],
                'donor_id' => $row['person_id'] === null ? null : (int) $row['person_id'],
                'donor_name' => $row['person_id'] === null ? null : trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
                'method' => (string) $row['method'],
                'reference' => $row['reference'] !== null ? (string) $row['reference'] : null,
                'note' => $row['note'] !== null ? (string) $row['note'] : null,
                'goods_or_services' => $row['goods_or_services'] !== null ? (string) $row['goods_or_services'] : null,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function gift(int $id): ?array
    {
        $row = $this->one(
            'SELECT g.id, g.person_id, g.fund_id, g.amount_cents, g.gifted_on, g.method,
                    g.reference, g.note, g.goods_or_services, f.name AS fund_name,
                    p.first_name, p.last_name
             FROM gifts g
             INNER JOIN funds f ON f.id = g.fund_id
             LEFT JOIN people p ON p.id = g.person_id
             WHERE g.id = :id',
            [':id' => $id],
        );
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'fund_id' => (int) $row['fund_id'],
            'fund' => (string) $row['fund_name'],
            'cents' => Money::fromDatabase($row['amount_cents']),
            'date' => (string) $row['gifted_on'],
            'donor_id' => $row['person_id'] === null ? null : (int) $row['person_id'],
            'donor_name' => $row['person_id'] === null ? null : trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
            'method' => (string) $row['method'],
            'reference' => $row['reference'] !== null ? (string) $row['reference'] : null,
            'note' => $row['note'] !== null ? (string) $row['note'] : null,
            'goods_or_services' => $row['goods_or_services'] !== null ? (string) $row['goods_or_services'] : null,
        ];
    }

    /** @param array<string, mixed> $data */
    public function saveGift(array $data, ?int $id, ?int $userId, string $now): int
    {
        $params = [
            ':person_id' => $data['person_id'],
            ':fund_id' => $data['fund_id'],
            ':cents' => (string) $data['cents'],
            ':date' => $data['date'],
            ':method' => $data['method'],
            ':reference' => $data['reference'],
            ':note' => $data['note'],
            ':goods' => $data['goods_or_services'],
        ];
        if ($id === null) {
            $params[':recorded_by'] = $userId;
            $params[':created_at'] = $now;
            $this->run(
                'INSERT INTO gifts (person_id, fund_id, amount_cents, gifted_on, method, reference, note, goods_or_services, recorded_by, created_at)
                 VALUES (:person_id, :fund_id, :cents, :date, :method, :reference, :note, :goods, :recorded_by, :created_at)',
                $params,
            );

            return (int) $this->pdo->lastInsertId();
        }
        $params[':id'] = $id;
        $this->run(
            'UPDATE gifts
             SET person_id = :person_id, fund_id = :fund_id, amount_cents = :cents, gifted_on = :date,
                 method = :method, reference = :reference, note = :note, goods_or_services = :goods
             WHERE id = :id',
            $params,
        );

        return $id;
    }

    public function deleteGift(int $id): void
    {
        $this->run('DELETE FROM gifts WHERE id = :id', [':id' => $id]);
    }

    /** @return list<int> */
    public function giftYears(int $personId): array
    {
        $years = [];
        foreach ($this->all(
            'SELECT DISTINCT YEAR(gifted_on) AS y FROM gifts WHERE person_id = :id ORDER BY y DESC',
            [':id' => $personId],
        ) as $row) {
            $years[] = (int) $row['y'];
        }

        return $years;
    }

    /** @return list<array<string, mixed>> */
    public function attendanceBetween(string $start, string $end): array
    {
        $rows = $this->all(
            'SELECT id, service_time_id, service_label, attended_on, headcount, notes
             FROM attendance
             WHERE attended_on >= :start AND attended_on <= :end
             ORDER BY attended_on, id',
            [':start' => $start, ':end' => $end],
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->mapAttendance($row);
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function recentAttendance(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        $rows = $this->all(
            'SELECT id, service_time_id, service_label, attended_on, headcount, notes
             FROM attendance ORDER BY attended_on DESC, id DESC LIMIT ' . $limit,
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->mapAttendance($row);
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function attendance(int $id): ?array
    {
        $row = $this->one(
            'SELECT id, service_time_id, service_label, attended_on, headcount, notes FROM attendance WHERE id = :id',
            [':id' => $id],
        );

        return $row === null ? null : $this->mapAttendance($row);
    }

    /** @param array<string, mixed> $data */
    public function saveAttendance(array $data, ?int $id, ?int $userId, string $now): int
    {
        $params = [
            ':service_time_id' => $data['service_id'],
            ':label' => $data['label'],
            ':date' => $data['date'],
            ':count' => $data['count'],
            ':notes' => $data['notes'],
        ];
        if ($id === null) {
            $params[':recorded_by'] = $userId;
            $params[':created_at'] = $now;
            $this->run(
                'INSERT INTO attendance (service_time_id, service_label, attended_on, headcount, notes, recorded_by, created_at)
                 VALUES (:service_time_id, :label, :date, :count, :notes, :recorded_by, :created_at)',
                $params,
            );

            return (int) $this->pdo->lastInsertId();
        }
        $params[':id'] = $id;
        $this->run(
            'UPDATE attendance
             SET service_time_id = :service_time_id, service_label = :label, attended_on = :date,
                 headcount = :count, notes = :notes
             WHERE id = :id',
            $params,
        );

        return $id;
    }

    public function deleteAttendance(int $id): void
    {
        $this->run('DELETE FROM attendance WHERE id = :id', [':id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function userByEmail(string $email): ?array
    {
        return $this->one(
            'SELECT id, email, password_hash, role, name FROM users WHERE email = :email',
            [':email' => $email],
        );
    }

    /** @return array<string, mixed>|null */
    public function user(int $id): ?array
    {
        return $this->one(
            'SELECT id, email, password_hash, role, name FROM users WHERE id = :id',
            [':id' => $id],
        );
    }

    /** @return list<array<string, mixed>> */
    public function users(): array
    {
        return $this->all('SELECT id, email, role, name, last_login_at FROM users ORDER BY name, id');
    }

    public function countAdmins(): int
    {
        $row = $this->one("SELECT COUNT(*) AS n FROM users WHERE role = 'admin'");

        return (int) ($row['n'] ?? 0);
    }

    /** @param array<string, mixed> $data */
    public function saveUser(array $data, ?int $id, string $now): int
    {
        if ($id === null) {
            $this->run(
                'INSERT INTO users (email, password_hash, role, name, created_at)
                 VALUES (:email, :hash, :role, :name, :created_at)',
                [
                    ':email' => $data['email'],
                    ':hash' => $data['password_hash'],
                    ':role' => $data['role'],
                    ':name' => $data['name'],
                    ':created_at' => $now,
                ],
            );

            return (int) $this->pdo->lastInsertId();
        }
        if ($data['password_hash'] === null) {
            $this->run(
                'UPDATE users SET email = :email, role = :role, name = :name WHERE id = :id',
                [':email' => $data['email'], ':role' => $data['role'], ':name' => $data['name'], ':id' => $id],
            );
        } else {
            $this->run(
                'UPDATE users SET email = :email, role = :role, name = :name, password_hash = :hash WHERE id = :id',
                [
                    ':email' => $data['email'],
                    ':role' => $data['role'],
                    ':name' => $data['name'],
                    ':hash' => $data['password_hash'],
                    ':id' => $id,
                ],
            );
        }

        return $id;
    }

    public function touchLogin(int $id, string $now): void
    {
        $this->run('UPDATE users SET last_login_at = :at WHERE id = :id', [':at' => $now, ':id' => $id]);
    }

    public function recordAttempt(string $email, string $ip, bool $ok, string $now): void
    {
        $this->run(
            'INSERT INTO login_attempts (email, ip, succeeded, attempted_at) VALUES (:email, :ip, :ok, :at)',
            [':email' => $email, ':ip' => $ip, ':ok' => $ok ? 1 : 0, ':at' => $now],
        );
    }

    public function recentFailures(string $email, string $ip, string $since): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM login_attempts
             WHERE succeeded = 0 AND attempted_at >= :since AND (email = :email OR ip = :ip)',
            [':since' => $since, ':email' => $email, ':ip' => $ip],
        );

        return (int) ($row['n'] ?? 0);
    }

    public function purgeAttempts(string $before): void
    {
        $this->run('DELETE FROM login_attempts WHERE attempted_at < :before', [':before' => $before]);
    }

    public function allowSubmission(string $bucket, DateTimeImmutable $now): bool
    {
        if (preg_match('/^[a-f0-9]{64}$/', $bucket) !== 1) {
            throw new \InvalidArgumentException('Rate-limit bucket must be a 64-character hash.');
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT hits, window_start FROM rate_limits WHERE bucket = :bucket FOR UPDATE');
            $stmt->execute([':bucket' => $bucket]);
            $row = $stmt->fetch();
            $next = RateWindow::next(
                $row ? (int) $row['hits'] : null,
                $row ? (string) $row['window_start'] : null,
                $now,
            );
            $write = $this->pdo->prepare(
                'INSERT INTO rate_limits (bucket, window_start, hits) VALUES (:bucket, :start, :hits)
                 ON DUPLICATE KEY UPDATE window_start = :start2, hits = :hits2',
            );
            $write->execute([
                ':bucket' => $bucket,
                ':start' => $next['window_start'],
                ':hits' => $next['hits'],
                ':start2' => $next['window_start'],
                ':hits2' => $next['hits'],
            ]);
            $this->pdo->commit();

            return !$next['blocked'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function isDemo(): bool
    {
        return $this->setting('demo_data') === '1';
    }

    public function setting(string $name): ?string
    {
        $row = $this->one('SELECT value FROM settings WHERE name = :name', [':name' => $name]);

        return $row === null ? null : (string) $row['value'];
    }

    public function setSetting(string $name, string $value): void
    {
        $this->run(
            'INSERT INTO settings (name, value) VALUES (:name, :value)
             ON DUPLICATE KEY UPDATE value = :value2',
            [':name' => $name, ':value' => $value, ':value2' => $value],
        );
    }

    public static function isDuplicate(PDOException $e): bool
    {
        return (string) $e->getCode() === '23000';
    }

    /** @param array<string, mixed> $row */
    private function mapService(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'weekday' => (int) $row['weekday'],
            'start_time' => substr((string) $row['start_time'], 0, 8),
            'end_time' => $row['end_time'] !== null ? substr((string) $row['end_time'], 0, 8) : null,
            'note' => $row['note'] !== null ? (string) $row['note'] : null,
            'sort_order' => (int) $row['sort_order'],
            'active' => (int) $row['active'],
            'weekly' => (int) $row['weekly'],
        ];
    }

    /** @param array<string, mixed> $row */
    private function mapAttendance(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'service_id' => $row['service_time_id'] === null ? null : (int) $row['service_time_id'],
            'label' => (string) $row['service_label'],
            'date' => (string) $row['attended_on'],
            'count' => (int) $row['headcount'],
            'notes' => $row['notes'] !== null ? (string) $row['notes'] : null,
        ];
    }

    /** @param array<string, mixed> $params */
    private function run(string $sql, array $params = []): void
    {
        $this->statement($sql, $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function all(string $sql, array $params = []): array
    {
        $rows = $this->statement($sql, $params)->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function one(string $sql, array $params = []): ?array
    {
        $row = $this->statement($sql, $params)->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $params */
    private function statement(string $sql, array $params): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            if ($value === null) {
                $stmt->bindValue($key, null, PDO::PARAM_NULL);
            } elseif (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, (string) $value, PDO::PARAM_STR);
            }
        }
        $stmt->execute();

        return $stmt;
    }
}
