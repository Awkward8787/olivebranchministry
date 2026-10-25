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

    /** @var list<array{name:string, role:string}> */
    public const LEADERS = [
        ['name' => 'Pastor Jerrell Anderson', 'role' => 'Lead Pastor'],
        ['name' => 'Pastor Carmen James-Anderson', 'role' => 'Pastor'],
        ['name' => 'Pastor Debbie Herring', 'role' => 'Pastor'],
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

    public const HUB_NAME = 'The Hub at Olive Branch';

    public const HUB_LEGAL = 'The Hub at Olive Branch Inc.';

    public const HUB_EIN = '41-5032500';

    public const HUB_OPENED = 'around August 2026';

    /** @var list<string> */
    public const HUB_PARTNERS = [
        'Georgia Mental Health Consumer Network',
        'Yellow Elephant',
        'Hope House',
    ];

    public const HUB_DRIVE = 'Fill the Tree';

    public static function cityLine(): string
    {
        return self::CITY . ', ' . self::REGION . ' ' . self::POSTAL;
    }

    public static function oneLineAddress(): string
    {
        return self::STREET . ', ' . self::cityLine();
    }
}
