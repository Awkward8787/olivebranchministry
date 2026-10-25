<?php

declare(strict_types=1);

namespace Obm;

use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

final class Repository
{
    private const GIFT_COLUMNS = 'g.id, g.person_id, g.fund_id, g.amount_cents, g.gifted_on, g.method,
            g.reference, g.note, g.goods_or_services, g.goods_value_cents, f.name AS fund_name,
            f.receiving_org, p.first_name, p.last_name,
            CAST(COALESCE((SELECT SUM(a.amount_cents) FROM gift_adjustments a WHERE a.gift_id = g.id), 0) AS SIGNED) AS adjustment_cents,
            (SELECT COUNT(*) FROM gift_adjustments a WHERE a.gift_id = g.id AND a.kind = \'void\') AS void_count';

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
            ? 'SELECT id, name, sort_order, active, receiving_org FROM funds WHERE active = 1 ORDER BY sort_order, id'
            : 'SELECT id, name, sort_order, active, receiving_org FROM funds ORDER BY sort_order, id';

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
    public function saveFund(array $data, ?int $id, int $actorId, string $now): int
    {
        $org = ($data['receiving_org'] ?? GiftPolicy::CHURCH) === GiftPolicy::CHURCH
            ? GiftPolicy::CHURCH
            : GiftPolicy::UNCONFIRMED;
        $reason = trim((string) ($data['reason'] ?? ''));
        $this->pdo->beginTransaction();
        try {
            if ($id === null) {
                $this->run(
                    'INSERT INTO funds (name, sort_order, active, receiving_org) VALUES (:name, :sort_order, :active, :org)',
                    [':name' => $data['name'], ':sort_order' => $data['sort_order'], ':active' => $data['active'], ':org' => $org],
                );
                $id = (int) $this->pdo->lastInsertId();
                $this->writeFundAudit(
                    $id,
                    $actorId,
                    'created',
                    $reason !== '' ? $reason : 'Created',
                    'receiving_org=' . $org,
                    $now,
                );
                $this->pdo->commit();

                return $id;
            }
            $current = $this->one('SELECT receiving_org FROM funds WHERE id = :id FOR UPDATE', [':id' => $id]);
            if ($current === null) {
                throw new \InvalidArgumentException('That fund is not recorded.');
            }
            $previous = (string) $current['receiving_org'] === GiftPolicy::CHURCH
                ? GiftPolicy::CHURCH
                : GiftPolicy::UNCONFIRMED;
            if ($previous !== $org && $reason === '') {
                throw new \InvalidArgumentException('Enter a reason for changing who receives this fund.');
            }
            $this->run(
                'UPDATE funds SET name = :name, sort_order = :sort_order, active = :active, receiving_org = :org WHERE id = :id',
                [':name' => $data['name'], ':sort_order' => $data['sort_order'], ':active' => $data['active'], ':org' => $org, ':id' => $id],
            );
            if ($previous !== $org) {
                $this->writeFundAudit($id, $actorId, 'receiving_org', $reason, $previous . ' -> ' . $org, $now);
            }
            $this->pdo->commit();

            return $id;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function fundAudit(int $fundId): array
    {
        return $this->all(
            'SELECT a.id, a.action, a.reason, a.detail, a.created_at, u.name AS actor_name
             FROM fund_audit a
             LEFT JOIN users u ON u.id = a.actor_id
             WHERE a.fund_id = :id
             ORDER BY a.id',
            [':id' => $fundId],
        );
    }

    /**
     * Inclusive on both calendar dates.
     *
     * @return list<array<string, mixed>>
     */
    public function ledgerGifts(string $start, string $end): array
    {
        $rows = $this->all(
            'SELECT ' . self::GIFT_COLUMNS . '
             FROM gifts g
             INNER JOIN funds f ON f.id = g.fund_id
             LEFT JOIN people p ON p.id = g.person_id
             WHERE g.gifted_on >= :start AND g.gifted_on <= :end
             ORDER BY g.gifted_on, g.id',
            [':start' => $start, ':end' => $end],
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->mapGift($row);
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function gift(int $id): ?array
    {
        $row = $this->one(
            'SELECT ' . self::GIFT_COLUMNS . '
             FROM gifts g
             INNER JOIN funds f ON f.id = g.fund_id
             LEFT JOIN people p ON p.id = g.person_id
             WHERE g.id = :id',
            [':id' => $id],
        );
        if ($row === null) {
            return null;
        }

        return $this->mapGift($row);
    }

    /** @return list<array<string, mixed>> */
    public function giftAudit(int $giftId): array
    {
        return $this->all(
            'SELECT a.id, a.action, a.reason, a.detail, a.created_at, u.name AS actor_name
             FROM gift_audit a
             LEFT JOIN users u ON u.id = a.actor_id
             WHERE a.gift_id = :id
             ORDER BY a.id',
            [':id' => $giftId],
        );
    }

    /** @param array<string, mixed> $data */
    public function saveGift(array $data, ?int $id, ?int $userId, string $now): int
    {
        if ($id !== null) {
            throw new \InvalidArgumentException('Recorded gifts cannot be edited.');
        }
        $this->pdo->beginTransaction();
        try {
            $this->run(
                'INSERT INTO gifts (person_id, fund_id, amount_cents, gifted_on, method, reference, note, goods_or_services, goods_value_cents, recorded_by, created_at)
                 VALUES (:person_id, :fund_id, :cents, :date, :method, :reference, :note, :goods, :goods_value, :recorded_by, :created_at)',
                [
                    ':person_id' => $data['person_id'],
                    ':fund_id' => $data['fund_id'],
                    ':cents' => (string) $data['cents'],
                    ':date' => $data['date'],
                    ':method' => $data['method'],
                    ':reference' => $data['reference'],
                    ':note' => $data['note'],
                    ':goods' => $data['goods_or_services'],
                    ':goods_value' => $data['goods_value_cents'] === null ? null : (string) $data['goods_value_cents'],
                    ':recorded_by' => $userId,
                    ':created_at' => $now,
                ],
            );
            $giftId = (int) $this->pdo->lastInsertId();
            $this->writeAudit($giftId, $userId, 'recorded', 'Recorded', 'amount_cents=' . (string) $data['cents'], $now);
            $this->pdo->commit();

            return $giftId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function postGiftEntry(int $giftId, string $kind, ?int $signedCents, string $reason, int $userId, string $now): void
    {
        if ($reason === '') {
            throw new \InvalidArgumentException('Enter a reason.');
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT g.amount_cents,
                    CAST(COALESCE((SELECT SUM(amount_cents) FROM gift_adjustments WHERE gift_id = g.id), 0) AS SIGNED) AS delta,
                    (SELECT COUNT(*) FROM gift_adjustments WHERE gift_id = g.id AND kind = \'void\') AS voids
                 FROM gifts g WHERE g.id = :id FOR UPDATE',
            );
            $stmt->execute([':id' => $giftId]);
            $row = $stmt->fetch();
            if (!is_array($row)) {
                throw new \InvalidArgumentException('That gift is not recorded.');
            }
            if ((int) $row['voids'] > 0) {
                throw new \InvalidArgumentException('This gift is already voided.');
            }
            $net = GivingLedger::combine(
                Money::fromDatabase($row['amount_cents']),
                Money::fromSignedDatabase($row['delta']),
            );
            if ($kind === 'void') {
                if ($net <= 0) {
                    throw new \InvalidArgumentException('This gift is already zero.');
                }
                $signedCents = -$net;
            } elseif ($kind === 'adjustment') {
                if ($signedCents === null || $signedCents === 0) {
                    throw new \InvalidArgumentException('An adjustment cannot be zero.');
                }
                GivingLedger::combine($net, $signedCents);
            } else {
                throw new \InvalidArgumentException('Unknown gift entry.');
            }
            $this->run(
                'INSERT INTO gift_adjustments (gift_id, kind, amount_cents, reason, created_by, created_at)
                 VALUES (:gift_id, :kind, :cents, :reason, :user_id, :created_at)',
                [
                    ':gift_id' => $giftId,
                    ':kind' => $kind,
                    ':cents' => (string) $signedCents,
                    ':reason' => $reason,
                    ':user_id' => $userId,
                    ':created_at' => $now,
                ],
            );
            $this->writeAudit($giftId, $userId, $kind, $reason, $kind . ' ' . (string) $signedCents, $now);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
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
            'SELECT id, email, password_hash, role, name, session_version, disabled_at FROM users WHERE email = :email',
            [':email' => $email],
        );
    }

    /** @return array<string, mixed>|null */
    public function user(int $id): ?array
    {
        return $this->one(
            'SELECT id, email, password_hash, role, name, session_version, disabled_at FROM users WHERE id = :id',
            [':id' => $id],
        );
    }

    /** @return list<array<string, mixed>> */
    public function users(): array
    {
        return $this->all('SELECT id, email, role, name, last_login_at, disabled_at FROM users ORDER BY name, id');
    }

    public function countAdmins(): int
    {
        $row = $this->one("SELECT COUNT(*) AS n FROM users WHERE role = 'admin' AND disabled_at IS NULL");

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
        $disabledAt = !empty($data['disabled']) ? $now : null;
        if ($data['password_hash'] === null) {
            $bump = !empty($data['bump_session']) ? 1 : 0;
            $this->run(
                'UPDATE users
                 SET email = :email, role = :role, name = :name, disabled_at = :disabled_at,
                     session_version = session_version + :bump
                 WHERE id = :id',
                [
                    ':email' => $data['email'],
                    ':role' => $data['role'],
                    ':name' => $data['name'],
                    ':disabled_at' => $disabledAt,
                    ':bump' => $bump,
                    ':id' => $id,
                ],
            );
        } else {
            $this->run(
                'UPDATE users
                 SET email = :email, role = :role, name = :name, password_hash = :hash, disabled_at = :disabled_at,
                     session_version = session_version + 1
                 WHERE id = :id',
                [
                    ':email' => $data['email'],
                    ':role' => $data['role'],
                    ':name' => $data['name'],
                    ':hash' => $data['password_hash'],
                    ':disabled_at' => $disabledAt,
                    ':id' => $id,
                ],
            );
        }

        return $id;
    }

    public function resetPassword(int $id, string $hash): void
    {
        $this->run(
            'UPDATE users SET password_hash = :hash, session_version = session_version + 1 WHERE id = :id',
            [':hash' => $hash, ':id' => $id],
        );
    }

    public function promoteAdmin(int $id): void
    {
        $this->run("UPDATE users SET role = 'admin' WHERE id = :id", [':id' => $id]);
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

    /** @return array{pair:int, ip:int} */
    public function recentFailureCounts(string $email, string $ip, string $since): array
    {
        $pair = $this->one(
            'SELECT COUNT(*) AS n FROM login_attempts
             WHERE succeeded = 0 AND attempted_at >= :since AND email = :email AND ip = :ip',
            [':since' => $since, ':email' => $email, ':ip' => $ip],
        );
        $ipRow = $this->one(
            'SELECT COUNT(*) AS n FROM login_attempts
             WHERE succeeded = 0 AND attempted_at >= :since AND ip = :ip',
            [':since' => $since, ':ip' => $ip],
        );

        return [
            'pair' => (int) ($pair['n'] ?? 0),
            'ip' => (int) ($ipRow['n'] ?? 0),
        ];
    }

    public function purgeAttempts(string $before): void
    {
        $this->run('DELETE FROM login_attempts WHERE attempted_at < :before', [':before' => $before]);
    }

    public function purgeHandled(string $before): void
    {
        $this->run(
            'DELETE FROM prayer_requests WHERE handled_at IS NOT NULL AND handled_at < :before',
            [':before' => $before],
        );
        $this->run(
            'DELETE FROM contact_messages WHERE handled_at IS NOT NULL AND handled_at < :before',
            [':before' => $before],
        );
    }

    /**
     * @return array{rows:list<array<string, mixed>>, more:bool, page:int}
     */
    public function inboxPage(string $table, string $columns, int $page): array
    {
        $table = match ($table) {
            'contact_messages', 'prayer_requests' => $table,
            default => throw new \InvalidArgumentException('Unknown inbox.'),
        };
        if (!in_array($columns, [
            'id, name, email, phone, message, created_at, handled_at',
            'id, name, email, phone, request, created_at, handled_at',
        ], true)) {
            throw new \InvalidArgumentException('Unknown inbox columns.');
        }
        $page = max(1, $page);
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        $limit = $perPage + 1;
        $rows = $this->all(
            "SELECT {$columns} FROM {$table}
             ORDER BY (handled_at IS NULL) DESC, created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
        );
        $more = count($rows) > $perPage;
        if ($more) {
            array_pop($rows);
        }

        return ['rows' => $rows, 'more' => $more, 'page' => $page];
    }

    public function deleteInbox(string $type, int $id): void
    {
        $table = match ($type) {
            'contact' => 'contact_messages',
            'prayer' => 'prayer_requests',
            default => throw new \InvalidArgumentException('Unknown inbox.'),
        };
        $this->run("DELETE FROM {$table} WHERE id = :id", [':id' => $id]);
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

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapGift(array $row): array
    {
        $original = Money::fromDatabase($row['amount_cents']);
        $net = GivingLedger::combine($original, Money::fromSignedDatabase($row['adjustment_cents']));
        $org = (string) ($row['receiving_org'] ?? GiftPolicy::CHURCH);
        $goodsValue = $row['goods_value_cents'] === null || $row['goods_value_cents'] === ''
            ? null
            : Money::fromDatabase($row['goods_value_cents']);

        return [
            'id' => (int) $row['id'],
            'fund_id' => (int) $row['fund_id'],
            'fund' => (string) $row['fund_name'],
            'cents' => $net,
            'original_cents' => $original,
            'date' => (string) $row['gifted_on'],
            'donor_id' => $row['person_id'] === null ? null : (int) $row['person_id'],
            'donor_name' => $row['person_id'] === null ? null : trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
            'method' => (string) $row['method'],
            'reference' => $row['reference'] !== null ? (string) $row['reference'] : null,
            'note' => $row['note'] !== null ? (string) $row['note'] : null,
            'goods_or_services' => $row['goods_or_services'] !== null ? (string) $row['goods_or_services'] : null,
            'goods_value_cents' => $goodsValue,
            'receiving_org' => $org === GiftPolicy::CHURCH ? GiftPolicy::CHURCH : GiftPolicy::UNCONFIRMED,
            'voided' => (int) $row['void_count'] > 0,
        ];
    }

    private function writeFundAudit(int $fundId, ?int $actorId, string $action, string $reason, string $detail, string $now): void
    {
        $this->run(
            'INSERT INTO fund_audit (fund_id, actor_id, action, reason, detail, created_at)
             VALUES (:fund_id, :actor_id, :action, :reason, :detail, :created_at)',
            [
                ':fund_id' => $fundId,
                ':actor_id' => $actorId,
                ':action' => $action,
                ':reason' => $reason,
                ':detail' => substr($detail, 0, 1000),
                ':created_at' => $now,
            ],
        );
    }

    private function writeAudit(int $giftId, ?int $actorId, string $action, string $reason, string $detail, string $now): void
    {
        $this->run(
            'INSERT INTO gift_audit (gift_id, actor_id, action, reason, detail, created_at)
             VALUES (:gift_id, :actor_id, :action, :reason, :detail, :created_at)',
            [
                ':gift_id' => $giftId,
                ':actor_id' => $actorId,
                ':action' => $action,
                ':reason' => $reason,
                ':detail' => substr($detail, 0, 1000),
                ':created_at' => $now,
            ],
        );
    }

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
