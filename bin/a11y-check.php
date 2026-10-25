<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/helpers.php';

use Obm\HtmlCheck;
use Obm\Tests\FixturePages;

$failed = false;
foreach (FixturePages::documents() as $name => $html) {
    $problems = HtmlCheck::problems($html);
    if ($problems !== []) {
        $failed = true;
        fwrite(STDERR, $name . "\n");
        foreach ($problems as $problem) {
            fwrite(STDERR, '  ' . $problem . "\n");
        }
    }
}
$source = HtmlCheck::sourceProblems(dirname(__DIR__));
foreach ($source as $problem) {
    $failed = true;
    fwrite(STDERR, $problem . "\n");
}
if ($failed) {
    exit(1);
}
fwrite(STDOUT, "Accessibility and content checks passed.\n");
