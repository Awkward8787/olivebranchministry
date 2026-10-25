<?php

declare(strict_types=1);

use Obm\Money;
use Obm\ScheduleFormat;
?>
<h1>Dashboard</h1>
<p><?= e($monthLabel) ?></p>

<h2>Giving this month</h2>
<?php if ($report === null): ?>
    <p class="error" role="alert">Giving totals could not be reconciled.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="totals">
            <caption class="note">By fund</caption>
            <thead>
                <tr><th scope="col">Fund</th><th class="num" scope="col">Amount</th></tr>
            </thead>
            <tbody>
                <?php foreach ($report['by_fund'] as $fund => $cents): ?>
                    <?php if ($cents === 0) { continue; } ?>
                    <tr>
                        <td><?= e($fund) ?></td>
                        <td class="num"><?= e(Money::format($cents)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row">Total</th>
                    <td class="num"><?= e(Money::format($report['total'])) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="note"><?= (int) $report['count'] ?> gift<?= (int) $report['count'] === 1 ? '' : 's' ?>. Named donors <?= e(Money::format($report['donor_statement_total'])) ?>. Anonymous <?= e(Money::format($report['anonymous_total'])) ?>.</p>
<?php endif; ?>

<h2>Attendance this month</h2>
<?php if ($attendance['records'] === 0): ?>
    <p>No attendance recorded.</p>
<?php else: ?>
    <p><?= (int) $attendance['records'] ?> records. Total headcount <?= (int) $attendance['sum'] ?>. Average <?= e($attendance['average_display']) ?>.</p>
<?php endif; ?>
<?php if ($recent !== []): ?>
    <div class="table-wrap">
        <table class="stack">
            <caption>Recent attendance</caption>
            <thead>
                <tr><th scope="col">Date</th><th scope="col">Service</th><th class="num" scope="col">Count</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $row): ?>
                    <tr>
                        <td data-label="Date"><?= e(ScheduleFormat::longDate($row['date'])) ?></td>
                        <td data-label="Service"><?= e($row['label']) ?></td>
                        <td class="num" data-label="Count"><?= (int) $row['count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<h2>Visitors awaiting follow-up</h2>
<?php if ($waiting === []): ?>
    <p>None.</p>
<?php else: ?>
    <ul class="plain-list">
        <?php foreach ($waiting as $person): ?>
            <li><a href="/admin/people/<?= (int) $person['id'] ?>"><?= e($person['first_name'] . ' ' . $person['last_name']) ?></a>
                <?= e($person['follow_up_status']) ?><?php if (!empty($person['follow_up_on'])): ?>, <?= e(ScheduleFormat::longDate((string) $person['follow_up_on'])) ?><?php endif; ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<h2>Unread</h2>
<p><a href="/admin/inbox"><?= (int) $unread['contact'] ?> contact message<?= (int) $unread['contact'] === 1 ? '' : 's' ?></a>. <?= (int) $unread['prayer'] ?> prayer request<?= (int) $unread['prayer'] === 1 ? '' : 's' ?>.</p>
