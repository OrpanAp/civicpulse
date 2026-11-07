<?php

declare(strict_types=1);

use CivicPulse\Framework\View\AssetResolver;
use CivicPulse\Framework\View\ComponentContext;
use CivicPulse\Framework\View\Compiler\Compiler;
use CivicPulse\Framework\View\Compiler\CompiledViewCache;
use CivicPulse\Framework\View\Exceptions\ViewException;
use CivicPulse\Framework\View\HtmlEscaper;
use CivicPulse\Framework\View\View;
use CivicPulse\Framework\View\ViewFinder;
use CivicPulse\Framework\View\ViewRenderer;
use CivicPulse\Framework\View\ViewResourceContext;

require_once __DIR__ . '/autoload.php';

echo "Testing View Resources...\n";

$basePath = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'civicpulse_view_resources_'
    . bin2hex(random_bytes(8));

$viewsPath = $basePath . DIRECTORY_SEPARATOR . 'views';
$cachePath = $basePath . DIRECTORY_SEPARATOR . 'compiled';
$publicPath = $basePath . DIRECTORY_SEPARATOR . 'public';

$directories = [
    $viewsPath . DIRECTORY_SEPARATOR . 'partials',
    $publicPath . DIRECTORY_SEPARATOR . 'assets'
        . DIRECTORY_SEPARATOR . 'css'
        . DIRECTORY_SEPARATOR . 'components',
    $publicPath . DIRECTORY_SEPARATOR . 'assets'
        . DIRECTORY_SEPARATOR . 'js'
        . DIRECTORY_SEPARATOR . 'components',
];

foreach ($directories as $directory) {
    mkdir($directory, 0777, true);
}

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
    // Create test templates.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'partials'
            . DIRECTORY_SEPARATOR . 'report-meta.php',
        '<span>Report metadata</span>'
    );

    // Create test assets.
    file_put_contents(
        $publicPath . DIRECTORY_SEPARATOR . 'assets'
            . DIRECTORY_SEPARATOR . 'css'
            . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'report-card.css',
        '.report-card { display: block; }'
    );

    file_put_contents(
        $publicPath . DIRECTORY_SEPARATOR . 'assets'
            . DIRECTORY_SEPARATOR . 'css'
            . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'alternate.css',
        '.alternate { display: block; }'
    );

    file_put_contents(
        $publicPath . DIRECTORY_SEPARATOR . 'assets'
            . DIRECTORY_SEPARATOR . 'js'
            . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'report-card.js',
        'console.log("report-card");'
    );

    // Build the View dependencies.
    $finder = new ViewFinder($viewsPath);

    $compiler = new Compiler();

    $cache = new CompiledViewCache(
        $cachePath,
        $compiler
    );

    $escaper = new HtmlEscaper();

    $renderer = new ViewRenderer(
        $cache,
        $escaper
    );

    $resolver = new AssetResolver($publicPath);

    $view = new View(
        $finder,
        $renderer,
        null,
        $resolver,
        $escaper
    );

    $componentContext = new ComponentContext();
    $resourceContext = new ViewResourceContext();

    // 1. Render a partial immediately.
    $output = $view->call(
        [
            'partials' => [
                'partials/report-meta',
            ],
        ],
        $componentContext,
        $resourceContext
    );

    $assert(
        $output === '<span>Report metadata</span>',
        'Renders partials immediately'
    );

    // 2. Register a stylesheet.
    $view->call(
        [
            'styles' => [
                'components/report-card',
            ],
        ],
        $componentContext,
        $resourceContext
    );

    $assert(
        $resourceContext->getResources('styles') === [
            [
                'key' => 'components.report-card',
                'path' => '/assets/css/components/report-card.css',
            ],
        ],
        'Registers a stylesheet using its default logical key'
    );

    // 3. Register JavaScript independently.
    $view->call(
        [
            'scripts' => [
                'components/report-card',
            ],
        ],
        $componentContext,
        $resourceContext
    );

    $assert(
        $resourceContext->getResources('scripts') === [
            [
                'key' => 'components.report-card',
                'path' => '/assets/js/components/report-card.js',
            ],
        ],
        'Registers JavaScript independently of stylesheet resources'
    );

    // 4. Render stylesheet tags.
    $output = $view->resources(
        'styles',
        $resourceContext
    );

    $assert(
        $output === '<link rel="stylesheet" href="/assets/css/components/report-card.css">',
        'Generates stylesheet link tags'
    );

    // 5. Render script tags.
    $output = $view->resources(
        'scripts',
        $resourceContext
    );

    $assert(
        $output === '<script src="/assets/js/components/report-card.js" defer></script>',
        'Generates deferred JavaScript tags'
    );

    // 6. Duplicate registration keeps the first resource.
    $view->call(
        [
            'styles' => [
                [
                    'key' => 'components.report-card',
                    'path' => 'components/alternate',
                ],
            ],
        ],
        $componentContext,
        $resourceContext
    );

    $assert(
        $resourceContext->getResources('styles')[0]['path']
            === '/assets/css/components/report-card.css',
        'Keeps the first resource registration by default'
    );

    // 7. Explicit override replaces the URL.
    $view->call(
        [
            'styles' => [
                [
                    'key' => 'components.report-card',
                    'path' => 'components/alternate',
                    'override' => true,
                ],
            ],
        ],
        $componentContext,
        $resourceContext
    );

    $assert(
        $resourceContext->getResources('styles')[0]['path']
            === '/assets/css/components/alternate.css',
        'Supports explicit resource overrides'
    );

    // 8. Override preserves registration position.
    $view->call(
        [
            'styles' => [
                'components/alternate',
            ],
        ],
        $componentContext,
        $resourceContext
    );

    $keys = array_column(
        $resourceContext->getResources('styles'),
        'key'
    );

    $assert(
        $keys === [
            'components.report-card',
            'components.alternate',
        ],
        'Preserves resource order after an override'
    );

    // 9. Escapes asset URLs before generating HTML.
    $escapedContext = new ViewResourceContext();

    $escapedContext->register(
        'styles',
        'escaped-test',
        '/assets/css/test.css?value="quoted"'
    );

    $output = $view->resources(
        'styles',
        $escapedContext
    );

    $assert(
        str_contains($output, '&quot;quoted&quot;'),
        'Escapes resource URLs in generated HTML'
    );

    // 10. Reject unsupported categories.
    $assertThrows(
        fn() => $view->call(
            ['unknown' => []],
            $componentContext,
            $resourceContext
        ),
        'Rejects unsupported @call categories'
    );

    // 11. Reject associative resource lists.
    $assertThrows(
        fn() => $view->call(
            ['styles' => ['first' => 'components/report-card']],
            $componentContext,
            $resourceContext
        ),
        'Rejects associative resource lists'
    );

    // 12. Reject invalid partial entries.
    $assertThrows(
        fn() => $view->call(
            ['partials' => [123]],
            $componentContext,
            $resourceContext
        ),
        'Rejects non-string partial names'
    );

    // 13. Reject malformed descriptors.
    $assertThrows(
        fn() => $view->call(
            [
                'styles' => [
                    ['key' => 'missing-path'],
                ],
            ],
            $componentContext,
            $resourceContext
        ),
        'Rejects asset descriptors without paths'
    );

    // 14. Reject invalid override types.
    $assertThrows(
        fn() => $view->call(
            [
                'styles' => [
                    [
                        'key' => 'invalid-override',
                        'path' => 'components/report-card',
                        'override' => 'yes',
                    ],
                ],
            ],
            $componentContext,
            $resourceContext
        ),
        'Rejects non-boolean override values'
    );

    // 15. Reject missing assets.
    $assertThrows(
        fn() => $view->call(
            ['styles' => ['components/missing']],
            $componentContext,
            $resourceContext
        ),
        'Rejects missing asset files'
    );

    // 16. Reject invalid output types.
    $assertThrows(
        fn() => $view->resources(
            'images',
            $resourceContext
        ),
        'Rejects unsupported resource output types'
    );

    // 17. Independent contexts remain isolated.
    $independentContext = new ViewResourceContext();

    $assert(
        $independentContext->getResources('styles') === []
            && $independentContext->getResources('scripts') === [],
        'Keeps independent resource contexts isolated'
    );

    // 18. A View without an asset resolver can still render partials.
    $viewWithoutResolver = new View(
        $finder,
        $renderer
    );

    $partialOutput = $viewWithoutResolver->call(
        [
            'partials' => ['partials/report-meta'],
        ],
        new ComponentContext(),
        new ViewResourceContext()
    );

    $assert(
        $partialOutput === '<span>Report metadata</span>',
        'Allows partial rendering without an asset resolver'
    );

    // 19. The original three-argument renderIncluded() API remains supported.
    $includedOutput = $viewWithoutResolver->renderIncluded(
        'partials/report-meta',
        [],
        new ComponentContext()
    );

    $assert(
        $includedOutput === '<span>Report metadata</span>',
        'Supports renderIncluded() without an explicit resource context'
    );

    // 20. Asset registration requires an asset resolver.
    $assertThrows(
        fn() => $viewWithoutResolver->call(
            ['styles' => ['components/report-card']],
            new ComponentContext(),
            new ViewResourceContext()
        ),
        'Rejects asset registration without an asset resolver'
    );
} finally {
    $removeDirectory($basePath);
}

echo PHP_EOL . "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}

echo "All View Resource tests passed.\n";
