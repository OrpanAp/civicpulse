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

echo "Testing View Composition...\n";

$basePath = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'civicpulse_view_composition_'
    . bin2hex(random_bytes(8));

$viewsPath = $basePath . DIRECTORY_SEPARATOR . 'views';
$cachePath = $basePath . DIRECTORY_SEPARATOR . 'compiled';

$directories = [
    $viewsPath . DIRECTORY_SEPARATOR . 'layouts',
    $viewsPath . DIRECTORY_SEPARATOR . 'pages',
    $viewsPath . DIRECTORY_SEPARATOR . 'partials',
    $viewsPath . DIRECTORY_SEPARATOR . 'components',
    $basePath . DIRECTORY_SEPARATOR . 'outside',
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
    // Create a page template.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'home.php',
        '<h1>{{ $heading }}</h1>'
    );

    // Create a layout template.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'layouts'
            . DIRECTORY_SEPARATOR . 'app.php',
        '<html><head><title>{{ $title }}</title></head>'
            . '<body><header>{{ $siteName }}</header>'
            . '<main><?= $content ?></main></body></html>'
    );

    // Create a reusable header partial.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'partials'
            . DIRECTORY_SEPARATOR . 'header.php',
        '<header>{{ $siteName }}</header>'
    );

    // Create a nested partial.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'partials'
            . DIRECTORY_SEPARATOR . 'navigation.php',
        '<nav>{{ $navigationLabel }}</nav>'
    );

    // Create a header partial that includes a nested navigation partial.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'partials'
            . DIRECTORY_SEPARATOR . 'composed-header.php',
        <<<'VIEW'
        <header>{{ $siteName }}@include('partials/navigation', [
            'navigationLabel' => $navigationLabel,
        ])</header>
        VIEW
    );

    // Create a page that includes a partial with explicit data.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'with-partial.php',
        <<<'VIEW'
        <main>{{ $heading }}@include('partials/header', [
            'siteName' => $partialSiteName,
        ])</main>
        VIEW
    );

    // Create a partial that attempts to read a parent variable.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'partials'
            . DIRECTORY_SEPARATOR . 'isolated.php',
        '<?php if (isset($secret)): ?>LEAKED<?php else: ?>ISOLATED<?php endif; ?>'
    );

    // Create a parent page that must not leak variables into a partial.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'isolation.php',
        <<<'VIEW'
        <?php $secret = "TOP SECRET"; ?>
        @include('partials/isolated')
        VIEW
    );

    // Create a page that includes a nonexistent partial.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'missing-partial.php',
        <<<'VIEW'
        @include('partials/does-not-exist')
        VIEW
    );

    // Create a layout for @extends integration tests.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'layouts'
            . DIRECTORY_SEPARATOR . 'composed.php',
        <<<'VIEW'
        <!DOCTYPE html>
        <html>
        <head><title>{{ $layoutTitle }}</title></head>
        <body>
            <header>@yield('header')</header>
            <main>@yield('content')</main>
            <footer>@yield('footer')</footer>
        </body>
        </html>
        VIEW
    );

    // Create a child page using @extends and multiple sections.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'extends.php',
        <<<'VIEW'
        @extends('layouts/composed', [
            'layoutTitle' => $documentTitle,
        ])
        @section('header')
            <h1>{{ $heading }}</h1>
        @endsection
        @section('content')
            <p>{{ $description }}</p>
            @include('partials/navigation', [
                'navigationLabel' => $navigationLabel,
            ])
        @endsection
        @section('footer')
            <small>{{ $footerText }}</small>
        @endsection
        VIEW
    );

    // Create a page that attempts to expose its data to the layout.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'layout-isolation.php',
        <<<'VIEW'
        @extends('layouts/isolation', [
            'layoutTitle' => 'Isolated Layout',
        ])
        @section('content')
            {{ $pageSecret }}
        @endsection
        VIEW
    );

    // Create a layout that checks its own variable scope.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'layouts'
            . DIRECTORY_SEPARATOR . 'isolation.php',
        <<<'VIEW'
        <title>{{ $layoutTitle }}</title>
        <?php if (isset($pageSecret)): ?>LEAKED<?php else: ?>ISOLATED<?php endif; ?>
        @yield('content')
        VIEW
    );

    // Create a page with duplicate sections.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'duplicate-sections.php',
        <<<'VIEW'
        @extends('layouts/composed')
        @section('content')
            First
        @endsection
        @section('content')
            Second
        @endsection
        VIEW
    );

    // Create a page with an unclosed section.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'unclosed-section.php',
        <<<'VIEW'
        @extends('layouts/composed')
        @section('content')
            Unclosed content
        VIEW
    );

    // Create a page with a section but no layout.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'section-without-layout.php',
        <<<'VIEW'
        @section('content')
            Content
        @endsection
        VIEW
    );

    // Create a page with @yield but no layout.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'yield-without-layout.php',
        "@yield('content')"
    );

    // Create a page that attempts to declare multiple layouts.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'multiple-layouts.php',
        <<<'VIEW'
        @extends('layouts/composed')
        @extends('layouts/isolation')
        VIEW
    );

    // Layout that displays registered stylesheet resources.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'layouts'
            . DIRECTORY_SEPARATOR . 'resource-check.php',
        <<<'VIEW'
        <?php foreach ($__resourceContext->getResources('styles') as $resource): ?>
            <?= $resource['key'] ?>:<?= $resource['path'] ?>;
        <?php endforeach; ?>
        VIEW
    );

    // Partial that registers a stylesheet.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'partials'
            . DIRECTORY_SEPARATOR . 'resource-registration.php',
        <<<'VIEW'
        <?php
        $__resourceContext->register(
            'styles',
            'partial-style',
            'partials/header'
        );
        ?>
        PARTIAL
        VIEW
    );

    // Component that registers a stylesheet and renders a nested component.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'resource-outer.php',
        <<<'VIEW'
        <?php
        $__resourceContext->register(
            'styles',
            'outer-component-style',
            'components/outer'
        );
        ?>
        @components('resource-inner')
        @endcomponents
        VIEW
    );

    // Nested component that registers its own stylesheet.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'resource-inner.php',
        <<<'VIEW'
        <?php
        $__resourceContext->register(
            'styles',
            'inner-component-style',
            'components/inner'
        );
        ?>
        INNER COMPONENT
        VIEW
    );

    // Page registering a resource and using a partial and nested components.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'resource-sharing.php',
        <<<'VIEW'
        <?php
        $__resourceContext->register(
            'styles',
            'page-style',
            'pages/home'
        );
        ?>

        PAGE

        @include('partials/resource-registration')

        @components('resource-outer')
        @endcomponents
        VIEW
    );

    // Template used to verify independent renders receive fresh contexts.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'resource-isolation.php',
        <<<'VIEW'
        <?php
        $__resourceContext->register(
            'styles',
            'same-key',
            $resourcePath
        );

        echo $__resourceContext->getResources('styles')[0]['path'];
        ?>
        VIEW
    );

    // Build the View system.
    $finder = new ViewFinder($viewsPath);

    $compiler = new Compiler();

    $compiledViewCache = new CompiledViewCache(
        $cachePath,
        $compiler
    );

    $renderer = new ViewRenderer(
        $compiledViewCache,
        new HtmlEscaper()
    );

    $view = new View(
        $finder,
        $renderer
    );

    // Test 1: Render a page inside a shared layout.
    $output = $view->renderWithLayout(
        'pages/home',
        'layouts/app',
        [
            'heading' => 'Welcome to CivicPulse',
        ],
        [
            'title' => 'Home',
            'siteName' => 'CivicPulse',
        ]
    );

    $assert(
        $output === '<html><head><title>Home</title></head>'
            . '<body><header>CivicPulse</header>'
            . '<main><h1>Welcome to CivicPulse</h1></main></body></html>',
        'Renders a page inside a shared layout'
    );

    // Test 2: Reject missing page templates.
    $assertThrows(
        fn() => $view->renderWithLayout(
            'pages/missing',
            'layouts/app'
        ),
        ViewNotFoundException::class,
        'Rejects a missing page template'
    );

    // Test 3: Reject missing layout templates.
    $assertThrows(
        fn() => $view->renderWithLayout(
            'pages/home',
            'layouts/missing',
            ['heading' => 'Home']
        ),
        ViewNotFoundException::class,
        'Rejects a missing layout template'
    );

    // Test 4: Reject directory traversal in page names.
    $assertThrows(
        fn() => $view->renderWithLayout(
            '../outside/secret',
            'layouts/app'
        ),
        ViewNotFoundException::class,
        'Rejects directory traversal in page names'
    );

    // Test 5: Reject directory traversal in layout names.
    $assertThrows(
        fn() => $view->renderWithLayout(
            'pages/home',
            '../outside/secret'
        ),
        ViewNotFoundException::class,
        'Rejects directory traversal in layout names'
    );

    // Test 6: Render a partial inside a page.
    $output = $view->render(
        'pages/with-partial',
        [
            'heading' => 'Dashboard',
            'partialSiteName' => 'CivicPulse',
        ]
    );

    $assert(
        $output === '<main>Dashboard<header>CivicPulse</header></main>',
        'Renders a partial inside a page'
    );

    // Test 7: Render nested partials.
    $output = $view->render(
        'partials/composed-header',
        [
            'siteName' => 'CivicPulse',
            'navigationLabel' => 'Home',
        ]
    );

    $assert(
        $output === '<header>CivicPulse<nav>Home</nav></header>',
        'Renders nested partials'
    );

    // Test 8: Prevent parent variables from leaking into partials.
    $output = $view->render('pages/isolation');

    $assert(
        $output === 'ISOLATED',
        'Keeps partial rendering scopes isolated'
    );

    // Test 9: Protect the internal view helper from user data.
    $output = $view->render(
        'pages/with-partial',
        [
            'heading' => 'Dashboard',
            'partialSiteName' => 'CivicPulse',
            'view' => 'malicious replacement',
        ]
    );

    $assert(
        $output === '<main>Dashboard<header>CivicPulse</header></main>',
        'Prevents user data from replacing the view helper'
    );

    // Test 10: Prevent layout data from replacing rendered page content.
    $output = $view->renderWithLayout(
        'pages/home',
        'layouts/app',
        ['heading' => 'Real page content'],
        [
            'title' => 'Home',
            'siteName' => 'CivicPulse',
            'content' => 'Fake content',
        ]
    );

    $assert(
        str_contains($output, '<h1>Real page content</h1>')
            && !str_contains($output, 'Fake content'),
        'Prevents layout data from replacing page content'
    );

    // Test 11: Reject a missing partial.
    $assertThrows(
        fn() => $view->render('pages/missing-partial'),
        ViewException::class,
        'Rejects a missing partial'
    );


    // Test 12: Render a child page through @extends.
    $output = $view->render(
        'pages/extends',
        [
            'documentTitle' => 'CivicPulse Dashboard',
            'heading' => 'Community Dashboard',
            'description' => 'Track infrastructure reports.',
            'navigationLabel' => 'Home',
            'footerText' => 'CivicPulse 2026',
        ]
    );

    $assert(
        str_contains($output, '<title>CivicPulse Dashboard</title>')
            && str_contains($output, '<h1>Community Dashboard</h1>')
            && str_contains($output, '<p>Track infrastructure reports.</p>')
            && str_contains($output, '<nav>Home</nav>')
            && str_contains($output, '<small>CivicPulse 2026</small>'),
        'Renders a child page through @extends with multiple sections'
    );

    // Test 13: Missing sections produce empty output.
    $output = $view->render(
        'pages/extends',
        [
            'documentTitle' => 'Dashboard',
            'heading' => 'Home',
            'description' => 'Description',
            'navigationLabel' => 'Home',
            'footerText' => 'Footer',
        ]
    );

    $assert(
        !str_contains($output, 'missing-section'),
        'Renders without requiring every possible section'
    );

    // Test 14: Keep child page variables isolated from layout data.
    $output = $view->render(
        'pages/layout-isolation',
        [
            'pageSecret' => 'PRIVATE PAGE DATA',
        ]
    );

    $assert(
        str_contains($output, 'ISOLATED')
            && !str_contains($output, 'LEAKED'),
        'Keeps child page variables isolated from layout scope'
    );

    // Test 15: Reject duplicate sections.
    $assertThrows(
        fn() => $view->render('pages/duplicate-sections'),
        ViewException::class,
        'Rejects duplicate sections'
    );

    // Test 16: Reject unclosed sections.
    $assertThrows(
        fn() => $view->render('pages/unclosed-section'),
        ViewException::class,
        'Rejects unclosed sections'
    );

    // Test 17: Reject sections without a layout.
    $assertThrows(
        fn() => $view->render('pages/section-without-layout'),
        ViewException::class,
        'Rejects sections without @extends'
    );

    // Test 18: Reject @yield outside a layout.
    $assertThrows(
        fn() => $view->render('pages/yield-without-layout'),
        ViewException::class,
        'Rejects @yield outside a layout'
    );

    // Test 19: Reject multiple layout declarations.
    $assertThrows(
        fn() => $view->render('pages/multiple-layouts'),
        ViewException::class,
        'Rejects multiple layout declarations'
    );

    // Test 20: Ensure composition state does not leak between renders.
    $firstOutput = $view->render(
        'pages/extends',
        [
            'documentTitle' => 'First Render',
            'heading' => 'First Heading',
            'description' => 'First Description',
            'navigationLabel' => 'First Navigation',
            'footerText' => 'First Footer',
        ]
    );

    $secondOutput = $view->render(
        'pages/extends',
        [
            'documentTitle' => 'Second Render',
            'heading' => 'Second Heading',
            'description' => 'Second Description',
            'navigationLabel' => 'Second Navigation',
            'footerText' => 'Second Footer',
        ]
    );

    $assert(
        str_contains($firstOutput, 'First Heading')
            && !str_contains($firstOutput, 'Second Heading')
            && str_contains($secondOutput, 'Second Heading')
            && !str_contains($secondOutput, 'First Heading'),
        'Keeps composition state isolated between renders'
    );

    // Test 21: Page, partials, and nested components share resources with layout.
    $output = $view->renderWithLayout(
        'pages/resource-sharing',
        'layouts/resource-check'
    );

    $assert(
        str_contains($output, 'page-style:pages/home;')
            && str_contains($output, 'partial-style:partials/header;')
            && str_contains($output, 'outer-component-style:components/outer;')
            && str_contains($output, 'inner-component-style:components/inner;'),
        'Shares resource context across pages, partials, nested components, and layouts'
    );

    // Test 22: Independent renders receive fresh resource contexts.
    $firstResourceOutput = $view->render(
        'pages/resource-isolation',
        ['resourcePath' => 'first/path']
    );

    $secondResourceOutput = $view->render(
        'pages/resource-isolation',
        ['resourcePath' => 'second/path']
    );

    $assert(
        trim($firstResourceOutput) === 'first/path'
            && trim($secondResourceOutput) === 'second/path',
        'Keeps resource context isolated between independent renders'
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

    // Remove temporary view files.
    if (is_dir($viewsPath)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $viewsPath,
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

        rmdir($viewsPath);
    }

    // Remove the outside test directory.
    $outsideSecret = $basePath
        . DIRECTORY_SEPARATOR . 'outside'
        . DIRECTORY_SEPARATOR . 'secret.php';

    if (is_file($outsideSecret)) {
        unlink($outsideSecret);
    }

    $outsideDirectory = $basePath
        . DIRECTORY_SEPARATOR . 'outside';

    if (is_dir($outsideDirectory)) {
        rmdir($outsideDirectory);
    }

    if (is_dir($basePath)) {
        rmdir($basePath);
    }
}

echo "\nPassed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
