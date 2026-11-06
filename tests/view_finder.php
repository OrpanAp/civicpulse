<?php

declare(strict_types=1);

use CivicPulse\Framework\View\Exceptions\ViewException;
use CivicPulse\Framework\View\Exceptions\ViewNotFoundException;
use CivicPulse\Framework\View\ViewFinder;

require_once __DIR__ . '/autoload.php';

echo "Testing ViewFinder...\n";

$basePath = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'civicpulse_view_finder_test';

$viewsPath = $basePath . DIRECTORY_SEPARATOR . 'views';
$outsidePath = $basePath . DIRECTORY_SEPARATOR . 'outside';

mkdir($viewsPath, 0777, true);
mkdir($outsidePath, 0777, true);

mkdir($viewsPath . DIRECTORY_SEPARATOR . 'reports', 0777, true);

file_put_contents(
    $viewsPath . DIRECTORY_SEPARATOR . 'home.php',
    '<h1>Home</h1>'
);

file_put_contents(
    $viewsPath . DIRECTORY_SEPARATOR . 'reports'
        . DIRECTORY_SEPARATOR . 'index.php',
    '<h1>Reports</h1>'
);

file_put_contents(
    $outsidePath . DIRECTORY_SEPARATOR . 'secret.php',
    'Secret'
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
    $finder = new ViewFinder($viewsPath);

    // Test 1: Find a root-level view.
    $homePath = $finder->find('home');

    $assert(
        $homePath === realpath(
            $viewsPath . DIRECTORY_SEPARATOR . 'home.php'
        ),
        'Finds an existing root-level view'
    );

    // Test 2: Find a nested view.
    $reportPath = $finder->find('reports/index');

    $assert(
        $reportPath === realpath(
            $viewsPath . DIRECTORY_SEPARATOR
                . 'reports' . DIRECTORY_SEPARATOR . 'index.php'
        ),
        'Finds an existing nested view'
    );

    // Test 3: Missing view.
    $assertThrows(
        fn() => $finder->find('missing'),
        ViewNotFoundException::class,
        'Rejects a missing view'
    );

    // Test 4: Empty name.
    $assertThrows(
        fn() => $finder->find(''),
        ViewNotFoundException::class,
        'Rejects an empty view name'
    );

    // Test 5: Directory traversal.
    $assertThrows(
        fn() => $finder->find('../outside/secret'),
        ViewNotFoundException::class,
        'Rejects directory traversal'
    );

    // Test 6: Absolute path.
    $assertThrows(
        fn() => $finder->find('/etc/passwd'),
        ViewNotFoundException::class,
        'Rejects absolute paths'
    );

    // Test 7: Windows-style drive path.
    $assertThrows(
        fn() => $finder->find('C:/Windows/test'),
        ViewNotFoundException::class,
        'Rejects Windows drive paths'
    );

    // Test 8: Explicit PHP extension.
    $assertThrows(
        fn() => $finder->find('home.php'),
        ViewNotFoundException::class,
        'Rejects names containing a file extension'
    );

    // Test 9: Invalid characters.
    $assertThrows(
        fn() => $finder->find('reports/index test'),
        ViewNotFoundException::class,
        'Rejects spaces in view names'
    );

    // Test 10: Missing views directory.
    $assertThrows(
        fn() => new ViewFinder(
            $basePath . DIRECTORY_SEPARATOR . 'missing'
        ),
        ViewException::class,
        'Rejects a missing views directory'
    );

    // Test 11: Symbolic link escape.
    $linkPath = $viewsPath
        . DIRECTORY_SEPARATOR
        . 'external';

    if (@symlink(
        $outsidePath . DIRECTORY_SEPARATOR . 'secret.php',
        $linkPath . '.php'
    )) {
        $assertThrows(
            fn() => $finder->find('external'),
            ViewNotFoundException::class,
            'Rejects a symbolic link pointing outside views'
        );
    } else {
        echo "[SKIP] Symbolic link test is not supported "
            . "by this environment\n";
    }
} finally {
    // Remove temporary test files and directories.
    $files = [
        $viewsPath . DIRECTORY_SEPARATOR . 'home.php',
        $viewsPath . DIRECTORY_SEPARATOR . 'reports'
            . DIRECTORY_SEPARATOR . 'index.php',
        $viewsPath . DIRECTORY_SEPARATOR . 'external.php',
        $outsidePath . DIRECTORY_SEPARATOR . 'secret.php',
    ];

    foreach ($files as $file) {
        if (is_file($file) || is_link($file)) {
            unlink($file);
        }
    }

    $directories = [
        $viewsPath . DIRECTORY_SEPARATOR . 'reports',
        $viewsPath,
        $outsidePath,
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
