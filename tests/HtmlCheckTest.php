<?php

declare(strict_types=1);

namespace Obm\Tests;

use Obm\HtmlCheck;
use Obm\SchemaOrg;
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
        $this->assertStringContainsString('Portrait of our pastors', $pages['leadership']);
        $this->assertStringContainsString('>Our Pastors<', $pages['leadership']);
        $this->assertStringContainsString('Pastor Jerrell Anderson', $pages['leadership']);
        $this->assertStringContainsString('Rooted in Faith, Reaching in Love', $pages['home']);
        $this->assertStringContainsString('41-5032500', $pages['hub']);
        $this->assertStringNotContainsString('41-5032500', $pages['statement']);
        $this->assertStringNotContainsString('415032500', $pages['statement']);
        $this->assertStringContainsString('No goods or services were provided in exchange for these contributions.', $pages['statement']);
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
