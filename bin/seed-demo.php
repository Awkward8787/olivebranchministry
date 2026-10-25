<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this from the command line.\n");
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

use Obm\AttendanceStats;
use Obm\Clock;
use Obm\Config;
use Obm\Database;
use Obm\DateRange;
use Obm\GivingLedger;
use Obm\Money;
use Obm\Periods;
use Obm\Statements;

$config = Config::load(dirname(__DIR__) . '/config.php');
if ((string) $config->get('app.env', '') === 'production') {
    fwrite(STDERR, "Refusing demo seed: app.env is production.\n");
    exit(1);
}
if (getenv('ALLOW_DEMO_SEED') !== '1') {
    fwrite(STDERR, "Refusing demo seed: set ALLOW_DEMO_SEED=1.\n");
    exit(1);
}

$pdo = Database::connect($config);
$now = Clock::now()->format('Y-m-d H:i:s');
$yesterday = Clock::now()->modify('-1 day')->format('Y-m-d');

$pdo->beginTransaction();
$pdo->prepare(
    "DELETE a FROM gift_adjustments a INNER JOIN gifts g ON g.id = a.gift_id WHERE g.note LIKE 'DEMO:%'",
)->execute();
$pdo->prepare(
    "DELETE a FROM gift_audit a INNER JOIN gifts g ON g.id = a.gift_id WHERE g.note LIKE 'DEMO:%'",
)->execute();
$pdo->prepare("DELETE FROM gifts WHERE note LIKE 'DEMO:%'")->execute();
$pdo->prepare("DELETE FROM attendance WHERE notes LIKE 'DEMO:%'")->execute();
$pdo->prepare("DELETE FROM contact_messages WHERE message LIKE 'DEMO:%'")->execute();
$pdo->prepare("DELETE FROM prayer_requests WHERE request LIKE 'DEMO:%'")->execute();
$pdo->prepare(
    "DELETE p FROM people p
     LEFT JOIN gifts g ON g.person_id = p.id
     WHERE p.notes = 'DEMO' AND g.id IS NULL",
)->execute();

$insertPerson = $pdo->prepare(
    'INSERT INTO people (first_name, last_name, email, phone, kind, follow_up_status, follow_up_on, notes, created_at)
     VALUES (:first, :last, NULL, NULL, :kind, :status, :follow, :notes, :created)',
);
$people = [
    ['Demo', 'Donor', 'member', 'contacted', null],
    ['Demo', 'Visitor', 'visitor', 'new', null],
    ['Demo', 'Guest', 'visitor', 'contacted', $yesterday],
    ['Sample', 'Member', 'member', 'contacted', null],
];
$ids = [];
foreach ($people as $person) {
    $insertPerson->execute([
        ':first' => $person[0],
        ':last' => $person[1],
        ':kind' => $person[2],
        ':status' => $person[3],
        ':follow' => $person[4],
        ':notes' => 'DEMO',
        ':created' => $now,
    ]);
    $ids[$person[0] . ' ' . $person[1]] = (int) $pdo->lastInsertId();
}

$fundRows = $pdo->query('SELECT id, name FROM funds')->fetchAll();
$funds = [];
foreach ($fundRows as $row) {
    $funds[(string) $row['name']] = (int) $row['id'];
}
$serviceRows = $pdo->query('SELECT id, name FROM service_times')->fetchAll();
$services = [];
foreach ($serviceRows as $row) {
    $services[(string) $row['name']] = (int) $row['id'];
}

$insertGift = $pdo->prepare(
    'INSERT INTO gifts (person_id, fund_id, amount_cents, gifted_on, method, reference, note, goods_or_services, goods_value_cents, recorded_by, created_at)
     VALUES (:person, :fund, :cents, :date, :method, :reference, :note, :goods, :goods_value, NULL, :created)',
);
$mealEstimate = Money::parseCents('15.00');
$gifts = [
    ['Demo Donor', 'Tithes', '500.00', '2026-01-01', 'check', '1001', 'DEMO: January tithe', null, null],
    ['Demo Donor', 'Offering', '40.25', '2026-02-28', 'cash', null, 'DEMO: February offering', null, null],
    ['Demo Donor', 'General', '150.00', '2026-10-04', 'cash', null, 'DEMO: October general', null, null],
    ['Demo Donor', 'The Hub', '75.10', '2026-10-18', 'check', '1002', 'DEMO: October hub', null, null],
    [null, 'General', '25.00', '2026-10-11', 'cash', null, 'DEMO: anonymous general', null, null],
    ['Sample Member', 'Building', '100.00', '2026-10-20', 'check', '1003', 'DEMO: meal good-faith estimate 15.00', 'meal', $mealEstimate],
    ['Demo Donor', 'Tithes', '300.00', '2025-12-31', 'check', '1004', 'DEMO: prior year tithe', null, null],
    ['Demo Donor', 'General', '50.00', '2027-01-01', 'cash', null, 'DEMO: next year general', null, null],
    ['Demo Donor', 'Tithes', '18.00', '2024-02-29', 'cash', null, 'DEMO: leap day tithe', null, null],
];
foreach ($gifts as $gift) {
    $insertGift->execute([
        ':person' => $gift[0] === null ? null : $ids[$gift[0]],
        ':fund' => $funds[$gift[1]],
        ':cents' => (string) Money::parseCents($gift[2]),
        ':date' => $gift[3],
        ':method' => $gift[4],
        ':reference' => $gift[5],
        ':note' => $gift[6],
        ':goods' => $gift[7],
        ':goods_value' => $gift[8] === null ? null : (string) $gift[8],
        ':created' => $now,
    ]);
}

$insertAttendance = $pdo->prepare(
    'INSERT INTO attendance (service_time_id, service_label, attended_on, headcount, notes, recorded_by, created_at)
     VALUES (:service, :label, :date, :count, :notes, NULL, :created)',
);
$counts = [
    ['2026-10-04', 'Worship', 48],
    ['2026-10-04', 'Sunday School', 22],
    ['2026-10-11', 'Worship', 51],
    ['2026-10-18', 'Worship', 45],
    ['2026-10-08', 'Bible Study & Youth', 16],
    ['2026-10-15', 'Bible Study & Youth', 14],
    ['2026-10-10', 'Prayer & Fasting', 9],
];
foreach ($counts as $count) {
    $insertAttendance->execute([
        ':service' => $services[$count[1]] ?? null,
        ':label' => $count[1],
        ':date' => $count[0],
        ':count' => $count[2],
        ':notes' => 'DEMO: headcount',
        ':created' => $now,
    ]);
}

$pdo->prepare(
    'INSERT INTO contact_messages (name, email, phone, message, created_at) VALUES (:name, :email, NULL, :message, :created)',
)->execute([
    ':name' => 'Demo Sender',
    ':email' => 'demo.sender@example.com',
    ':message' => 'DEMO: sample contact',
    ':created' => $now,
]);
$pdo->prepare(
    'INSERT INTO prayer_requests (name, email, phone, request, created_at) VALUES (:name, NULL, NULL, :request, :created)',
)->execute([
    ':name' => 'Demo Sender',
    ':request' => 'DEMO: sample prayer',
    ':created' => $now,
]);
$pdo->prepare(
    'INSERT INTO settings (name, value) VALUES (:name, :value) ON DUPLICATE KEY UPDATE value = :value2',
)->execute([':name' => 'demo_data', ':value' => '1', ':value2' => '1']);
$pdo->commit();

$ledger = [];
$rows = $pdo->query(
    'SELECT g.id, g.person_id, g.amount_cents, g.gifted_on, f.name AS fund, f.receiving_org,
            g.goods_or_services, g.goods_value_cents
     FROM gifts g INNER JOIN funds f ON f.id = g.fund_id WHERE g.note LIKE \'DEMO:%\'',
)->fetchAll();
foreach ($rows as $row) {
    $ledger[] = [
        'id' => (int) $row['id'],
        'fund' => (string) $row['fund'],
        'cents' => Money::fromDatabase($row['amount_cents']),
        'date' => (string) $row['gifted_on'],
        'donor_id' => $row['person_id'] === null ? null : (int) $row['person_id'],
        'receiving_org' => (string) $row['receiving_org'],
        'goods_or_services' => $row['goods_or_services'] !== null ? (string) $row['goods_or_services'] : null,
        'goods_value_cents' => $row['goods_value_cents'] === null ? null : Money::fromDatabase($row['goods_value_cents']),
    ];
}
$year = GivingLedger::reconcile($ledger, new DateRange('2026-01-01', '2026-12-31'));
$october = GivingLedger::reconcile($ledger, Periods::month(2026, 10));
$donor = GivingLedger::donorTotal($ledger, $ids['Demo Donor'], Periods::year(2026));
$statement = Statements::forDonor($ledger, $ids['Demo Donor'], Periods::year(2026));
$octoberStatement = Statements::forDonor($ledger, $ids['Demo Donor'], Periods::month(2026, 10));
$attendance = AttendanceStats::summarize(array_column($counts, 2));
$expect = [
    '2026 ledger' => [89035, $year['total']],
    '2026 funds' => [89035, $year['fund_total']],
    '2026 named plus anonymous' => [89035, $year['donor_statement_total'] + $year['anonymous_total']],
    'October 2026 ledger' => [35010, $october['total']],
    'Demo Donor 2026 ledger' => [76535, $donor],
    'Demo Donor 2026 church statement' => [69025, $statement['total_cents']],
    'Demo Donor October 2026 church statement' => [15000, $octoberStatement['total_cents']],
    'attendance average' => ['29.3', $attendance['average_display']],
];
$failed = false;
foreach ($expect as $label => $pair) {
    $ok = $pair[0] === $pair[1];
    fwrite(STDOUT, $label . ': ' . (string) $pair[1] . ($ok ? "\n" : " EXPECTED {$pair[0]}\n"));
    $failed = $failed || !$ok;
}
fwrite(STDOUT, 'Demo Donor statement: /admin/statements?person=' . $ids['Demo Donor'] . "&year=2026\n");
fwrite(STDOUT, "DEMO DATA is local only. The public site does not list these names.\n");
if ($failed) {
    exit(1);
}
