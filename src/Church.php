<?php

declare(strict_types=1);

namespace Obm;

/**
 * Published facts only. Unknown details are omitted rather than invented.
 */
final class Church
{
    public const NAME = 'Olive Branch Ministries Inc.';

    public const TAGLINE = 'Rooted in Faith, Reaching in Love';

    public const STREET = '1033 1st Street SE';

    public const CITY = 'Moultrie';

    public const REGION = 'GA';

    public const POSTAL = '31768';

    public const PHONE_DISPLAY = '229-531-9673';

    public const PHONE_TEL = '+12295319673';

    public const PHONE_E164 = '+1-229-531-9673';

    public const EMAIL = 'olivebranchministriesinc@gmail.com';

    public const FACEBOOK = 'https://www.facebook.com/profile.php?id=61585852584784';

    public const MISSION = 'To cultivate spiritual growth by restoring individuals to wholeness, equipping them for service, and uniting them in Christ-centered communities.';

    public const PASTOR_CAPTION = 'Pastor Jerrell Anderson & Pastor Carmen James-Anderson';

    public const PASTOR_ALT = 'Pastor Jerrell Anderson and Pastor Carmen James-Anderson of Olive Branch Ministries';

    /** @var list<string> */
    public const LEADERS = [
        'Pastor Jerrell Anderson',
        'Pastor Carmen James-Anderson',
    ];

    /** @var list<string> */
    public const MINISTRIES = [
        "Men's Ministry",
        'Youth Ministry',
        'Prayer & Fasting',
    ];

    /** Service names that match a ministry. Men's Ministry has no listed time. */
    /** @var list<string> */
    public const MINISTRY_SERVICE_NAMES = [
        'Bible Study & Youth',
        'Prayer & Fasting',
    ];

    /**
     * The published schedule from schema.sql, used when the database is down.
     *
     * @return list<array<string, mixed>>
     */
    public static function publishedServices(): array
    {
        return [
            ['id' => 0, 'name' => 'Sunday School', 'weekday' => 0, 'start_time' => '09:30:00', 'end_time' => null, 'note' => null, 'sort_order' => 10, 'active' => 1, 'weekly' => 1],
            ['id' => 0, 'name' => 'Worship', 'weekday' => 0, 'start_time' => '11:00:00', 'end_time' => null, 'note' => null, 'sort_order' => 20, 'active' => 1, 'weekly' => 1],
            ['id' => 0, 'name' => 'Bible Study & Youth', 'weekday' => 4, 'start_time' => '18:30:00', 'end_time' => null, 'note' => null, 'sort_order' => 30, 'active' => 1, 'weekly' => 1],
            ['id' => 0, 'name' => 'Evening Service', 'weekday' => 0, 'start_time' => '18:00:00', 'end_time' => null, 'note' => 'Fourth Sunday', 'sort_order' => 40, 'active' => 1, 'weekly' => 0],
            ['id' => 0, 'name' => 'Prayer & Fasting', 'weekday' => 6, 'start_time' => '10:00:00', 'end_time' => '12:00:00', 'note' => null, 'sort_order' => 50, 'active' => 1, 'weekly' => 1],
        ];
    }

    public static function cityLine(): string
    {
        return self::CITY . ', ' . self::REGION . ' ' . self::POSTAL;
    }

    public static function oneLineAddress(): string
    {
        return self::STREET . ', ' . self::cityLine();
    }
}
