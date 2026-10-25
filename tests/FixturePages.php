<?php

declare(strict_types=1);

namespace Obm\Tests;

use Obm\Money;
use Obm\SchemaOrg;
use Obm\Statements;
use Obm\View;

final class FixturePages
{
    /** @return array<string, string> */
    public static function documents(): array
    {
        require_once dirname(__DIR__) . '/src/helpers.php';
        $services = self::services();
        $schema = SchemaOrg::church($services, 'https://example.org/', 'https://example.org/assets/img/banner-1284.jpg');
        $csrf = '<input type="hidden" name="csrf" value="fixture-token-fixture-token-fixture">';
        $pages = [
            'home' => self::pub('home', 'Olive Branch Ministries Inc.', 'Sunday worship is at 11:00 AM.', '/', ['services' => $services], $schema),
            'about' => self::pub('about', 'About', 'Mission', '/about', [], $schema),
            'leadership' => self::pub('leadership', 'Leadership', 'Pastors', '/leadership', [], $schema),
            'ministries' => self::pub('ministries', 'Ministries', 'Ministries', '/ministries', ['services' => $services], $schema),
            'hub' => self::pub('hub', 'The Hub', 'The Hub', '/the-hub', [], $schema),
            'events' => self::pub('events', 'Events', 'Events', '/events', ['events' => []], $schema),
            'visit' => self::pub('visit', 'Plan your visit', 'Visit', '/plan-your-visit', [
                'services' => $services,
                'mapLink' => 'https://maps.google.com/?q=1033+1st+Street+SE',
                'mapEmbed' => 'https://maps.google.com/maps?q=1033+1st+Street+SE&output=embed',
            ], $schema),
            'give' => self::pub('give', 'Give', 'Give', '/give', ['onlineUrl' => null], $schema),
            'give-online' => self::pub('give', 'Give', 'Give', '/give', ['onlineUrl' => 'https://give.example.org/olive'], $schema),
            'contact' => self::pub('contact', 'Contact', 'Contact', '/contact', [
                'errors' => [],
                'form' => [],
                'csrfField' => $csrf,
                'errorKind' => '',
            ], $schema),
            'received' => self::pub('received', 'Message received', 'Received', '/contact/received', ['kind' => 'contact']),
            'not-found' => self::pub('not-found', 'Page not found', 'Missing', '/404', [], null, 'noindex'),
            'limited' => self::pub('limited', 'Please wait', 'Wait', '/contact', [], null, 'noindex'),
        ];

        $adminBase = [
            'csrfField' => $csrf,
            'flash' => null,
            'demo' => true,
            'user' => ['id' => 1, 'email' => 'office@example.org', 'role' => 'admin', 'name' => 'Office'],
        ];
        $pages['dashboard'] = View::adminHtml('dashboard', array_merge($adminBase, [
            'title' => 'Dashboard',
            'section' => 'dashboard',
            'monthLabel' => 'October 1, 2026 to October 31, 2026',
            'report' => [
                'by_fund' => ['General' => 17500, 'The Hub' => 7510, 'Building' => 10000, 'Tithes' => 0, 'Offering' => 0],
                'total' => 35010,
                'count' => 4,
                'donor_statement_total' => 32510,
                'anonymous_total' => 2500,
                'fund_total' => 35010,
            ],
            'attendance' => ['records' => 7, 'sum' => 205, 'average_tenths' => 293, 'average_display' => '29.3'],
            'recent' => [
                ['date' => '2026-10-18', 'label' => 'Worship', 'count' => 45],
            ],
            'waiting' => [
                ['id' => 2, 'first_name' => 'Demo', 'last_name' => 'Visitor', 'follow_up_status' => 'new', 'follow_up_on' => null],
            ],
            'unread' => ['contact' => 1, 'prayer' => 1],
        ]));
        $pages['giving'] = View::adminHtml('giving', array_merge($adminBase, [
            'title' => 'Giving',
            'section' => 'giving',
            'mode' => 'list',
            'which' => 'month',
            'from' => '',
            'to' => '',
            'rangeError' => null,
            'rangeLabel' => 'October 1, 2026 to October 31, 2026',
            'report' => [
                'by_fund' => ['General' => 17500, 'Tithes' => 0, 'Offering' => 0, 'The Hub' => 7510, 'Building' => 10000],
                'total' => 35010,
                'fund_total' => 35010,
                'donor_statement_total' => 32510,
                'anonymous_total' => 2500,
            ],
            'gifts' => [
                [
                    'id' => 1,
                    'date' => '2026-10-04',
                    'donor_name' => 'Demo Donor',
                    'fund' => 'General',
                    'method' => 'cash',
                    'reference' => null,
                    'cents' => Money::parseCents('150.00'),
                ],
            ],
            'isAdmin' => true,
        ]));
        $pages['statement'] = View::adminHtml('statement', array_merge($adminBase, [
            'title' => 'Giving statement',
            'section' => 'giving',
            'people' => [
                ['id' => 1, 'first_name' => 'Demo', 'last_name' => 'Donor'],
            ],
            'person' => ['id' => 1, 'first_name' => 'Demo', 'last_name' => 'Donor'],
            'year' => '2026',
            'rangeLabel' => 'January 1, 2026 to December 31, 2026',
            'issued' => 'October 25, 2026',
            'error' => null,
            'ein' => null,
            'extra' => '',
            'statement' => [
                'lines' => [
                    ['date' => '2026-01-01', 'fund' => 'Tithes', 'method' => 'check', 'cents' => Money::parseCents('500.00')],
                ],
                'total_cents' => Money::parseCents('500.00'),
                'language' => [Statements::NO_GOODS],
            ],
        ]));
        $pages['login'] = View::adminHtml('login', [
            'title' => 'Sign in',
            'section' => 'login',
            'csrfField' => $csrf,
            'flash' => null,
            'demo' => false,
            'user' => null,
            'error' => null,
            'email' => '',
            'timeout' => false,
        ]);

        return $pages;
    }

    /** @return list<array<string, mixed>> */
    public static function services(): array
    {
        return [
            ['id' => 1, 'name' => 'Sunday School', 'weekday' => 0, 'start_time' => '09:30:00', 'end_time' => null, 'note' => null, 'weekly' => 1],
            ['id' => 2, 'name' => 'Worship', 'weekday' => 0, 'start_time' => '11:00:00', 'end_time' => null, 'note' => null, 'weekly' => 1],
            ['id' => 3, 'name' => 'Bible Study & Youth', 'weekday' => 4, 'start_time' => '18:30:00', 'end_time' => null, 'note' => null, 'weekly' => 1],
            ['id' => 4, 'name' => 'Evening Service', 'weekday' => 0, 'start_time' => '18:00:00', 'end_time' => null, 'note' => 'Fourth Sunday', 'weekly' => 0],
            ['id' => 5, 'name' => 'Prayer & Fasting', 'weekday' => 6, 'start_time' => '10:00:00', 'end_time' => '12:00:00', 'note' => null, 'weekly' => 1],
        ];
    }

    /** @param array<string, mixed> $extra */
    private static function pub(string $template, string $title, string $description, string $path, array $extra, ?array $jsonLd = null, ?string $robots = null): string
    {
        return View::html($template, array_merge($extra, [
            'page' => $template,
            'title' => $title,
            'description' => $description,
            'canonical' => 'https://example.org' . $path,
            'base' => 'https://example.org',
            'jsonLd' => $jsonLd,
            'robots' => $robots,
            'image' => 'https://example.org/assets/img/banner-1284.jpg',
        ]));
    }
}
