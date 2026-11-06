<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use CivicPulse\Framework\Container\Container;
use CivicPulse\Framework\View\Compiler\Compiler;
use CivicPulse\Framework\View\Compiler\CompiledViewCache;
use CivicPulse\Framework\View\HtmlEscaper;
use CivicPulse\Framework\View\View;
use CivicPulse\Framework\View\ViewFinder;
use CivicPulse\Framework\View\ViewRenderer;

echo "Testing View service container integration..." . PHP_EOL;

function viewContainerCheck(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException("FAILED: {$message}");
    }

    echo "[PASS] {$message}" . PHP_EOL;
}

// Load the real application container.
$container = require dirname(__DIR__) . '/bootstrap/app.php';

viewContainerCheck(
    $container instanceof Container,
    'Application bootstrap returns a Container'
);

// Resolve the View service.
$view = $container->make(View::class);

viewContainerCheck(
    $view instanceof View,
    'View resolves from the container'
);

viewContainerCheck(
    $container->make(View::class) === $view,
    'View is registered as a singleton'
);

// Resolve the ViewFinder service.
$finder = $container->make(ViewFinder::class);

viewContainerCheck(
    $finder instanceof ViewFinder,
    'ViewFinder resolves from the container'
);

viewContainerCheck(
    $container->make(ViewFinder::class) === $finder,
    'ViewFinder is registered as a singleton'
);

// Resolve the ViewRenderer service.
$renderer = $container->make(ViewRenderer::class);

viewContainerCheck(
    $renderer instanceof ViewRenderer,
    'ViewRenderer resolves from the container'
);

viewContainerCheck(
    $container->make(ViewRenderer::class) === $renderer,
    'ViewRenderer is registered as a singleton'
);

// Resolve the compiled view cache.
$cache = $container->make(CompiledViewCache::class);

viewContainerCheck(
    $cache instanceof CompiledViewCache,
    'CompiledViewCache resolves from the container'
);

viewContainerCheck(
    $container->make(CompiledViewCache::class) === $cache,
    'CompiledViewCache is registered as a singleton'
);

// Resolve the compiler.
$compiler = $container->make(Compiler::class);

viewContainerCheck(
    $compiler instanceof Compiler,
    'Compiler resolves from the container'
);

viewContainerCheck(
    $container->make(Compiler::class) === $compiler,
    'Compiler is registered as a singleton'
);

// Resolve the HTML escaper.
$escaper = $container->make(HtmlEscaper::class);

viewContainerCheck(
    $escaper instanceof HtmlEscaper,
    'HtmlEscaper resolves from the container'
);

viewContainerCheck(
    $container->make(HtmlEscaper::class) === $escaper,
    'HtmlEscaper is registered as a singleton'
);

echo PHP_EOL;
echo "All View container integration tests passed." . PHP_EOL;
