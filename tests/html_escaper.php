<?php

declare(strict_types=1);

use CivicPulse\Framework\View\HtmlEscaper;

require_once __DIR__ . '/autoload.php';

echo "Testing HtmlEscaper...\n";

$escaper = new HtmlEscaper();

$passed = 0;
$failed = 0;

$assert = static function (
    bool $condition,
    string $description
) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "[PASS] {$description}\n";
        return;
    }

    $failed++;
    echo "[FAIL] {$description}\n";
};

// Test 1: Escape HTML tags.
$assert(
    $escaper->escape('<h1>Hello</h1>')
        === '&lt;h1&gt;Hello&lt;/h1&gt;',
    'Escapes HTML tags'
);

// Test 2: Escape a script injection payload.
$assert(
    $escaper->escape('<script>alert("XSS")</script>')
        === '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;',
    'Escapes script injection payloads'
);

// Test 3: Escape double quotes.
$assert(
    $escaper->escape('"CivicPulse"')
        === '&quot;CivicPulse&quot;',
    'Escapes double quotes'
);

// Test 4: Escape single quotes.
$assert(
    $escaper->escape("'CivicPulse'")
        === '&#039;CivicPulse&#039;',
    'Escapes single quotes'
);

// Test 5: Escape ampersands.
$assert(
    $escaper->escape('Roads & Bridges')
        === 'Roads &amp; Bridges',
    'Escapes ampersands'
);

// Test 6: Handle null.
$assert(
    $escaper->escape(null) === '',
    'Converts null into an empty string'
);

// Test 7: Handle integers.
$assert(
    $escaper->escape(42) === '42',
    'Handles integer values'
);

// Test 8: Handle floating-point numbers.
$assert(
    $escaper->escape(12.5) === '12.5',
    'Handles floating-point values'
);

// Test 9: Handle booleans.
$assert(
    $escaper->escape(true) === '1'
        && $escaper->escape(false) === '',
    'Handles boolean values'
);

// Test 10: Preserve Unicode characters.
$assert(
    $escaper->escape('বাংলা ভাষা')
        === 'বাংলা ভাষা',
    'Preserves Unicode text'
);

// Test 11: Escape HTML while preserving Unicode.
$assert(
    $escaper->escape('<p>বাংলা & English</p>')
        === '&lt;p&gt;বাংলা &amp; English&lt;/p&gt;',
    'Escapes HTML without corrupting Unicode text'
);

// Test 12: Replace invalid UTF-8 sequences.
$assert(
    $escaper->escape("\xB1\x31") === "\u{FFFD}1",
    'Replaces invalid UTF-8 sequences'
);

// Test 13: Encode existing entities again.
$assert(
    $escaper->escape('&lt;script&gt;')
        === '&amp;lt;script&amp;gt;',
    'Encodes existing HTML entities to prevent double interpretation'
);

echo "\nPassed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
