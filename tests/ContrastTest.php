<?php

declare(strict_types=1);

namespace Obm\Tests;

use PHPUnit\Framework\TestCase;

final class ContrastTest extends TestCase
{
    public function testTokensMeetThePairsWeActuallyPaint(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__) . '/public/assets/css/site.css');
        preg_match_all('/--([a-z0-9-]+):\s*(#[0-9a-fA-F]{6})\s*;/', $css, $matches, PREG_SET_ORDER);
        $tokens = [];
        foreach ($matches as $match) {
            $tokens[$match[1]] = strtolower($match[2]);
        }
        $this->assertSame('#f7f6f1', $tokens['paper']);
        $this->assertSame('#15210b', $tokens['ink']);
        $this->assertSame('#15210b', $tokens['olive']);
        $this->assertSame('#3e4a32', $tokens['muted']);
        $this->assertSame('#c0a050', $tokens['gold']);
        $this->assertSame('#7a5c22', $tokens['gold-rule']);
        $this->assertSame('#7a211c', $tokens['danger']);
        $this->assertSame('#f8ecea', $tokens['danger-bg']);

        $this->assertGreaterThanOrEqual(7.0, $this->contrast($tokens['ink'], $tokens['paper']));
        $this->assertGreaterThanOrEqual(4.5, $this->contrast($tokens['muted'], $tokens['paper']));
        $this->assertGreaterThanOrEqual(4.5, $this->contrast($tokens['gold'], $tokens['olive']));
        $this->assertGreaterThanOrEqual(4.5, $this->contrast($tokens['gold-rule'], $tokens['paper']));
        $this->assertGreaterThanOrEqual(4.5, $this->contrast($tokens['danger'], $tokens['paper']));
        $this->assertGreaterThanOrEqual(4.5, $this->contrast($tokens['danger'], $tokens['danger-bg']));
        $this->assertGreaterThanOrEqual(7.0, $this->contrast($tokens['paper'], $tokens['olive']));
        $this->assertLessThan(3.0, $this->contrast($tokens['gold'], $tokens['paper']));
    }

    public function testGoldTextIsOnlyOnOliveSurfaces(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__) . '/public/assets/css/site.css');
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;
        preg_match_all('/(?<![\w-])color:\s*var\(--gold\)/', $css, $hits, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($hits[0]);
        foreach ($hits[0] as $hit) {
            $prefix = substr($css, 0, $hit[1]);
            $brace = strrpos($prefix, '{');
            $this->assertNotFalse($brace);
            $start = strrpos($prefix, '}');
            $selector = substr($prefix, $start === false ? 0 : $start + 1, $brace);
            $allowed = str_contains($selector, '.site-header')
                || str_contains($selector, '.site-footer')
                || str_contains($selector, '.admin-bar')
                || str_contains($selector, '.hub-band');
            $this->assertTrue($allowed, $selector);
        }
    }

    private function contrast(string $a, string $b): float
    {
        $light = max($this->luminance($a), $this->luminance($b));
        $dark = min($this->luminance($a), $this->luminance($b));

        return ($light + 0.05) / ($dark + 0.05);
    }

    private function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
        $linear = [];
        foreach ($channels as $channel) {
            $linear[] = $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
