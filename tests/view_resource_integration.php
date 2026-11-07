<?php

declare(strict_types=1);

use CivicPulse\Framework\View\AssetResolver;
use CivicPulse\Framework\View\Compiler\Compiler;
use CivicPulse\Framework\View\Compiler\CompiledViewCache;
use CivicPulse\Framework\View\HtmlEscaper;
use CivicPulse\Framework\View\View;
use CivicPulse\Framework\View\ViewFinder;
use CivicPulse\Framework\View\ViewRenderer;

require_once __DIR__ . '/autoload.php';

echo "Testing View Resource Integration...\n";

$basePath = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'civicpulse_view_resource_integration_'
    . bin2hex(random_bytes(8));

$viewsPath = $basePath . DIRECTORY_SEPARATOR . 'views';
$cachePath = $basePath . DIRECTORY_SEPARATOR . 'compiled';
$publicPath = $basePath . DIRECTORY_SEPARATOR . 'public';

$directories = [
    $viewsPath . DIRECTORY_SEPARATOR . 'layouts',
    $viewsPath . DIRECTORY_SEPARATOR . 'pages',
    $viewsPath . DIRECTORY_SEPARATOR . 'partials',
    $viewsPath . DIRECTORY_SEPARATOR . 'components',

    $publicPath . DIRECTORY_SEPARATOR . 'assets'
        . DIRECTORY_SEPARATOR . 'css'
        . DIRECTORY_SEPARATOR . 'pages',

    $publicPath . DIRECTORY_SEPARATOR . 'assets'
        . DIRECTORY_SEPARATOR . 'css'
        . DIRECTORY_SEPARATOR . 'components',

    $publicPath . DIRECTORY_SEPARATOR . 'assets'
        . DIRECTORY_SEPARATOR . 'js'
        . DIRECTORY_SEPARATOR . 'pages',
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
    /*
     * Create the layout.
     *
     * Styles are emitted in the head.
     * Scripts are emitted before the closing body tag.
     *
     * The content is deliberately rendered as trusted framework
     * output, rather than escaped interpolation.
     */
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'layouts'
            . DIRECTORY_SEPARATOR . 'app.php',
        <<<'VIEW'
        <!DOCTYPE html>
        <html>
        <head>
            <title>{{ $title }}</title>
            @resources('styles')
        </head>
        <body>
            <header>CivicPulse</header>

            <main><?= $content ?></main>

            @resources('scripts')
        </body>
        </html>
        VIEW
    );

    /*
     * Create the partial.
     *
     * This should render immediately at the @call location.
     */
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'partials'
            . DIRECTORY_SEPARATOR . 'report-meta.php',
        '<span class="report-meta">Report metadata</span>'
    );

    /*
     * Create the component.
     *
     * It registers a stylesheet and renders its default slot.
     */
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'report-card.php',
        <<<'VIEW'
        @call([
            'styles' => [
                'components/report-card',
            ],
        ])

        <article class="report-card">
            <h2>{{ $title }}</h2>
            @renderSlot()
        </article>
        VIEW
    );

    /*
     * Create the page.
     *
     * It registers its own stylesheet and script.
     * It also registers the component stylesheet deliberately.
     *
     * The component registers that same stylesheet again.
     * The resource context should deduplicate it.
     */
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'home.php',
        <<<'VIEW'
        @call([
            'partials' => [
                'partials/report-meta',
            ],
            'styles' => [
                'pages/home',
                'components/report-card',
            ],
            'scripts' => [
                'pages/home',
            ],
        ])

        <p class="page-content">Welcome to CivicPulse.</p>

        @components('report-card', [
            'title' => $reportTitle,
        ])
            <p>Report card content.</p>
        @endcomponents
        VIEW
    );

    /*
     * Create the physical asset files.
     */
    file_put_contents(
        $publicPath . DIRECTORY_SEPARATOR . 'assets'
            . DIRECTORY_SEPARATOR . 'css'
            . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'home.css',
        '.page-content { display: block; }'
    );

    file_put_contents(
        $publicPath . DIRECTORY_SEPARATOR . 'assets'
            . DIRECTORY_SEPARATOR . 'css'
            . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'report-card.css',
        '.report-card { display: block; }'
    );

    file_put_contents(
        $publicPath . DIRECTORY_SEPARATOR . 'assets'
            . DIRECTORY_SEPARATOR . 'js'
            . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'home.js',
        'console.log("CivicPulse home");'
    );

    /*
     * Build the complete View dependency chain.
     */
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

    /*
     * Render the page inside the layout.
     */
    $output = $view->renderWithLayout(
        'pages/home',
        'layouts/app',
        [
            'reportTitle' => 'Community Road Damage',
        ],
        [
            'title' => 'CivicPulse Reports',
        ]
    );

    /*
     * Test 1: The layout renders.
     */
    $assert(
        str_contains($output, '<!DOCTYPE html>')
            && str_contains($output, '</html>'),
        'Renders the complete HTML layout'
    );

    /*
     * Test 2: The partial renders at its registration location.
     */
    $partialPosition = strpos(
        $output,
        '<span class="report-meta">Report metadata</span>'
    );

    $pagePosition = strpos(
        $output,
        'Welcome to CivicPulse.'
    );

    $assert(
        $partialPosition !== false
            && $pagePosition !== false
            && $partialPosition < $pagePosition,
        'Renders the partial immediately at the @call location'
    );

    /*
     * Test 3: The page content renders.
     */
    $assert(
        str_contains($output, 'Welcome to CivicPulse.'),
        'Renders page content through the layout'
    );

    /*
     * Test 4: The component renders.
     */
    $assert(
        str_contains($output, 'Community Road Damage')
            && str_contains($output, 'Report card content.'),
        'Renders component props and default slot content'
    );

    /*
     * Test 5: The page stylesheet appears.
     */
    $assert(
        str_contains(
            $output,
            '<link rel="stylesheet" href="/assets/css/pages/home.css">'
        ),
        'Outputs the page stylesheet in the layout'
    );

    /*
     * Test 6: The component stylesheet appears.
     */
    $assert(
        str_contains(
            $output,
            '<link rel="stylesheet" href="/assets/css/components/report-card.css">'
        ),
        'Outputs the component stylesheet in the layout'
    );

    /*
     * Test 7: The script appears.
     */
    $assert(
        str_contains(
            $output,
            '<script src="/assets/js/pages/home.js" defer></script>'
        ),
        'Outputs the page script in the layout'
    );

    /*
     * Test 8: The component stylesheet is not duplicated.
     */
    $assert(
        substr_count(
            $output,
            '/assets/css/components/report-card.css'
        ) === 1,
        'Deduplicates a stylesheet registered by both page and component'
    );

    /*
     * Test 9: The page stylesheet precedes the component stylesheet.
     */
    $pageStylePosition = strpos(
        $output,
        '/assets/css/pages/home.css'
    );

    $componentStylePosition = strpos(
        $output,
        '/assets/css/components/report-card.css'
    );

    $assert(
        $pageStylePosition !== false
            && $componentStylePosition !== false
            && $pageStylePosition < $componentStylePosition,
        'Preserves stylesheet registration order'
    );

    /*
     * Test 10: Styles are emitted inside the head.
     */
    $headPosition = strpos($output, '</head>');

    $assert(
        $pageStylePosition !== false
            && $headPosition !== false
            && $pageStylePosition < $headPosition,
        'Emits stylesheets inside the document head'
    );

    /*
     * Test 11: Scripts are emitted before the closing body tag.
     */
    $scriptPosition = strpos(
        $output,
        '/assets/js/pages/home.js'
    );

    $bodyPosition = strpos($output, '</body>');

    $assert(
        $scriptPosition !== false
            && $bodyPosition !== false
            && $scriptPosition < $bodyPosition,
        'Emits scripts before the closing body tag'
    );

    /*
     * Test 12: The layout title is escaped correctly.
     */
    $assert(
        str_contains($output, '<title>CivicPulse Reports</title>'),
        'Renders layout data through escaped interpolation'
    );
} finally {
    $removeDirectory($basePath);
}

echo PHP_EOL . "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}

echo "All View Resource Integration tests passed.\n";
