<?php

declare(strict_types=1);

use CivicPulse\Framework\View\AssetResolver;
use CivicPulse\Framework\View\Exceptions\ViewException;

require_once __DIR__ . '/autoload.php';

echo "Testing Asset Resolver...\n";

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

$temporaryDirectory = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'civicpulse_asset_resolver_'
    . bin2hex(random_bytes(8));

$publicPath = $temporaryDirectory
    . DIRECTORY_SEPARATOR . 'public';

$cssPath = $publicPath
    . DIRECTORY_SEPARATOR . 'assets'
    . DIRECTORY_SEPARATOR . 'css';

$jsPath = $publicPath
    . DIRECTORY_SEPARATOR . 'assets'
    . DIRECTORY_SEPARATOR . 'js';

$mkdir = static function (string $path): void {
    if (
        !is_dir($path)
        && !mkdir($path, 0777, true)
        && !is_dir($path)
    ) {
        throw new RuntimeException(
            'Failed to create test directory: ' . $path
        );
    }
};

$write = static function (
    string $path,
    string $content
): void {
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException(
            'Failed to create test asset: ' . $path
        );
    }
};

$removeDirectory = static function (string $directory): void {
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
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($directory);
};

try {
    $mkdir($cssPath . DIRECTORY_SEPARATOR . 'components');
    $mkdir($jsPath . DIRECTORY_SEPARATOR . 'components');

    $write(
        $cssPath . DIRECTORY_SEPARATOR
            . 'components' . DIRECTORY_SEPARATOR
            . 'report-card.css',
        '.report-card { display: block; }'
    );

    $write(
        $jsPath . DIRECTORY_SEPARATOR
            . 'components' . DIRECTORY_SEPARATOR
            . 'report-card.js',
        'console.log("report-card");'
    );

    $resolver = new AssetResolver($publicPath);

    // 1. Resolve CSS.
    $assert(
        $resolver->resolve(
            'styles',
            'components/report-card'
        ) === '/assets/css/components/report-card.css',
        'Resolves a stylesheet URL'
    );

    // 2. Resolve JavaScript.
    $assert(
        $resolver->resolve(
            'scripts',
            'components/report-card'
        ) === '/assets/js/components/report-card.js',
        'Resolves a JavaScript URL'
    );

    // 3. Resolve nested asset paths.
    $assert(
        $resolver->resolve(
            'styles',
            'components/report-card'
        ) === '/assets/css/components/report-card.css',
        'Supports nested asset directories'
    );

    // 4. Configured root-relative base URL.
    $rootResolver = new AssetResolver(
        $publicPath,
        '/civicpulse'
    );

    $assert(
        $rootResolver->resolve(
            'styles',
            'components/report-card'
        ) === '/civicpulse/assets/css/components/report-card.css',
        'Supports a subdirectory deployment URL'
    );

    // 5. Configured external asset domain.
    $cdnResolver = new AssetResolver(
        $publicPath,
        'https://cdn.example.com'
    );

    $assert(
        $cdnResolver->resolve(
            'scripts',
            'components/report-card'
        ) === 'https://cdn.example.com/assets/js/components/report-card.js',
        'Supports an external asset domain'
    );

    // 6. Reject invalid resource type.
    $assertThrows(
        fn() => $resolver->resolve(
            'images',
            'logo'
        ),
        'Rejects unsupported asset types'
    );

    // 7. Reject traversal.
    $assertThrows(
        fn() => $resolver->resolve(
            'styles',
            '../secret'
        ),
        'Rejects directory traversal'
    );

    // 8. Reject invalid characters.
    $assertThrows(
        fn() => $resolver->resolve(
            'styles',
            'components/report card'
        ),
        'Rejects invalid asset path characters'
    );

    // 9. Reject empty path.
    $assertThrows(
        fn() => $resolver->resolve(
            'styles',
            ''
        ),
        'Rejects empty asset paths'
    );

    // 10. Reject a missing file.
    $assertThrows(
        fn() => $resolver->resolve(
            'styles',
            'components/missing'
        ),
        'Rejects missing asset files'
    );

    // 11. Reject an invalid public directory.
    $assertThrows(
        fn() => new AssetResolver(
            $temporaryDirectory . DIRECTORY_SEPARATOR . 'missing'
        ),
        'Rejects a missing public directory'
    );

    // 12. Reject an unsafe base URL.
    $assertThrows(
        fn() => new AssetResolver(
            $publicPath,
            'javascript:alert(1)'
        ),
        'Rejects an unsafe asset base URL'
    );

    // 13. Reject traversal in the base URL.
    $assertThrows(
        fn() => new AssetResolver(
            $publicPath,
            '/assets/../private'
        ),
        'Rejects traversal in the asset base URL'
    );

    // 14. Reject protocol-relative base URLs.
    $assertThrows(
        fn() => new AssetResolver(
            $publicPath,
            '//cdn.example.com'
        ),
        'Rejects protocol-relative asset base URLs'
    );

    // 15. Reject an asset directory symlink escaping public.
    $symlinkPublicPath = $temporaryDirectory
        . DIRECTORY_SEPARATOR . 'symlink-public';

    $symlinkAssetsPath = $symlinkPublicPath
        . DIRECTORY_SEPARATOR . 'assets';

    $outsideCssPath = $temporaryDirectory
        . DIRECTORY_SEPARATOR . 'outside-css';

    $mkdir($symlinkAssetsPath);
    $mkdir($outsideCssPath);

    $write(
        $outsideCssPath . DIRECTORY_SEPARATOR . 'report-card.css',
        '.report-card { color: red; }'
    );

    $cssDirectoryLink = $symlinkAssetsPath
        . DIRECTORY_SEPARATOR . 'css';

    if (
        function_exists('symlink')
        && @symlink($outsideCssPath, $cssDirectoryLink)
    ) {
        $symlinkResolver = new AssetResolver(
            $symlinkPublicPath
        );

        $assertThrows(
            fn() => $symlinkResolver->resolve(
                'styles',
                'report-card'
            ),
            'Rejects an asset directory symlink escaping public'
        );
    } else {
        echo "[SKIP] Asset directory symlink test is unsupported in this environment.\n";
    }

    // 16. Symlink escape test, where supported.
    $outsidePath = $temporaryDirectory
        . DIRECTORY_SEPARATOR . 'outside.css';

    $write($outsidePath, 'body { color: red; }');

    $linkPath = $cssPath
        . DIRECTORY_SEPARATOR . 'components'
        . DIRECTORY_SEPARATOR . 'outside.css';

    if (function_exists('symlink') && @symlink($outsidePath, $linkPath)) {
        $assertThrows(
            fn() => $resolver->resolve(
                'styles',
                'components/outside'
            ),
            'Rejects symlinks escaping the CSS directory'
        );
    } else {
        echo "[SKIP] Symlink escape test is unsupported in this environment.\n";
    }
} finally {
    $removeDirectory($temporaryDirectory);
}

echo "\nPassed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}

echo "All asset resolver tests passed.\n";
