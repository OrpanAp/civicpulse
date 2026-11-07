<?php

declare(strict_types=1);

use CivicPulse\Framework\View\Exceptions\ViewException;
use CivicPulse\Framework\View\ViewResourceContext;

require_once __DIR__ . '/autoload.php';

echo "Testing View Resource Context...\n";

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
        echo "[FAIL] {$description} - no exception thrown\n";
    } catch (ViewException $exception) {
        $passed++;
        echo "[PASS] {$description}\n";
    } catch (Throwable $exception) {
        $failed++;
        echo "[FAIL] {$description} - unexpected exception: "
            . $exception::class . "\n";
    }
};

$context = new ViewResourceContext();

// 1. Register a stylesheet.
$context->register(
    'styles',
    'report-card',
    'components/report-card'
);

$assert(
    count($context->getResources('styles')) === 1,
    'Registers a stylesheet'
);

// 2. Register a script.
$context->register(
    'scripts',
    'report-card',
    'components/report-card'
);

$assert(
    count($context->getResources('scripts')) === 1,
    'Registers a script independently of stylesheet keys'
);

// 3. Duplicate registration without override.
$context->register(
    'styles',
    'report-card',
    'components/another-card'
);

$styles = $context->getResources('styles');

$assert(
    $styles[0]['path'] === 'components/report-card',
    'Keeps the first registration by default'
);

// 4. Explicit override.
$context->register(
    'styles',
    'report-card',
    'components/report-card-v2',
    true
);

$styles = $context->getResources('styles');

$assert(
    $styles[0]['path'] === 'components/report-card-v2',
    'Replaces an existing resource when override is enabled'
);

// 5. Preserve original registration order.
$context->register(
    'styles',
    'dashboard',
    'pages/dashboard'
);

$context->register(
    'styles',
    'profile',
    'pages/profile'
);

$context->register(
    'styles',
    'dashboard',
    'pages/dashboard-v2',
    true
);

$styles = $context->getResources('styles');

$assert(
    array_column($styles, 'key') === [
        'report-card',
        'dashboard',
        'profile',
    ],
    'Preserves registration order after an override'
);

// 6. Independent style and script collections.
$assert(
    count($context->getResources('styles')) === 3
        && count($context->getResources('scripts')) === 1,
    'Keeps styles and scripts in separate collections'
);

// 7. Invalid resource type.
$assertThrows(
    fn() => $context->register(
        'images',
        'logo',
        'images/logo'
    ),
    'Rejects unsupported resource types'
);

// 8. Invalid logical key.
$assertThrows(
    fn() => $context->register(
        'styles',
        '../report',
        'components/report'
    ),
    'Rejects invalid logical keys'
);

// 9. Empty path.
$assertThrows(
    fn() => $context->register(
        'scripts',
        'empty-script',
        '   '
    ),
    'Rejects empty resource paths'
);

// 10. Independent contexts.
$anotherContext = new ViewResourceContext();

$assert(
    $anotherContext->getResources('styles') === []
        && $anotherContext->getResources('scripts') === [],
    'Starts with empty resource collections'
);

echo "\nPassed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}

echo "All resource context tests passed.\n";
