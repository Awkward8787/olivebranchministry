<?php

declare(strict_types=1);

namespace Obm;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Structural checks for rendered pages and a source scan for facts
 * that must not appear outside their allowed files.
 */
final class HtmlCheck
{
    /** @return list<string> */
    public static function problems(string $html): array
    {
        $problems = [];
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($loaded === false) {
            return ['Document did not parse.'];
        }

        $root = $dom->getElementsByTagName('html')->item(0);
        if (!$root instanceof DOMElement || strtolower($root->getAttribute('lang')) !== 'en') {
            $problems[] = 'html lang must be en.';
        }
        if ($dom->getElementsByTagName('h1')->length !== 1) {
            $problems[] = 'Expected one h1, found ' . $dom->getElementsByTagName('h1')->length . '.';
        }
        $xpath = new DOMXPath($dom);
        $skip = $xpath->query('//a[starts-with(@href, "#content") or contains(@href, "#content")]');
        if ($skip === false || $skip->length < 1) {
            $problems[] = 'Missing skip link.';
        }
        if ($dom->getElementById('content') === null) {
            $problems[] = 'Missing #content.';
        }
        if ($dom->getElementsByTagName('main')->length < 1) {
            $problems[] = 'Missing main.';
        }
        $titleNode = $dom->getElementsByTagName('title')->item(0);
        if ($titleNode === null || trim($titleNode->textContent) === '') {
            $problems[] = 'Missing title.';
        }
        $viewport = $xpath->query('//meta[@name="viewport"]');
        if ($viewport === false || $viewport->length < 1) {
            $problems[] = 'Missing viewport.';
        }

        foreach ($dom->getElementsByTagName('img') as $img) {
            if (!$img instanceof DOMElement) {
                continue;
            }
            if (!$img->hasAttribute('alt')) {
                $problems[] = 'Image missing alt.';
            }
            $class = ' ' . $img->getAttribute('class') . ' ';
            if (str_contains($class, ' media ') && trim($img->getAttribute('alt')) === '') {
                $problems[] = 'Media image needs a text alternative.';
            }
        }
        foreach (['input', 'select', 'textarea'] as $tag) {
            foreach ($dom->getElementsByTagName($tag) as $control) {
                if ($control instanceof DOMElement) {
                    self::controlLabeled($control, $problems);
                }
            }
        }
        foreach ($dom->getElementsByTagName('button') as $button) {
            if (!$button instanceof DOMElement) {
                continue;
            }
            $name = trim($button->textContent);
            if ($name === '' && trim($button->getAttribute('aria-label')) === '') {
                $problems[] = 'Button has no accessible name.';
            }
        }

        $text = $dom->textContent ?? '';
        if (stripos($text, 'lorem ipsum') !== false) {
            $problems[] = 'Placeholder text is present.';
        }
        if (preg_match('/1601|2nd Ave/i', $text) === 1) {
            $problems[] = 'Unconfirmed address appeared.';
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    public static function sourceProblems(string $root): array
    {
        $problems = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            $relative = substr($path, strlen(rtrim($root, '/')) + 1);
            if (self::skipSource($relative)) {
                continue;
            }
            $contents = file_get_contents($path);
            if (!is_string($contents)) {
                continue;
            }
            if (stripos($contents, 'lorem ipsum') !== false) {
                $problems[] = $relative . ' contains placeholder text.';
            }
            if (preg_match('/1601|2nd Ave/i', $contents) === 1 && $relative !== 'docs/CHURCH-TODO.md') {
                $problems[] = $relative . ' mentions the unconfirmed address.';
            }
            if (str_contains($contents, '41-5032500') || str_contains($contents, '415032500')) {
                $allowed = $relative === 'src/Church.php'
                    || $relative === 'src/Statements.php'
                    || $relative === 'docs/CHURCH-TODO.md'
                    || str_starts_with($relative, 'tests/');
                if (!$allowed) {
                    $problems[] = $relative . ' includes The Hub EIN.';
                }
            }
        }

        return $problems;
    }

    private static function skipSource(string $relative): bool
    {
        if (
            str_starts_with($relative, 'vendor/')
            || str_starts_with($relative, '.git/')
            || str_starts_with($relative, '.phpunit.cache/')
            || $relative === 'src/HtmlCheck.php'
        ) {
            return true;
        }
        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif', 'ico'], true);
    }

    /** @param list<string> $problems */
    private static function controlLabeled(DOMElement $el, array &$problems): void
    {
        $type = strtolower($el->getAttribute('type'));
        if (in_array($type, ['hidden', 'submit', 'button'], true)) {
            return;
        }
        if ($el->getAttribute('name') === 'obm_leave_blank') {
            return;
        }
        if (trim($el->getAttribute('aria-label')) !== '' || trim($el->getAttribute('aria-labelledby')) !== '') {
            return;
        }
        $parent = $el->parentNode;
        while ($parent instanceof DOMElement) {
            if (strtolower($parent->tagName) === 'label') {
                return;
            }
            $parent = $parent->parentNode;
        }
        $id = $el->getAttribute('id');
        if ($id !== '') {
            $doc = $el->ownerDocument;
            if ($doc !== null) {
                foreach ($doc->getElementsByTagName('label') as $label) {
                    if ($label instanceof DOMElement && $label->getAttribute('for') === $id) {
                        return;
                    }
                }
            }
        }
        $problems[] = 'Control ' . ($el->getAttribute('name') ?: $el->tagName) . ' has no label.';
    }
}
