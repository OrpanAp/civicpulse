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

// Test 16: Compile an include without explicit data.
$assert(
    $compiler->compile(
        "@include('partials/header')"
    ) === "<?= \$view->renderIncluded('partials/header', [], \$__componentContext) ?>",
    'Compiles an include without explicit data'
);

// Test 17: Compile an include with explicit data.
$assert(
    $compiler->compile(
        "@include('partials/header', ['siteName' => \$siteName])"
    ) === "<?= \$view->renderIncluded('partials/header', ['siteName' => \$siteName], \$__componentContext) ?>",
    'Compiles an include with explicit data'
);

// Test 18: Compile nested expressions in include data.
$assert(
    $compiler->compile(
        "@include('partials/navigation', ['label' => strtoupper(trim(\$label))])"
    ) === "<?= \$view->renderIncluded('partials/navigation', ['label' => strtoupper(trim(\$label))], \$__componentContext) ?>",
    'Compiles nested expressions in include data'
);

// Test 19: Reject an unquoted view name.
$assertThrows(
    static fn() => $compiler->compile(
        '@include(partials/header)'
    ),
    'Rejects an unquoted include view name'
);

// Test 20: Reject a dynamic view name.
$assertThrows(
    static fn() => $compiler->compile(
        "@include(\$partialName)"
    ),
    'Rejects a dynamic include view name'
);

// Test 21: Reject more than two arguments.
$assertThrows(
    static fn() => $compiler->compile(
        "@include('partials/header', [], 'extra')"
    ),
    'Rejects extra include arguments'
);

// Test 22: Reject a non-array data argument.
$assertThrows(
    static fn() => $compiler->compile(
        "@include('partials/header', \$data)"
    ),
    'Rejects implicit or variable include data'
);

// Test 23: Reject an unclosed include directive.
$assertThrows(
    static fn() => $compiler->compile(
        "@include('partials/header'"
    ),
    'Rejects an unclosed include directive'
);

// Test 24: Reject an empty include argument.
$assertThrows(
    static fn() => $compiler->compile(
        "@include('partials/header', )"
    ),
    'Rejects an empty include argument'
);

// Test 25: Compile a layout declaration without data.
$assert(
    $compiler->compile(
        "@extends('layouts/app')"
    ) === "<?php \$__componentContext->assertCompositionAllowed(); ?><?php \$__viewContext->extend('layouts/app', []); ?>",
    'Compiles a layout declaration without data'
);

// Test 26: Compile a layout declaration with explicit data.
$assert(
    $compiler->compile(
        "@extends('layouts/app', ['title' => \$title])"
    ) === "<?php \$__componentContext->assertCompositionAllowed(); ?><?php \$__viewContext->extend('layouts/app', ['title' => \$title]); ?>",
    'Compiles a layout declaration with explicit data'
);

// Test 27: Compile a section declaration.
$assert(
    $compiler->compile(
        "@section('content')"
    ) === "<?php \$__componentContext->assertCompositionAllowed(); ?><?php \$__viewContext->startSection('content'); ?>",
    'Compiles a section declaration'
);

// Test 28: Compile an ending section directive.
$assert(
    $compiler->compile(
        '@endsection'
    ) === "<?php \$__viewContext->endSection(); ?>",
    'Compiles an ending section directive'
);

// Test 29: Compile a yield directive.
$assert(
    $compiler->compile(
        "@yield('content')"
    ) === "<?php \$__componentContext->assertCompositionAllowed(); ?><?= \$__viewContext->yieldSection('content') ?>",
    'Compiles a yield directive'
);

// Test 30: Reject an unquoted layout name.
$assertThrows(
    static fn() => $compiler->compile(
        '@extends(layouts/app)'
    ),
    'Rejects an unquoted layout name'
);

// Test 31: Reject a dynamic layout name.
$assertThrows(
    static fn() => $compiler->compile(
        '@extends($layout)'
    ),
    'Rejects a dynamic layout name'
);

// Test 32: Reject more than two layout arguments.
$assertThrows(
    static fn() => $compiler->compile(
        "@extends('layouts/app', [], 'extra')"
    ),
    'Rejects extra layout arguments'
);

// Test 33: Reject non-array layout data.
$assertThrows(
    static fn() => $compiler->compile(
        "@extends('layouts/app', \$data)"
    ),
    'Rejects variable layout data'
);

// Test 34: Reject an unclosed layout declaration.
$assertThrows(
    static fn() => $compiler->compile(
        "@extends('layouts/app'"
    ),
    'Rejects an unclosed layout declaration'
);

// Test 35: Reject an empty section name.
$assertThrows(
    static fn() => $compiler->compile(
        "@section('')"
    ),
    'Rejects an empty section name'
);

// Test 36: Reject an unclosed yield directive.
$assertThrows(
    static fn() => $compiler->compile(
        "@yield('content'"
    ),
    'Rejects an unclosed yield directive'
);

// Test 37: Compile a basic if directive.
$assert(
    $compiler->compile(
        '@if($condition)Yes@endif'
    ) === '<?php if ($condition): ?>'
        . 'Yes'
        . '<?php endif; ?>',
    'Compiles a basic if directive'
);

// Test 38: Compile an elseif directive.
$assert(
    $compiler->compile(
        '@if($condition)First@elseif($otherCondition)Second@endif'
    ) === '<?php if ($condition): ?>'
        . 'First'
        . '<?php elseif ($otherCondition): ?>'
        . 'Second'
        . '<?php endif; ?>',
    'Compiles an elseif directive'
);

// Test 39: Compile an else directive.
$assert(
    $compiler->compile(
        '@if($condition)First@elseSecond@endif'
    ) === '<?php if ($condition): ?>'
        . 'First'
        . '<?php else: ?>'
        . 'Second'
        . '<?php endif; ?>',
    'Compiles an else directive'
);

// Test 40: Compile an endif directive.
$assert(
    $compiler->compile(
        '@if($condition)Content@endif'
    ) === '<?php if ($condition): ?>'
        . 'Content'
        . '<?php endif; ?>',
    'Compiles an endif directive'
);

// Test 41: Compile nested conditional directives.
$assert(
    $compiler->compile(
        '@if($first)@if($second)Inside@endif@endif'
    ) === '<?php if ($first): ?>'
        . '<?php if ($second): ?>'
        . 'Inside'
        . '<?php endif; ?>'
        . '<?php endif; ?>',
    'Compiles nested conditional directives'
);

// Test 42: Support complex conditions with nested expressions.
$assert(
    $compiler->compile(
        "@if(is_string(\$user['name']) && strlen(\$user['name']) > 0)"
            . 'Valid'
            . '@endif'
    ) === "<?php if (is_string(\$user['name']) && strlen(\$user['name']) > 0): ?>"
        . 'Valid'
        . '<?php endif; ?>',
    'Compiles conditions containing nested expressions'
);

// Test 43: Support arrays and quoted strings in elseif conditions.
$assert(
    $compiler->compile(
        "@if(\$role === 'guest')Guest"
            . "@elseif(in_array(\$role, ['admin', 'editor'], true))"
            . 'Authorized'
            . '@endif'
    ) === "<?php if (\$role === 'guest'): ?>"
        . 'Guest'
        . "<?php elseif (in_array(\$role, ['admin', 'editor'], true)): ?>"
        . 'Authorized'
        . '<?php endif; ?>',
    'Compiles elseif conditions containing arrays and strings'
);

// Test 44: Reject an empty if condition.
$assertThrows(
    static fn() => $compiler->compile('@if()'),
    'Rejects an empty if condition'
);

// Test 45: Reject an unclosed if directive.
$assertThrows(
    static fn() => $compiler->compile('@if($condition'),
    'Rejects an unclosed if directive'
);

// Test 46: Reject an empty elseif condition.
$assertThrows(
    static fn() => $compiler->compile('@elseif()'),
    'Rejects an empty elseif condition'
);

// Test 47: Reject multiple top-level if arguments.
$assertThrows(
    static fn() => $compiler->compile('@if($first, $second)'),
    'Rejects multiple if arguments'
);

// Test 48: Reject else without an open if.
$assertThrows(
    static fn() => $compiler->compile('@else'),
    'Rejects else without an open if'
);

// Test 49: Reject elseif without an open if.
$assertThrows(
    static fn() => $compiler->compile('@elseif($condition)'),
    'Rejects elseif without an open if'
);

// Test 50: Reject endif without an open if.
$assertThrows(
    static fn() => $compiler->compile('@endif'),
    'Rejects endif without an open if'
);

// Test 51: Reject duplicate else directives.
$assertThrows(
    static fn() => $compiler->compile(
        '@if($condition)@else@else@endif'
    ),
    'Rejects duplicate else directives'
);

// Test 52: Reject elseif after else.
$assertThrows(
    static fn() => $compiler->compile(
        '@if($condition)@else@elseif($otherCondition)@endif'
    ),
    'Rejects elseif after else'
);

// Test 53: Reject an unclosed conditional block.
$assertThrows(
    static fn() => $compiler->compile(
        '@if($condition)Content'
    ),
    'Rejects an unclosed conditional block'
);

// Test 54: Reject an empty component declaration.
$assertThrows(
    static fn() => $compiler->compile('@components()'),
    'Rejects an empty component declaration'
);

// Test 55: Reject more than two component arguments.
$assertThrows(
    static fn() => $compiler->compile(
        "@components('card', [], [])"
    ),
    'Rejects extra component arguments'
);

// Test 56: Reject a dynamic component name.
$assertThrows(
    static fn() => $compiler->compile(
        '@components($componentName)'
    ),
    'Rejects a dynamic component name'
);

// Test 57: Reject non-array component props.
$assertThrows(
    static fn() => $compiler->compile(
        "@components('card', \$props)"
    ),
    'Rejects variable component props'
);

// Test 58: Reject an empty named slot declaration.
$assertThrows(
    static fn() => $compiler->compile('@slot()'),
    'Rejects an empty slot declaration'
);

// Test 59: Reject declaring the reserved default slot.
$assertThrows(
    static fn() => $compiler->compile(
        "@components('card')@slot('default')@endslot@endcomponents"
    ),
    'Rejects declaring the default slot explicitly'
);

// Test 60: Reject multiple render-slot arguments.
$assertThrows(
    static fn() => $compiler->compile(
        "@renderSlot('header', 'footer')"
    ),
    'Rejects multiple render-slot arguments'
);

// Test 61: Reject a dynamic render-slot name.
$assertThrows(
    static fn() => $compiler->compile(
        '@renderSlot($slotName)'
    ),
    'Rejects a dynamic render-slot name'
);

echo "\nPassed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
