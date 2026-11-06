<?php

declare(strict_types=1);

use CivicPulse\Framework\View\Compiler\Compiler;
use CivicPulse\Framework\View\Exceptions\ViewException;

require_once __DIR__ . '/autoload.php';

echo "Testing View Compiler...\n";

$compiler = new Compiler();

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

$assertThrows = static function (
    callable $callback,
    string $description
) use (&$passed, &$failed): void {
    try {
        $callback();

        $failed++;
        echo "[FAIL] {$description} (no exception thrown)\n";
    } catch (ViewException) {
        $passed++;
        echo "[PASS] {$description}\n";
    } catch (Throwable $exception) {
        $failed++;
        echo "[FAIL] {$description} (unexpected exception: "
            . $exception::class
            . ")\n";
    }
};

// Test 1: Compile basic interpolation.
$assert(
    $compiler->compile('<h1>{{ $title }}</h1>')
        === '<h1><?= $__escaper->escape($title) ?></h1>',
    'Compiles basic escaped interpolation'
);

// Test 2: Compile multiple interpolations.
$assert(
    $compiler->compile(
        '<h1>{{ $title }}</h1><p>{{ $description }}</p>'
    ) === '<h1><?= $__escaper->escape($title) ?></h1>'
        . '<p><?= $__escaper->escape($description) ?></p>',
    'Compiles multiple interpolations'
);

// Test 3: Preserve ordinary HTML.
$assert(
    $compiler->compile(
        '<main><h1>CivicPulse</h1><p>Reports</p></main>'
    ) === '<main><h1>CivicPulse</h1><p>Reports</p></main>',
    'Preserves ordinary HTML'
);

// Test 4: Preserve an ordinary PHP block.
$assert(
    $compiler->compile(
        '<?php if ($show): ?><p>Visible</p><?php endif; ?>'
    ) === '<?php if ($show): ?><p>Visible</p><?php endif; ?>',
    'Preserves ordinary PHP template code'
);

// Test 5: Compile array access.
$assert(
    $compiler->compile(
        "{{ \$report['description'] }}"
    ) === "<?= \$__escaper->escape(\$report['description']) ?>",
    'Compiles array access expressions'
);

// Test 6: Compile nested array access.
$assert(
    $compiler->compile(
        "{{ \$data['nested']['value'] }}"
    ) === "<?= \$__escaper->escape(\$data['nested']['value']) ?>",
    'Compiles nested array access'
);

// Test 7: Compile nested function calls.
$assert(
    $compiler->compile(
        '{{ strtoupper(trim($name)) }}'
    ) === '<?= $__escaper->escape(strtoupper(trim($name))) ?>',
    'Compiles nested function calls'
);

// Test 8: Preserve closing braces inside a quoted string.
$assert(
    $compiler->compile(
        "{{ str_replace('}', 'x', \$value) }}"
    ) === "<?= \$__escaper->escape(str_replace('}', 'x', \$value)) ?>",
    'Ignores closing braces inside single-quoted strings'
);

// Test 9: Preserve interpolation delimiters inside a quoted string.
$assert(
    $compiler->compile(
        "{{ 'left }} right' }}"
    ) === "<?= \$__escaper->escape('left }} right') ?>",
    'Ignores interpolation delimiters inside quoted strings'
);

// Test 10: Preserve braces inside double-quoted strings.
$assert(
    $compiler->compile(
        '{{ "CivicPulse {reports}" }}'
    ) === '<?= $__escaper->escape("CivicPulse {reports}") ?>',
    'Ignores braces inside double-quoted strings'
);

// Test 11: Reject empty interpolation.
$assertThrows(
    static fn() => $compiler->compile('{{ }}'),
    'Rejects empty interpolation'
);

// Test 12: Reject unclosed interpolation.
$assertThrows(
    static fn() => $compiler->compile('<p>{{ $title </p>'),
    'Rejects unclosed interpolation'
);

// Test 13: Reject unexpected closing parentheses.
$assertThrows(
    static fn() => $compiler->compile('{{ $value) }}'),
    'Rejects unexpected closing parentheses'
);

// Test 14: Reject unexpected closing brackets.
$assertThrows(
    static fn() => $compiler->compile('{{ $value] }}'),
    'Rejects unexpected closing brackets'
);

// Test 15: Reject unexpected closing braces.
$assertThrows(
    static fn() => $compiler->compile('{{ $value } }'),
    'Rejects unexpected closing braces'
);

echo "\nPassed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
