<?php

declare(strict_types=1);

use CivicPulse\Framework\View\Exceptions\ViewException;
use CivicPulse\Framework\View\Exceptions\ViewNotFoundException;
use CivicPulse\Framework\View\View;
use CivicPulse\Framework\View\ViewFinder;
use CivicPulse\Framework\View\ViewRenderer;
use CivicPulse\Framework\View\Compiler\Compiler;
use CivicPulse\Framework\View\Compiler\CompiledViewCache;
use CivicPulse\Framework\View\HtmlEscaper;

require_once __DIR__ . '/autoload.php';

echo "Testing View integration...\n";

$basePath = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'civicpulse_view_test_'
    . bin2hex(random_bytes(8));

$viewsPath = $basePath . DIRECTORY_SEPARATOR . 'views';
$cachePath = $basePath . DIRECTORY_SEPARATOR . 'compiled';

mkdir(
    $viewsPath . DIRECTORY_SEPARATOR . 'reports',
    0777,
    true
);

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
) use (&$passed, &$failed): ?Throwable {
    try {
        $callback();

        $failed++;
        echo "[FAIL] {$description} - no exception thrown\n";

        return null;
    } catch (Throwable $exception) {
        if ($exception instanceof $exceptionClass) {
            $passed++;
            echo "[PASS] {$description}\n";
        } else {
            $failed++;
            echo "[FAIL] {$description} - unexpected exception: "
                . $exception::class . "\n";
        }

        return $exception;
    }
};

try {
    // Create temporary templates.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'home.php',
        '<h1>Welcome to CivicPulse</h1>'
    );

    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'welcome.php',
        'Welcome, <?= $name ?>!'
    );

    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'escaped.php',
        '<h1>{{ $title }}</h1>'
    );

    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'reports'
            . DIRECTORY_SEPARATOR . 'index.php',
        '<h1>Reports</h1>'
    );

    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'broken.php',
        '<?php throw new RuntimeException("Template execution failed");'
    );

    // Build the View dependencies.
    $finder = new ViewFinder($viewsPath);

    $compiler = new Compiler();

    $compiledViewCache = new CompiledViewCache(
        $cachePath,
        $compiler
    );

    $escaper = new HtmlEscaper();

    $renderer = new ViewRenderer(
        $compiledViewCache,
        $escaper
    );

    $view = new View(
        $finder,
        $renderer
    );

    // Test 1: Render a root-level template.
    $output = $view->render('home');

    $assert(
        $output === '<h1>Welcome to CivicPulse</h1>',
        'Renders a root-level template through View'
    );

    // Test 2: Render a nested template.
    $output = $view->render('reports/index');

    $assert(
        $output === '<h1>Reports</h1>',
        'Renders a nested template through View'
    );

    // Test 3: Pass data into a template.
    $output = $view->render(
        'welcome',
        ['name' => 'CivicPulse']
    );

    $assert(
        $output === 'Welcome, CivicPulse!',
        'Passes data from View to ViewRenderer'
    );

    // Test 4: Automatically escape dynamic HTML.
    $output = $view->render(
        'escaped',
        ['title' => '<script>alert("XSS")</script>']
    );

    $assert(
        $output === '<h1>&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;</h1>',
        'Escapes dynamic HTML through the complete View rendering pipeline'
    );

    // Test 5: Reject a missing template.
    $assertThrows(
        fn() => $view->render('missing'),
        ViewNotFoundException::class,
        'Rejects a missing template through View'
    );

    // Test 6: Reject directory traversal.
    $assertThrows(
        fn() => $view->render('../outside/secret'),
        ViewNotFoundException::class,
        'Rejects directory traversal through View'
    );

    // Test 7: Preserve template execution exceptions.
    $exception = $assertThrows(
        fn() => $view->render('broken'),
        ViewException::class,
        'Wraps template execution errors through View'
    );

    $assert(
        $exception?->getPrevious() instanceof RuntimeException,
        'Preserves the original template exception'
    );
} finally {
    // Remove compiled cache files.
    if (is_dir($cachePath)) {
        $cacheFiles = glob($cachePath . DIRECTORY_SEPARATOR . '*');
        $temporaryFiles = glob($cachePath . DIRECTORY_SEPARATOR . '.*');

        foreach ([$cacheFiles, $temporaryFiles] as $files) {
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
        }

        rmdir($cachePath);
    }

    // Remove temporary templates.
    $files = [
        $viewsPath . DIRECTORY_SEPARATOR . 'home.php',
        $viewsPath . DIRECTORY_SEPARATOR . 'welcome.php',
        $viewsPath . DIRECTORY_SEPARATOR . 'escaped.php',
        $viewsPath . DIRECTORY_SEPARATOR . 'reports'
            . DIRECTORY_SEPARATOR . 'index.php',
        $viewsPath . DIRECTORY_SEPARATOR . 'broken.php',
    ];

    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    // Remove temporary directories.
    $directories = [
        $viewsPath . DIRECTORY_SEPARATOR . 'reports',
        $viewsPath,
        $basePath,
    ];

    foreach ($directories as $directory) {
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}

echo "\nPassed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
