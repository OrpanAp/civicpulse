<?php

declare(strict_types=1);

use CivicPulse\Framework\View\Compiler\CompiledViewCache;
use CivicPulse\Framework\View\Compiler\Compiler;
use CivicPulse\Framework\View\Exceptions\ViewException;

require_once __DIR__ . '/autoload.php';

echo "Testing CompiledViewCache...\n";

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

// Create an isolated temporary test directory.
$testRoot = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'civicpulse-cache-test-'
    . bin2hex(random_bytes(8));

if (!mkdir($testRoot, 0775, true)) {
    throw new RuntimeException(
        'Unable to create temporary test directory.'
    );
}

// Recursively remove test files and directories.
$cleanup = static function (string $directory): void {
    if (!is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($directory);
};

try {
    $cacheDirectory = $testRoot
        . DIRECTORY_SEPARATOR
        . 'cache'
        . DIRECTORY_SEPARATOR
        . 'compiled';

    $sourceDirectory = $testRoot
        . DIRECTORY_SEPARATOR
        . 'views';

    mkdir($sourceDirectory, 0775, true);

    $sourcePath = $sourceDirectory
        . DIRECTORY_SEPARATOR
        . 'home.php';

    file_put_contents(
        $sourcePath,
        '<h1>{{ $title }}</h1>'
    );

    $compiler = new Compiler();

    // Test 1: Create the cache directory automatically.
    $cache = new CompiledViewCache(
        $cacheDirectory,
        $compiler
    );

    $assert(
        is_dir($cacheDirectory),
        'Creates the cache directory when it does not exist'
    );

    // Test 2: Compile a source template.
    $compiledPath = $cache->compile($sourcePath);

    $assert(
        is_file($compiledPath),
        'Creates a compiled PHP file'
    );

    // Test 3: Verify generated PHP content.
    $compiledContent = file_get_contents($compiledPath);

    $assert(
        $compiledContent
            === '<h1><?= $__escaper->escape($title) ?></h1>',
        'Stores correctly compiled template content'
    );

    // Test 4: Verify the generated filename format.
    $assert(
        preg_match(
            '/\A[a-f0-9]{64}\.php\z/',
            basename($compiledPath)
        ) === 1,
        'Uses a SHA-256-based cache filename'
    );

    // Test 5: Reuse an unchanged compiled template.
    $secondCompiledPath = $cache->compile($sourcePath);

    $assert(
        $secondCompiledPath === $compiledPath,
        'Reuses the cached file when source is unchanged'
    );

    // Test 6: Change the source template.
    file_put_contents(
        $sourcePath,
        '<h1>{{ $title }}</h1><p>{{ $description }}</p>'
    );

    $updatedCompiledPath = $cache->compile($sourcePath);

    $assert(
        $updatedCompiledPath !== $compiledPath,
        'Generates a new cache file when source changes'
    );

    // Test 7: Verify updated compiled content.
    $updatedContent = file_get_contents($updatedCompiledPath);

    $assert(
        $updatedContent
            === '<h1><?= $__escaper->escape($title) ?></h1>'
            . '<p><?= $__escaper->escape($description) ?></p>',
        'Stores the updated compiled template content'
    );

    // Test 8: Reject a missing source file.
    $assertThrows(
        static fn() => $cache->compile(
            $sourceDirectory
                . DIRECTORY_SEPARATOR
                . 'missing.php'
        ),
        'Rejects missing source files'
    );

    // Test 9: Reject invalid template interpolation.
    $invalidSourcePath = $sourceDirectory
        . DIRECTORY_SEPARATOR
        . 'invalid.php';

    file_put_contents(
        $invalidSourcePath,
        '<h1>{{ }}</h1>'
    );

    $assertThrows(
        static fn() => $cache->compile($invalidSourcePath),
        'Propagates compilation errors as ViewException'
    );

    // Test 10: Ensure failed compilation creates no PHP cache file.
    $phpFiles = glob(
        $cacheDirectory . DIRECTORY_SEPARATOR . '*.php'
    );

    $assert(
        count($phpFiles) === 2,
        'Does not create a compiled file after compilation failure'
    );

    // Test 11: Ensure no temporary files remain.
    $temporaryFiles = glob(
        $cacheDirectory . DIRECTORY_SEPARATOR . '.*.tmp'
    );

    $assert(
        $temporaryFiles !== false
            && count($temporaryFiles) === 0,
        'Leaves no temporary files after successful and failed compilation'
    );
} finally {
    $cleanup($testRoot);
}

echo "\nPassed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
