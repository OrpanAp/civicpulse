<?php

declare(strict_types=1);

use CivicPulse\Framework\View\Exceptions\ViewException;
use CivicPulse\Framework\View\ViewRenderer;
use CivicPulse\Framework\View\Compiler\Compiler;
use CivicPulse\Framework\View\Compiler\CompiledViewCache;
use CivicPulse\Framework\View\HtmlEscaper;

require_once __DIR__ . '/autoload.php';

echo "Testing ViewRenderer...\n";

$basePath = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'civicpulse_view_renderer_'
    . bin2hex(random_bytes(8));

mkdir($basePath, 0777, true);

$cachePath = $basePath . DIRECTORY_SEPARATOR . 'compiled';

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
    // Build renderer dependencies.
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

    // Test 1: Render a simple PHP template.
    $simplePath = $basePath . DIRECTORY_SEPARATOR . 'simple.php';

    file_put_contents(
        $simplePath,
        '<h1>Welcome to CivicPulse</h1>'
    );

    $output = $renderer->render($simplePath);

    $assert(
        $output === '<h1>Welcome to CivicPulse</h1>',
        'Renders a simple PHP template'
    );

    // Test 2: Pass data into a template.
    $dataPath = $basePath . DIRECTORY_SEPARATOR . 'data.php';

    file_put_contents(
        $dataPath,
        'Hello, <?= $name ?>!'
    );

    $output = $renderer->render(
        $dataPath,
        ['name' => 'CivicPulse']
    );

    $assert(
        $output === 'Hello, CivicPulse!',
        'Makes supplied data available inside the template'
    );

    // Test 3: Automatically escape template interpolations.
    $escapedPath = $basePath
        . DIRECTORY_SEPARATOR
        . 'escaped.php';

    file_put_contents(
        $escapedPath,
        '<h1>{{ $name }}</h1>'
    );

    $output = $renderer->render(
        $escapedPath,
        ['name' => '<script>alert("XSS")</script>']
    );

    $assert(
        $output === '<h1>&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;</h1>',
        'Automatically escapes interpolated template data'
    );

    // Test 4: Prevent supplied data from overwriting internal variables.
    $internalPath = $basePath
        . DIRECTORY_SEPARATOR
        . 'internal.php';

    file_put_contents(
        $internalPath,
        '<?= $__viewPath ?>'
    );

    $output = $renderer->render(
        $internalPath,
        ['__viewPath' => 'unexpected-value']
    );

    $assert(
        $output === $internalPath,
        'Prevents supplied data from overwriting internal variables'
    );

    // Test 5: Preserve output from nested output buffers.
    $nestedPath = $basePath
        . DIRECTORY_SEPARATOR
        . 'nested.php';

    file_put_contents(
        $nestedPath,
        '<?php ob_start(); echo "Nested output"; ?>'
    );

    $output = $renderer->render($nestedPath);

    $assert(
        $output === 'Nested output',
        'Preserves output from nested template buffers'
    );

    // Test 6: Reject a missing template file.
    $missingPath = $basePath
        . DIRECTORY_SEPARATOR
        . 'missing.php';

    $assertThrows(
        fn() => $renderer->render($missingPath),
        ViewException::class,
        'Rejects a missing template file'
    );

    // Test 7: Wrap template execution errors.
    $errorPath = $basePath
        . DIRECTORY_SEPARATOR
        . 'error.php';

    file_put_contents(
        $errorPath,
        '<?php throw new RuntimeException("Template failure");'
    );

    $exception = $assertThrows(
        fn() => $renderer->render($errorPath),
        ViewException::class,
        'Wraps template execution errors in ViewException'
    );

    $assert(
        $exception?->getPrevious() instanceof RuntimeException,
        'Preserves the original template exception as the previous exception'
    );

    // Test 8: Preserve the caller's output buffer after successful rendering.
    ob_start();

    try {
        echo 'Before';

        $renderer->render($simplePath);

        echo 'After';

        $outerOutput = ob_get_clean();
    } finally {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    $assert(
        $outerOutput === 'BeforeAfter',
        'Preserves the caller output buffer after successful rendering'
    );

    // Test 9: Preserve the caller's output buffer after a template error.
    $bufferErrorPath = $basePath
        . DIRECTORY_SEPARATOR
        . 'buffer_error.php';

    file_put_contents(
        $bufferErrorPath,
        '<?php ob_end_clean(); throw new RuntimeException("Buffer failure");'
    );

    $caughtException = false;
    $outerOutput = false;

    ob_start();

    try {
        echo 'Before';

        try {
            $renderer->render($bufferErrorPath);
        } catch (ViewException) {
            $caughtException = true;
        }

        echo 'After';

        $outerOutput = ob_get_clean();
    } finally {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    $assert(
        $caughtException,
        'Handles a template that closes the renderer output buffer'
    );

    $assert(
        $outerOutput === 'BeforeAfter',
        'Preserves the caller output buffer after a template error'
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

    // Remove temporary template files.
    if (is_dir($basePath)) {
        $files = glob($basePath . DIRECTORY_SEPARATOR . '*');

        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file) || is_link($file)) {
                    unlink($file);
                }
            }
        }

        rmdir($basePath);
    }
}

echo "\nPassed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
