<?php

declare(strict_types=1);

namespace Obm\Tests;

use Obm\HtmlCheck;
use Obm\SchemaOrg;
use Obm\Statements;
use PHPUnit\Framework\TestCase;

final class HtmlCheckTest extends TestCase
{
    /** @return array<string, string> */
    private function pages(): array
    {
        return FixturePages::documents();
    }

    public function testRenderedPagesAreStructurallyAccessible(): void
    {
        foreach ($this->pages() as $name => $html) {
            $problems = HtmlCheck::problems($html);
            $this->assertSame([], $problems, $name . ' ' . implode('; ', $problems));
        }
    }

    public function testPublicCopyStaysInsideTheVerifiedFacts(): void
    {
        $pages = $this->pages();
        $this->assertStringNotContainsString('<iframe', $pages['visit']);
        $this->assertStringContainsString('data-load-map', $pages['visit']);
        $this->assertStringContainsString('maps.google.com', $pages['visit']);
        $this->assertStringNotContainsString('Give online', $pages['give']);
        $this->assertStringContainsString('https://give.example.org/olive', $pages['give-online']);
        $this->assertStringContainsString('Pastor Jerrell Anderson and Pastor Carmen James-Anderson of Olive Branch Ministries', $pages['leadership']);
        $this->assertStringContainsString('Pastor Jerrell Anderson &amp; Pastor Carmen James-Anderson', $pages['leadership']);
        $this->assertStringContainsString('Pastor Jerrell Anderson', $pages['leadership']);
        $this->assertStringContainsString('Pastor Carmen James-Anderson', $pages['leadership']);
        $this->assertStringNotContainsString('Our Pastors', $pages['leadership']);
        $this->assertStringNotContainsString('Debbie', $pages['leadership']);
        $this->assertStringNotContainsString('Lead Pastor', $pages['leadership']);
        $this->assertStringContainsString('Rooted in Faith, Reaching in Love', $pages['home']);
        $this->assertStringContainsString('The Hub', $pages['hub']);
        $this->assertStringNotContainsString('41-5032500', $pages['hub']);
        $this->assertStringNotContainsString('501(c)(3)', $pages['hub']);
        $this->assertStringNotContainsString('Fill the Tree', $pages['hub']);
        $this->assertStringNotContainsString('August 2026', $pages['hub']);
        $this->assertStringNotContainsString('at 1033', $pages['hub']);
        $this->assertStringNotContainsString('community resource', $pages['hub']);
        $this->assertStringNotContainsString('around', $pages['hub']);
        $this->assertStringNotContainsString('41-5032500', $pages['statement']);
        $this->assertStringContainsString('intangible religious benefits', $pages['statement']);
        $this->assertStringNotContainsString('415032500', $pages['statement']);
        $this->assertStringContainsString(Statements::NO_GOODS, $pages['statement']);
        $this->assertStringNotContainsString('Intangible religious benefits were provided.', $pages['statement']);
        $this->assertStringNotContainsString('latitude', $pages['home']);
        $this->assertStringNotContainsString('"geo"', $pages['home']);
        $this->assertStringContainsString('DEMO DATA', $pages['dashboard']);
        $this->assertStringNotContainsString('DEMO DATA', $pages['home']);
    }

    public function testFourthSundayIsNotWeeklyOpeningHours(): void
    {
        $data = SchemaOrg::church(FixturePages::services(), 'https://example.org/');
        $this->assertArrayNotHasKey('geo', $data);
        $descriptions = [];
        foreach ($data['openingHoursSpecification'] as $spec) {
            $descriptions[] = $spec['description'];
        }
        $this->assertNotContains('Evening Service', $descriptions);
        $this->assertContains('Worship', $descriptions);
        $this->assertStringContainsString('Fourth Sunday', $data['description']);
    }

    public function testSourceDoesNotLeakUnconfirmedFacts(): void
    {
        $problems = HtmlCheck::sourceProblems(dirname(__DIR__));
        $this->assertSame([], $problems, implode("\n", $problems));
    }
}
