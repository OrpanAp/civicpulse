<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use CivicPulse\Framework\View\HtmlSanitizer;
use CivicPulse\Framework\View\Exceptions\ViewException;

$sanitizer = new HtmlSanitizer();

$tests = [
    'plain text' => [
        'input' => 'Hello world',
        'expected' => 'Hello world',
    ],

    'allowed formatting' => [
        'input' => '<p>Hello <strong>world</strong></p>',
        'expected' => '<p>Hello <strong>world</strong></p>',
    ],

    'Bengali and entities' => [
        'input' => '<p>বাংলা &amp; English</p>',
        'expected' => '<p>বাংলা &amp; English</p>',
    ],

    'dangerous script removed' => [
        'input' => '<p>Safe</p><script>alert(1)</script>',
        'expected' => '<p>Safe</p>',
    ],

    'event attributes removed' => [
        'input' => '<p onclick="alert(1)">Safe</p>',
        'expected' => '<p>Safe</p>',
    ],

    'style attribute removed' => [
        'input' => '<p style="color:red">Safe</p>',
        'expected' => '<p>Safe</p>',
    ],

    'javascript URL removed' => [
        'input' => '<a href="javascript:alert(1)">Click</a>',
        'expected' => '<a>Click</a>',
    ],

    'HTTP URL preserved' => [
        'input' => '<a href="https://example.com">Visit</a>',
        'expected' => '<a href="https://example.com">Visit</a>',
    ],

    'relative URL preserved' => [
        'input' => '<a href="/reports/123">Report</a>',
        'expected' => '<a href="/reports/123">Report</a>',
    ],

    'unsupported harmless element unwrapped' => [
        'input' => '<mark>Important</mark>',
        'expected' => 'Important',
    ],

    'nested dangerous element removed' => [
        'input' => '<div>Safe<script><strong>Unsafe</strong></script></div>',
        'expected' => '<div>Safe</div>',
    ],

    'protocol relative URL removed' => [
        'input' => '<a href="//evil.example/path">Link</a>',
        'expected' => '<a>Link</a>',
    ],

    'data URL removed' => [
        'input' => '<a href="data:text/html,test">Link</a>',
        'expected' => '<a>Link</a>',
    ],

    'title attribute preserved on link' => [
        'input' => '<a href="/help" title="Help page">Help</a>',
        'expected' => '<a href="/help" title="Help page">Help</a>',
    ],

    'empty input' => [
        'input' => '',
        'expected' => '',
    ],

    'unsupported wrapper cannot preserve script' => [
        'input' => '<mark>Safe<script>alert(1)</script></mark>',
        'expected' => 'Safe',
    ],

    'unsupported wrapper preserves safe nested formatting' => [
        'input' => '<mark><strong>Important</strong></mark>',
        'expected' => '<strong>Important</strong>',
    ],

    'multiple unsafe attributes removed' => [
        'input' => '<p onclick="alert(1)" style="color:red" id="x" class="y">Safe</p>',
        'expected' => '<p>Safe</p>',
    ],

    'multiple unsafe link attributes removed' => [
        'input' => '<a href="javascript:alert(1)" onclick="alert(2)" id="x">Link</a>',
        'expected' => '<a>Link</a>',
    ],

    'unsupported wrapper cannot preserve iframe' => [
        'input' => '<mark>Before<iframe><p>Unsafe</p></iframe>After</mark>',
        'expected' => 'BeforeAfter',
    ],

    'HTML entity encoded javascript URL removed' => [
        'input' => '<a href="java&#x73;cript:alert(1)">Link</a>',
        'expected' => '<a>Link</a>',
    ],

    'percent encoded scheme-like URL removed' => [
        'input' => '<a href="java%73cript:alert(1)">Link</a>',
        'expected' => '<a>Link</a>',
    ],

    'percent encoded newline rejected' => [
        'input' => '<a href="/reports/%0Aadmin">Link</a>',
        'expected' => '<a>Link</a>',
    ],

    'percent encoded null byte rejected' => [
        'input' => '<a href="/reports/%00admin">Link</a>',
        'expected' => '<a>Link</a>',
    ],

    'malformed percent encoding rejected' => [
        'input' => '<a href="/reports/%GG">Link</a>',
        'expected' => '<a>Link</a>',
    ],

    'whitespace in URL rejected' => [
        'input' => '<a href="/reports/my report">Link</a>',
        'expected' => '<a>Link</a>',
    ],

    'leading whitespace in URL rejected' => [
        'input' => '<a href=" /reports/123">Link</a>',
        'expected' => '<a>Link</a>',
    ],

    'URL containing credentials rejected' => [
        'input' => '<a href="https://user:pass@example.com">Link</a>',
        'expected' => '<a>Link</a>',
    ],

    'HTTP URL without host rejected' => [
        'input' => '<a href="https:///reports">Link</a>',
        'expected' => '<a>Link</a>',
    ],

    'mixed case script element removed' => [
        'input' => '<ScRiPt>alert(1)</ScRiPt><p>Safe</p>',
        'expected' => '<p>Safe</p>',
    ],

    'HTML comments removed' => [
        'input' => '<!-- comment --><p>Safe</p>',
        'expected' => '<p>Safe</p>',
    ],

    'SVG subtree removed' => [
        'input' => '<svg><text>Unsafe</text></svg><p>Safe</p>',
        'expected' => '<p>Safe</p>',
    ],

    'MathML subtree removed' => [
        'input' => '<math><mi>Unsafe</mi></math><p>Safe</p>',
        'expected' => '<p>Safe</p>',
    ],

    'encoded tab in javascript URL rejected' => [
        'input' => '<a href="java&#x09;script:alert(1)">Link</a>',
        'expected' => '<a>Link</a>',
    ],

    'encoded newline in javascript URL rejected' => [
        'input' => '<a href="java&#x0A;script:alert(1)">Link</a>',
        'expected' => '<a>Link</a>',
    ],

    'malformed nested HTML remains sanitized' => [
        'input' => '<div><p>Safe<script>alert(1)</script><p>Also safe</div>',
        'expected' => '<div><p>Safe</p><p>Also safe</p></div>',
    ],
];

$passed = 0;
$failed = 0;

foreach ($tests as $name => $test) {
    $actual = $sanitizer->sanitize($test['input']);

    if ($actual === $test['expected']) {
        echo "[PASS] {$name}" . PHP_EOL;
        $passed++;
        continue;
    }

    echo "[FAIL] {$name}" . PHP_EOL;
    echo "  Expected: {$test['expected']}" . PHP_EOL;
    echo "  Actual:   {$actual}" . PHP_EOL;

    $failed++;
}

// Invalid UTF-8 must be rejected.
try {
    $sanitizer->sanitize("\xB1\x31");

    echo "[FAIL] invalid UTF-8 rejected" . PHP_EOL;
    $failed++;
} catch (ViewException) {
    echo "[PASS] invalid UTF-8 rejected" . PHP_EOL;
    $passed++;
}

// Oversized input must be rejected.
try {
    $sanitizer->sanitize(str_repeat('a', 102401));

    echo "[FAIL] oversized input rejected" . PHP_EOL;
    $failed++;
} catch (ViewException) {
    echo "[PASS] oversized input rejected" . PHP_EOL;
    $passed++;
}

echo PHP_EOL;
echo "Passed: {$passed}" . PHP_EOL;
echo "Failed: {$failed}" . PHP_EOL;

exit($failed === 0 ? 0 : 1);
