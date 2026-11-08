<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use CivicPulse\Framework\View\Compiler\Compiler;
use CivicPulse\Framework\View\Compiler\CompiledViewCache;
use CivicPulse\Framework\View\Exceptions\ViewException;
use CivicPulse\Framework\View\HtmlEscaper;
use CivicPulse\Framework\View\HtmlSanitizer;
use CivicPulse\Framework\View\View;
use CivicPulse\Framework\View\ViewFinder;
use CivicPulse\Framework\View\ViewRenderer;

echo "Testing @build integration...\n";

$basePath = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'civicpulse_view_build_test_'
    . bin2hex(random_bytes(8));

$viewsPath = $basePath . DIRECTORY_SEPARATOR . 'views';
$cachePath = $basePath . DIRECTORY_SEPARATOR . 'compiled';

mkdir($viewsPath, 0777, true);

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
    string $exceptionClass,
    string $description
) use (&$passed, &$failed): void {
    try {
        $callback();

        $failed++;
        echo "[FAIL] {$description} - no exception thrown\n";
    } catch (Throwable $exception) {
        if ($exception instanceof $exceptionClass) {
            $passed++;
            echo "[PASS] {$description}\n";
        } else {
            $failed++;
            echo "[FAIL] {$description} - unexpected exception: "
                . $exception::class . "\n";
        }
    }
};

try {
    // Create templates.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'build.php',
        '@build($html)'
    );

    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'escaped.php',
        '{{ $html }}'
    );

    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'nested.php',
        '@build($data[\'html\'])'
    );

    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'fallback.php',
        '@build($html ?? \'\')'
    );

    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'combined.php',
        '<section>@build($html)</section>'
            . '<div>{{ $html }}</div>'
    );

    // Build the View dependencies.
    $finder = new ViewFinder($viewsPath);

    $compiler = new Compiler();

    $cache = new CompiledViewCache(
        $cachePath,
        $compiler
    );

    $escaper = new HtmlEscaper();

    $sanitizer = new HtmlSanitizer();

    $renderer = new ViewRenderer(
        $cache,
        $escaper
    );

    $view = new View(
        $finder,
        $renderer,
        null,
        null,
        $escaper,
        $sanitizer
    );

    // 1. Allowed HTML is preserved.
    $output = $view->render(
        'build',
        ['html' => '<p>Hello <strong>world</strong></p>']
    );

    $assert(
        $output === '<p>Hello <strong>world</strong></p>',
        'Renders allowed HTML through @build'
    );

    // 2. Dangerous HTML is removed.
    $output = $view->render(
        'build',
        [
            'html' => '<p>Safe</p>'
                . '<script>alert(1)</script>'
                . '<img src=x onerror=alert(2)>',
        ]
    );

    $assert(
        $output === '<p>Safe</p>',
        'Removes dangerous elements through @build'
    );

    // 3. Event handlers and unsafe attributes are removed.
    $output = $view->render(
        'build',
        [
            'html' => '<p onclick="alert(1)" style="color:red">'
                . 'Safe'
                . '</p>',
        ]
    );

    $assert(
        $output === '<p>Safe</p>',
        'Removes unsafe attributes through @build'
    );

    // 4. Normal interpolation remains escaped.
    $output = $view->render(
        'escaped',
        ['html' => '<strong>Important</strong>']
    );

    $assert(
        $output === '&lt;strong&gt;Important&lt;/strong&gt;',
        'Keeps normal interpolation escaped'
    );

    // 5. Nested expressions work.
    $output = $view->render(
        'nested',
        [
            'data' => [
                'html' => '<p>Nested content</p>',
            ],
        ]
    );

    $assert(
        $output === '<p>Nested content</p>',
        'Supports array expressions in @build'
    );

    // 6. Null coalescing expressions work.
    $output = $view->render(
        'fallback',
        []
    );

    $assert(
        $output === '',
        'Supports null coalescing expressions in @build'
    );

    // 7. @build and escaped interpolation remain independent.
    $output = $view->render(
        'combined',
        ['html' => '<strong>Important</strong>']
    );

    $assert(
        $output === '<section><strong>Important</strong></section>'
            . '<div>&lt;strong&gt;Important&lt;/strong&gt;</div>',
        'Keeps sanitized output separate from escaped interpolation'
    );

    // 8. A View constructed with the original two arguments works.
    $legacyView = new View(
        $finder,
        $renderer
    );

    $output = $legacyView->render(
        'build',
        ['html' => '<p>Default sanitizer</p><script>alert(1)</script>']
    );

    $assert(
        $output === '<p>Default sanitizer</p>',
        'Uses the default sanitizer with the original constructor arguments'
    );

    // 9. Reject empty @build expressions.
    $assertThrows(
        fn() => $compiler->compile('@build()'),
        ViewException::class,
        'Rejects an empty @build expression'
    );

    // 10. Reject multiple top-level arguments.
    $assertThrows(
        fn() => $compiler->compile('@build($first, $second)'),
        ViewException::class,
        'Rejects multiple @build arguments'
    );

    // 11. Reject an unclosed directive.
    $assertThrows(
        fn() => $compiler->compile('@build($html'),
        ViewException::class,
        'Rejects an unclosed @build directive'
    );
} catch (Throwable $exception) {
    $failed++;

    echo "[FAIL] Unexpected test exception: "
        . $exception::class
        . ': '
        . $exception->getMessage()
        . "\n";

    echo $exception->getTraceAsString() . "\n";
} finally {
    if (is_dir($basePath)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $basePath,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();

            if ($item->isDir() && !$item->isLink()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($basePath);
    }
}

echo PHP_EOL;
echo "Passed: {$passed}" . PHP_EOL;
echo "Failed: {$failed}" . PHP_EOL;

exit($failed === 0 ? 0 : 1);
