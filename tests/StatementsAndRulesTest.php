<?php

declare(strict_types=1);

namespace Obm\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Obm\FollowUp;
use Obm\GiftPolicy;
use Obm\GoodsEstimateMissing;
use Obm\HtmlCheck;
use Obm\Links;
use Obm\LoginGuard;
use Obm\Mailer;
use Obm\Money;
use Obm\Periods;
use Obm\RateWindow;
use Obm\Retention;
use Obm\Rules;
use Obm\SessionPolicy;
use Obm\Statements;
use PHPUnit\Framework\TestCase;

final class StatementsAndRulesTest extends TestCase
{
    public function testContributionLanguageDependsOnTheGoodsField(): void
    {
        $plain = [[
            'date' => '2026-01-01',
            'cents' => 100,
            'goods_or_services' => null,
        ]];
        $this->assertSame([Statements::NO_GOODS], Statements::contributionLanguage($plain));
        $this->assertSame(
            'No goods or services were provided other than intangible religious benefits.',
            Statements::NO_GOODS,
        );

        $goods = [[
            'date' => '2026-01-01',
            'cents' => 100,
            'goods_or_services' => 'Dinner provided',
            'goods_value_cents' => 2500,
        ]];
        $language = Statements::contributionLanguage($goods);
        $this->assertStringStartsWith('Goods or services were provided', $language[0]);
        $this->assertSame('January 1, 2026: Dinner provided. Good-faith estimate $25.00.', $language[1]);
        $this->assertSame(Statements::INTANGIBLE_BESIDES, $language[2]);
        $this->assertNotContains(Statements::NO_GOODS, $language);

        $mixed = [
            ['date' => '2026-01-01', 'cents' => 100, 'goods_or_services' => null],
            ['date' => '2026-02-01', 'cents' => 50, 'goods_or_services' => 'A book', 'goods_value_cents' => 500],
        ];
        $mixedLanguage = Statements::contributionLanguage($mixed);
        $this->assertStringContainsString('except as noted', $mixedLanguage[0]);
        $this->assertSame('February 1, 2026: A book. Good-faith estimate $5.00.', $mixedLanguage[1]);
        $this->assertSame(Statements::INTANGIBLE_BESIDES, $mixedLanguage[2]);
        $this->assertSame([], Statements::contributionLanguage([]));
    }

    public function testAGoodsNoteWithoutAnEstimateNamesTheGift(): void
    {
        try {
            Statements::contributionLanguage([[
                'id' => 6,
                'date' => '2026-10-20',
                'cents' => 10000,
                'goods_or_services' => 'meal',
                'goods_value_cents' => null,
            ]]);
            $this->fail('A missing estimate should not use the year error.');
        } catch (GoodsEstimateMissing $e) {
            $this->assertSame(6, $e->giftId);
            $this->assertSame('Gift #6 has a goods note but no good-faith estimate.', $e->getMessage());
        }
    }

    public function testChurchStatementRefusesTheHubEin(): void
    {
        $this->assertNull(Statements::statementEin(''));
        $this->assertNull(Statements::statementEin('   '));
        $this->assertNull(Statements::statementEin('41-5032500'));
        $this->assertNull(Statements::statementEin('415032500'));
        $this->assertNull(Statements::statementEin('41 5032500'));
        $this->assertSame('12-3456789', Statements::statementEin('12-3456789'));
    }

    public function testGiftRulesUseCentsAndRealDates(): void
    {
        $ok = Rules::gift([
            'amount' => '$1.15',
            'date' => '2024-02-29',
            'method' => 'check',
            'reference' => '1008',
            'fund_id' => '2',
            'person_id' => '',
            'note' => '',
            'goods_or_services' => '',
        ], [2], [5]);
        $this->assertSame([], $ok['errors']);
        $this->assertSame(115, $ok['data']['cents']);
        $this->assertNull($ok['data']['person_id']);

        $zero = Rules::gift([
            'amount' => '0',
            'date' => '2024-12-31',
            'method' => 'cash',
            'reference' => '',
            'fund_id' => '2',
            'person_id' => '',
            'note' => '',
            'goods_or_services' => '',
        ], [2], []);
        $this->assertArrayHasKey('amount', $zero['errors']);

        $badDate = Rules::gift([
            'amount' => '10',
            'date' => '2023-02-29',
            'method' => 'cash',
            'reference' => '',
            'fund_id' => '2',
            'person_id' => '5',
            'note' => '',
            'goods_or_services' => '',
        ], [2], [5]);
        $this->assertArrayHasKey('date', $badDate['errors']);

        $check = Rules::gift([
            'amount' => '10',
            'date' => '2026-01-01',
            'method' => 'check',
            'reference' => '',
            'fund_id' => '2',
            'person_id' => '9',
            'note' => '',
            'goods_or_services' => '',
        ], [2], [5]);
        $this->assertArrayHasKey('reference', $check['errors']);
        $this->assertArrayHasKey('person_id', $check['errors']);

        $floaty = Rules::gift([
            'amount' => '1e2',
            'date' => '2026-01-01',
            'method' => 'wire',
            'reference' => '',
            'fund_id' => '2',
            'person_id' => '',
            'note' => '',
            'goods_or_services' => '',
        ], [2], []);
        $this->assertArrayHasKey('amount', $floaty['errors']);
        $this->assertArrayHasKey('method', $floaty['errors']);
    }

    public function testAttendanceCountRejectsFractionsAndAllowsZero(): void
    {
        $zero = Rules::attendance([
            'date' => '2024-02-29',
            'count' => '0',
            'label' => 'Worship',
            'service_id' => '',
            'notes' => '',
        ], []);
        $this->assertSame([], $zero['errors']);
        $this->assertSame(0, $zero['data']['count']);

        $bad = Rules::attendance([
            'date' => '2023-02-29',
            'count' => '10.5',
            'label' => '',
            'service_id' => '4',
            'notes' => '',
        ], [1]);
        $this->assertArrayHasKey('date', $bad['errors']);
        $this->assertArrayHasKey('count', $bad['errors']);
        $this->assertArrayHasKey('label', $bad['errors']);
        $this->assertArrayHasKey('service_id', $bad['errors']);
    }

    public function testFollowUpEdges(): void
    {
        $visitor = ['kind' => 'visitor', 'follow_up_status' => 'new', 'follow_up_on' => null];
        $this->assertTrue(FollowUp::awaiting($visitor, '2026-01-01'));
        $this->assertTrue(FollowUp::awaiting([
            'kind' => 'visitor',
            'follow_up_status' => 'new',
            'follow_up_on' => '2026-06-01',
        ], '2026-01-01'));
        $this->assertFalse(FollowUp::awaiting([
            'kind' => 'visitor',
            'follow_up_status' => 'contacted',
            'follow_up_on' => null,
        ], '2026-01-01'));
        $this->assertTrue(FollowUp::awaiting([
            'kind' => 'visitor',
            'follow_up_status' => 'contacted',
            'follow_up_on' => '2026-01-01',
        ], '2026-01-01'));
        $this->assertTrue(FollowUp::awaiting([
            'kind' => 'visitor',
            'follow_up_status' => 'contacted',
            'follow_up_on' => '2025-12-31',
        ], '2026-01-01'));
        $this->assertFalse(FollowUp::awaiting([
            'kind' => 'visitor',
            'follow_up_status' => 'contacted',
            'follow_up_on' => '2026-01-01',
        ], '2025-12-31'));
        $this->assertFalse(FollowUp::awaiting([
            'kind' => 'member',
            'follow_up_status' => 'new',
            'follow_up_on' => null,
        ], '2026-01-01'));
    }

    public function testServiceEndAndEventGap(): void
    {
        $service = Rules::service([
            'name' => 'Prayer & Fasting',
            'weekday' => '6',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'note' => '',
            'sort_order' => '50',
            'active' => '1',
            'weekly' => '1',
        ]);
        $this->assertSame([], $service['errors']);
        $this->assertSame('10:00:00', $service['data']['start_time']);
        $this->assertSame('12:00:00', $service['data']['end_time']);

        $backwards = Rules::service([
            'name' => 'Worship',
            'weekday' => '0',
            'start_time' => '11:00',
            'end_time' => '11:00',
            'note' => '',
            'sort_order' => '1',
            'active' => '1',
            'weekly' => '1',
        ]);
        $this->assertArrayHasKey('end_time', $backwards['errors']);

        $gap = Rules::event([
            'title' => 'Study',
            'summary' => 'A study.',
            'starts_at' => '2026-03-08T02:30',
            'ends_at' => '',
            'location' => '',
            'published' => '1',
        ]);
        $this->assertArrayHasKey('starts_at', $gap['errors']);

        $ok = Rules::event([
            'title' => 'Study',
            'summary' => 'A study.',
            'starts_at' => '2026-10-25T18:30',
            'ends_at' => '2026-10-25T19:30',
            'location' => '',
            'published' => '0',
        ]);
        $this->assertSame([], $ok['errors']);
        $this->assertSame('2026-10-25 18:30:00', $ok['data']['starts_at']);
        $this->assertSame(0, $ok['data']['published']);
    }

    public function testLoginWindowCrossesTheNewYearAndSessionLimitsAreExact(): void
    {
        $now = new DateTimeImmutable('2026-01-01 00:10:00', new DateTimeZone('America/New_York'));
        $this->assertSame('2025-12-31 23:55:00', LoginGuard::windowStart($now));
        $this->assertFalse(LoginGuard::blocked(7));
        $this->assertTrue(LoginGuard::blocked(8));
        $this->assertFalse(LoginGuard::locked(0, 8));
        $this->assertTrue(LoginGuard::locked(8, 0));
        $this->assertFalse(LoginGuard::locked(7, 39));
        $this->assertTrue(LoginGuard::locked(0, 40));
        $this->assertFalse(SessionPolicy::stillValid(2, 1, false));
        $this->assertFalse(SessionPolicy::stillValid(1, 1, true));
        $this->assertTrue(SessionPolicy::stillValid(1, 1, false));

        $issued = 1_000_000;
        $this->assertFalse(SessionPolicy::expired($issued, $issued, $issued + SessionPolicy::IDLE_SECONDS - 1));
        $this->assertTrue(SessionPolicy::expired($issued, $issued, $issued + SessionPolicy::IDLE_SECONDS));
        $this->assertTrue(SessionPolicy::expired($issued + 10, $issued, $issued + SessionPolicy::ABSOLUTE_SECONDS));
        $this->assertTrue(SessionPolicy::expired($issued + 5, $issued, $issued));
    }

    public function testRateWindowAllowsFiveAndResetsOnTheHourAcrossNewYear(): void
    {
        $start = new DateTimeImmutable('2025-12-31 23:30:00', new DateTimeZone('America/New_York'));
        $state = RateWindow::next(null, null, $start);
        $this->assertSame(1, $state['hits']);
        $this->assertFalse($state['blocked']);
        for ($i = 2; $i <= 5; $i++) {
            $state = RateWindow::next($state['hits'], $state['window_start'], $start);
            $this->assertSame($i, $state['hits']);
            $this->assertFalse($state['blocked']);
        }
        $blocked = RateWindow::next($state['hits'], $state['window_start'], $start);
        $this->assertSame(6, $blocked['hits']);
        $this->assertTrue($blocked['blocked']);

        $almost = new DateTimeImmutable('2026-01-01 00:29:59', new DateTimeZone('America/New_York'));
        $still = RateWindow::next(5, '2025-12-31 23:30:00', $almost);
        $this->assertTrue($still['blocked']);
        $resetAt = new DateTimeImmutable('2026-01-01 00:30:00', new DateTimeZone('America/New_York'));
        $reset = RateWindow::next(6, '2025-12-31 23:30:00', $resetAt);
        $this->assertFalse($reset['blocked']);
        $this->assertSame(1, $reset['hits']);
        $this->assertSame('2026-01-01 00:30:00', $reset['window_start']);

        $contact = RateWindow::formBucket('contact', '203.0.113.10');
        $prayer = RateWindow::formBucket('prayer', '203.0.113.10');
        $this->assertSame(64, strlen($contact));
        $this->assertNotSame($contact, $prayer);
        $this->assertSame($contact, RateWindow::formBucket('contact', '203.0.113.10'));
        $this->assertDoesNotMatchRegularExpression('/203\.0\.113\.10/', $contact);
    }

    public function testGivingLinkMustBeHttps(): void
    {
        $this->assertNull(Links::httpsUrl(''));
        $this->assertNull(Links::httpsUrl('http://give.example.org/x'));
        $this->assertNull(Links::httpsUrl('javascript:alert(1)'));
        $this->assertNull(Links::httpsUrl('https://evil.example/"onclick'));
        $this->assertSame('https://give.example.org/olive', Links::httpsUrl('https://give.example.org/olive'));
        $this->assertNull(Links::safeHost("evil.example\r\n"));
        $this->assertSame('localhost:8080', Links::safeHost('localhost:8080'));
    }

    public function testPasswordLengthAndLastAdmin(): void
    {
        $short = Rules::user([
            'name' => 'Ada',
            'email' => 'ada@example.org',
            'role' => 'staff',
            'password' => 'short-pass',
        ], true);
        $this->assertArrayHasKey('password', $short['errors']);
        $this->assertFalse(Rules::keepsAnAdmin(1, 'admin', 'staff'));
        $this->assertTrue(Rules::keepsAnAdmin(2, 'admin', 'staff'));
        $this->assertTrue(Rules::keepsAnAdmin(1, 'staff', 'staff'));
    }

    public function testParsedCheckGiftCentsMatchTheStoredInteger(): void
    {
        $result = Rules::gift([
            'amount' => '$1,000.10',
            'date' => '2024-12-31',
            'method' => 'check',
            'reference' => '42',
            'fund_id' => '1',
            'person_id' => '3',
            'note' => 'envelope',
            'goods_or_services' => '',
        ], [1], [3]);
        $this->assertSame([], $result['errors']);
        $this->assertSame(100010, $result['data']['cents']);
        $this->assertSame('$1,000.10', Money::format($result['data']['cents']));
    }

    public function testGoodsNoteRequiresACentsEstimateAndAReasonIsRequiredToVoid(): void
    {
        $missing = Rules::gift([
            'amount' => '10.00',
            'date' => '2026-10-20',
            'method' => 'check',
            'reference' => '1003',
            'fund_id' => '1',
            'person_id' => '',
            'note' => '',
            'goods_or_services' => 'meal',
            'goods_value' => '',
        ], [1], []);
        $this->assertArrayHasKey('goods_value', $missing['errors']);

        $estimate = Rules::gift([
            'amount' => '100.00',
            'date' => '2026-10-20',
            'method' => 'check',
            'reference' => '1003',
            'fund_id' => '1',
            'person_id' => '',
            'note' => '',
            'goods_or_services' => 'meal',
            'goods_value' => '15.00',
        ], [1], []);
        $this->assertSame([], $estimate['errors']);
        $this->assertSame(1500, $estimate['data']['goods_value_cents']);

        $capped = Rules::gift([
            'amount' => '10.00',
            'date' => '2026-10-20',
            'method' => 'check',
            'reference' => '1003',
            'fund_id' => '1',
            'person_id' => '',
            'note' => '',
            'goods_or_services' => 'meal',
            'goods_value' => '10.00',
        ], [1], []);
        $this->assertSame([], $capped['errors']);
        $this->assertSame(1000, $capped['data']['goods_value_cents']);

        $over = Rules::gift([
            'amount' => '10.00',
            'date' => '2026-10-20',
            'method' => 'check',
            'reference' => '1003',
            'fund_id' => '1',
            'person_id' => '',
            'note' => '',
            'goods_or_services' => 'meal',
            'goods_value' => '500.00',
        ], [1], []);
        $this->assertSame('The good-faith estimate cannot be more than the gift.', $over['errors']['goods_value']);
        $this->assertNull($over['data']['goods_value_cents']);

        $blank = Rules::giftEntry(['kind' => 'void', 'reason' => '', 'amount' => '', 'direction' => ''], 50000);
        $this->assertArrayHasKey('reason', $blank['errors']);
        $void = Rules::giftEntry(['kind' => 'void', 'reason' => 'Bounced check', 'amount' => '', 'direction' => ''], 50000);
        $this->assertSame([], $void['errors']);
        $this->assertSame(-50000, $void['data']['amount_cents']);
    }

    public function testPrayerMailIsANoticeAndHandledRowsStayUntilAPeriodIsSet(): void
    {
        $notice = Mailer::prayerNotice('https://example.org/admin/inbox');
        $this->assertStringContainsString('A new prayer request is waiting.', $notice);
        $this->assertStringContainsString('/admin/inbox', $notice);
        $this->assertStringNotContainsString('please heal', $notice);
        $this->assertFalse(Mailer::acceptableFrom('', 'example.org'));
        $this->assertFalse(Mailer::acceptableFrom('office@gmail.com', 'example.org'));
        $this->assertTrue(Mailer::acceptableFrom('office@example.org', 'www.example.org'));
        $this->assertTrue(Mailer::acceptableFrom('office@example.org', '127.0.0.1'));
        $now = new DateTimeImmutable('2026-04-01 12:00:00', new DateTimeZone('America/New_York'));
        $this->assertNull(Retention::handledDays(null));
        $this->assertNull(Retention::handledDays(''));
        $this->assertNull(Retention::handledDays(0));
        $this->assertNull(Retention::handledDays('90 days'));
        $this->assertSame(90, Retention::handledDays(90));
        $this->assertSame(90, Retention::handledDays('90'));
        $this->assertSame('2026-01-01 12:00:00', Retention::cutoff($now, 90));
    }

    public function testReceivingOrgChangeRequiresAReasonAndTheHubIsNotMatchedByName(): void
    {
        $changed = Rules::fund([
            'name' => 'Community fund',
            'sort_order' => '40',
            'active' => '1',
            'receiving_org' => 'church',
            'reason' => '',
        ], 'unconfirmed');
        $this->assertArrayHasKey('reason', $changed['errors']);

        $kept = Rules::fund([
            'name' => 'Community fund',
            'sort_order' => '40',
            'active' => '1',
            'receiving_org' => 'church',
            'reason' => 'The church confirmed this fund',
        ], 'unconfirmed');
        $this->assertSame([], $kept['errors']);
        $this->assertSame('church', $kept['data']['receiving_org']);

        $same = Rules::fund([
            'name' => 'General',
            'sort_order' => '10',
            'active' => '1',
            'receiving_org' => 'church',
            'reason' => '',
        ], 'church');
        $this->assertSame([], $same['errors']);

        $migration = (string) file_get_contents(dirname(__DIR__) . '/sql/migrate-review.sql');
        $schema = (string) file_get_contents(dirname(__DIR__) . '/schema.sql');
        $this->assertStringContainsString(
            "SET code = '" . GiftPolicy::HUB_CODE . "' WHERE id = " . GiftPolicy::HUB_SEED_ID,
            $migration,
        );
        $this->assertDoesNotMatchRegularExpression("/name\\s*=\\s*'The Hub'/", $migration);
        $this->assertStringContainsString('ADD COLUMN IF NOT EXISTS', $migration);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS', $migration);
        $this->assertStringContainsString('ADD CONSTRAINT IF NOT EXISTS chk_goods_value', $migration);
        $this->assertStringContainsString('ADD CONSTRAINT IF NOT EXISTS chk_receiving_org', $migration);
        $this->assertStringContainsString("('The Hub', 40, 1, 'unconfirmed', 'hub')", $schema);
    }

    public function testAddressGuardCatchesAvenueSpellings(): void
    {
        $this->assertSame(1, preg_match(HtmlCheck::ADDRESS_PATTERN, '16' . '01 ' . 'Second' . ' Ave'));
        $this->assertSame(1, preg_match(HtmlCheck::ADDRESS_PATTERN, '2' . 'nd' . "  " . 'Av'));
        $this->assertSame(0, preg_match(HtmlCheck::ADDRESS_PATTERN, '1033 1st Street SE'));
    }
}
