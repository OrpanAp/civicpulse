<?php

declare(strict_types=1);

use CivicPulse\Framework\View\ComponentContext;
use CivicPulse\Framework\View\Exceptions\ViewException;
use CivicPulse\Framework\View\Exceptions\ViewNotFoundException;
use CivicPulse\Framework\View\View;
use CivicPulse\Framework\View\ViewFinder;
use CivicPulse\Framework\View\ViewRenderer;
use CivicPulse\Framework\View\Compiler\Compiler;
use CivicPulse\Framework\View\Compiler\CompiledViewCache;
use CivicPulse\Framework\View\HtmlEscaper;

require_once __DIR__ . '/autoload.php';

echo "Testing View Components...\n";

$basePath = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'civicpulse_view_components_'
    . bin2hex(random_bytes(8));

$viewsPath = $basePath . DIRECTORY_SEPARATOR . 'views';
$cachePath = $basePath . DIRECTORY_SEPARATOR . 'compiled';

$directories = [
    $viewsPath . DIRECTORY_SEPARATOR . 'components',
    $viewsPath . DIRECTORY_SEPARATOR . 'pages',
    $viewsPath . DIRECTORY_SEPARATOR . 'partials',
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

$removeDirectory = static function (string $directory) use (&$removeDirectory): void {
    if (!is_dir($directory)) {
        return;
    }

    $items = scandir($directory);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path) && !is_link($path)) {
            $removeDirectory($path);
            continue;
        }

        unlink($path);
    }

    rmdir($directory);
};

try {
    // Component with props and a default slot.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'card.php',
        <<<'VIEW'
        <article class="card">
            <h2>{{ $title }}</h2>
            <span>{{ $status }}</span>
            <div class="card-content">@renderSlot()</div>
            <footer>@renderSlot('footer')</footer>
        </article>
        VIEW
    );

    // Simple component for nested component tests.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'button.php',
        '<button type="button">{{ $label }}</button>'
    );

    // Component with only a default slot.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'panel.php',
        '<section class="panel">@renderSlot()</section>'
    );

    // Component used to test prop isolation.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'isolated.php',
        <<<'VIEW'
        <div>
            <?php if (isset($secret)): ?>
                LEAKED
            <?php else: ?>
                ISOLATED
            <?php endif; ?>
        </div>
        VIEW
    );

    // Component that checks whether a missing slot is empty.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'empty-slots.php',
        '<div>@renderSlot()|@renderSlot(\'footer\')</div>'
    );

    // Page using a component with props and a default slot.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'basic.php',
        <<<'VIEW'
        @components('card', [
            'title' => 'Community Report',
            'status' => 'Pending',
        ])
            <p>Report information.</p>
        @endcomponents
        VIEW
    );

    // Page using a named slot.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'named-slot.php',
        <<<'VIEW'
        @components('card', [
            'title' => 'Road Damage',
            'status' => 'Reviewed',
        ])
            <p>Report details.</p>

            @slot('footer')
                <small>Updated today</small>
            @endslot
        @endcomponents
        VIEW
    );

    // Page with a nested component in its default slot.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'nested.php',
        <<<'VIEW'
        @components('panel')
            <p>Before button</p>
            @components('button', ['label' => 'Open report'])
            @endcomponents
            <p>After button</p>
        @endcomponents
        VIEW
    );

    // Page with a nested component inside a named slot.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'nested-named.php',
        <<<'VIEW'
        @components('card', [
            'title' => 'Water Supply',
            'status' => 'Open',
        ])
            <p>Report body.</p>

            @slot('footer')
                @components('button', ['label' => 'View details'])
                @endcomponents
            @endslot
        @endcomponents
        VIEW
    );

    // Page checking that slot content can access caller variables.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'caller-scope.php',
        <<<'VIEW'
        @components('panel')
            <p>{{ $callerMessage }}</p>
        @endcomponents
        VIEW
    );

    // Page checking escaping inside slot content.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'escaped-slot.php',
        <<<'VIEW'
        @components('panel')
            <p>{{ $untrustedMessage }}</p>
        @endcomponents
        VIEW
    );

    // Page checking missing slots.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'missing-slots.php',
        <<<'VIEW'
        @components('empty-slots')
            Default content
        @endcomponents
        VIEW
    );

    // Page checking component prop isolation.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'prop-isolation.php',
        <<<'VIEW'
        <?php $secret = 'PRIVATE PAGE DATA'; ?>
        @components('isolated')
        @endcomponents
        VIEW
    );

    // Page referencing a component that does not exist.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'missing-component.php',
        <<<'VIEW'
        @components('does-not-exist')
        @endcomponents
        VIEW
    );

    // Page declaring the same named slot twice.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'duplicate-slots.php',
        <<<'VIEW'
        @components('panel')
            @slot('footer')
                First footer
            @endslot

            @slot('footer')
                Second footer
            @endslot
        @endcomponents
        VIEW
    );

    // Page declaring a slot outside a component.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'orphan-slot.php',
        <<<'VIEW'
        @slot('footer')
            Orphan footer
        @endslot
        VIEW
    );

    // Page with an unclosed named slot.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'unclosed-slot.php',
        <<<'VIEW'
        @components('panel')
            @slot('footer')
                Unclosed footer
        @endcomponents
        VIEW
    );

    // Page with an unclosed component invocation.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'unclosed-component.php',
        <<<'VIEW'
        @components('panel')
            <p>Component content</p>
        VIEW
    );

    // Page declaring a named slot inside another named slot.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'nested-slots.php',
        <<<'VIEW'
        @components('panel')
            @slot('footer')
                Footer content

                @slot('header')
                    Nested header
                @endslot
            @endslot
        @endcomponents
        VIEW
    );

    // Page with @endslot outside a component.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'orphan-endslot.php',
        <<<'VIEW'
        @endslot
        VIEW
    );

    // Page with @endcomponents outside a component.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'orphan-endcomponents.php',
        <<<'VIEW'
        @endcomponents
        VIEW
    );

    // Page attempting to render a slot outside a component.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'orphan-render-slot.php',
        <<<'VIEW'
        @renderSlot()
        VIEW
    );

    // Component attempting to extend a layout.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'invalid-extends.php',
        <<<'VIEW'
        @extends('layouts/app')
        <div>Invalid component</div>
        VIEW
    );

    // Component attempting to declare a section.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'invalid-section.php',
        <<<'VIEW'
        @section('content')
            Invalid component content
        @endsection
        VIEW
    );

    // Component attempting to yield a section.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'invalid-yield.php',
        <<<'VIEW'
        @yield('content')
        VIEW
    );

    // Page rendering a component that attempts to extend a layout.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'invalid-extends.php',
        <<<'VIEW'
        @components('invalid-extends')
        @endcomponents
        VIEW
    );

    // Page rendering a component that attempts to declare a section.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'invalid-section.php',
        <<<'VIEW'
        @components('invalid-section')
        @endcomponents
        VIEW
    );

    // Page rendering a component that attempts to yield a section.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'invalid-yield.php',
        <<<'VIEW'
        @components('invalid-yield')
        @endcomponents
        VIEW
    );

    // Page rendering a component with a unique slot value.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'context-first.php',
        <<<'VIEW'
        @components('panel')
            FIRST_RENDER_CONTENT
        @endcomponents
        VIEW
    );

    // Page rendering the same component without slot content.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'context-second.php',
        <<<'VIEW'
        @components('panel')
        @endcomponents
        VIEW
    );

    // Component that deliberately throws an exception.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'throwing.php',
        <<<'VIEW'
        <?php
        throw new RuntimeException('Intentional component failure.');
        ?>
        VIEW
    );

    // Page rendering the component that throws an exception.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'throwing-component.php',
        <<<'VIEW'
        @components('throwing')
        @endcomponents
        VIEW
    );

    // Partial that renders a component.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'partials'
            . DIRECTORY_SEPARATOR . 'component-child.php',
        <<<'VIEW'
        @components('button', ['label' => 'Included Button'])
        @endcomponents
        VIEW
    );

    // Partial that renders the active component's default slot.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'partials'
            . DIRECTORY_SEPARATOR . 'slot-child.php',
        <<<'VIEW'
        <div class="included-slot">
            @renderSlot()
        </div>
        VIEW
    );

    // Component that includes a partial which accesses its slot.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'components'
            . DIRECTORY_SEPARATOR . 'include-slot.php',
        <<<'VIEW'
        <section class="include-slot">
            @include('partials/slot-child')
        </section>
        VIEW
    );

    // Page including a partial that renders a component.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'include-component.php',
        <<<'VIEW'
        @include('partials/component-child')
        VIEW
    );

    // Page rendering a component that includes a partial.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'include-slot.php',
        <<<'VIEW'
        @components('include-slot')
            SLOT_CONTENT_THROUGH_INCLUDE
        @endcomponents
        VIEW
    );

    // Partial checking whether an unrelated caller variable is available.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'partials'
            . DIRECTORY_SEPARATOR . 'isolated-data.php',
        <<<'VIEW'
        <?php if (isset($secret)): ?>
            LEAKED
        <?php else: ?>
            ISOLATED
        <?php endif; ?>
        VIEW
    );

    // Page including a partial without explicitly passing its variables.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'include-isolation.php',
        <<<'VIEW'
        <?php $secret = 'PRIVATE PAGE DATA'; ?>

        @include('partials/isolated-data')
        VIEW
    );

    // Page explicitly passing data to an included partial.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'pages'
            . DIRECTORY_SEPARATOR . 'include-with-data.php',
        <<<'VIEW'
        @include('partials/data-value', ['message' => 'EXPLICIT_INCLUDE_DATA'])
        VIEW
    );

    // Partial displaying explicitly supplied data.
    file_put_contents(
        $viewsPath . DIRECTORY_SEPARATOR . 'partials'
            . DIRECTORY_SEPARATOR . 'data-value.php',
        <<<'VIEW'
        <p>{{ $message }}</p>
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

    // 1. Component props and default slot.
    $output = $view->render('pages/basic');

    $assert(
        str_contains($output, '<h2>Community Report</h2>')
            && str_contains($output, '<span>Pending</span>')
            && str_contains($output, '<p>Report information.</p>'),
        'Renders component props and default slot'
    );

    // 2. Named slot.
    $output = $view->render('pages/named-slot');

    $assert(
        str_contains($output, '<p>Report details.</p>')
            && str_contains($output, '<small>Updated today</small>'),
        'Renders named slots'
    );

    // 3. Nested component in default slot.
    $output = $view->render('pages/nested');

    $assert(
        str_contains($output, '<section class="panel">')
            && str_contains($output, '<button type="button">Open report</button>')
            && str_contains($output, '<p>Before button</p>')
            && str_contains($output, '<p>After button</p>'),
        'Renders nested components inside default slots'
    );

    // 4. Nested component in named slot.
    $output = $view->render('pages/nested-named');

    $assert(
        str_contains($output, '<p>Report body.</p>')
            && str_contains($output, '<button type="button">View details</button>'),
        'Renders nested components inside named slots'
    );

    // 5. Slot content can access caller variables.
    $output = $view->render(
        'pages/caller-scope',
        ['callerMessage' => 'Message from the caller']
    );

    $assert(
        str_contains($output, '<p>Message from the caller</p>'),
        'Renders slot content in caller scope'
    );

    // 6. Interpolations inside slots remain escaped.
    $output = $view->render(
        'pages/escaped-slot',
        ['untrustedMessage' => '<script>alert("xss")</script>']
    );

    $assert(
        str_contains(
            $output,
            '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;'
        )
            && !str_contains($output, '<script>'),
        'Escapes dynamic values inside slot content'
    );

    // 7. Missing slots render as empty strings.
    $output = $view->render('pages/missing-slots');

    $assert(
        preg_match(
            '/Default content\s*\|<\/div>/',
            $output
        ) === 1,
        'Renders missing slots as empty strings'
    );

    // 8. Component props do not inherit caller variables.
    $output = $view->render('pages/prop-isolation');

    $assert(
        str_contains($output, 'ISOLATED')
            && !str_contains($output, 'LEAKED'),
        'Keeps component props isolated from caller variables'
    );

    // 9. Missing components throw ViewNotFoundException.
    $assertThrows(
        fn() => $view->render('pages/missing-component'),
        ViewNotFoundException::class,
        'Throws ViewNotFoundException for missing components'
    );

    // 10. Duplicate named slots are rejected.
    $assertThrows(
        fn() => $view->render('pages/duplicate-slots'),
        ViewException::class,
        'Rejects duplicate named slots'
    );

    // 11. A slot outside a component is rejected.
    $assertThrows(
        fn() => $view->render('pages/orphan-slot'),
        ViewException::class,
        'Rejects a slot outside a component'
    );

    // 12. An unclosed named slot is rejected.
    $assertThrows(
        fn() => $view->render('pages/unclosed-slot'),
        ViewException::class,
        'Rejects an unclosed named slot'
    );

    // 13. An unclosed component invocation is rejected.
    $assertThrows(
        fn() => $view->render('pages/unclosed-component'),
        ViewException::class,
        'Rejects an unclosed component invocation'
    );

    // 14. Nested named slots are rejected.
    $assertThrows(
        fn() => $view->render('pages/nested-slots'),
        ViewException::class,
        'Rejects nested named slots'
    );

    // 15. @endslot outside a component is rejected.
    $assertThrows(
        fn() => $view->render('pages/orphan-endslot'),
        ViewException::class,
        'Rejects @endslot outside a component'
    );

    // 16. @endcomponents outside a component is rejected.
    $assertThrows(
        fn() => $view->render('pages/orphan-endcomponents'),
        ViewException::class,
        'Rejects @endcomponents outside a component'
    );

    // 17. @renderSlot outside a component is rejected.
    $assertThrows(
        fn() => $view->render('pages/orphan-render-slot'),
        ViewException::class,
        'Rejects @renderSlot outside a component'
    );

    // 18. @extends is rejected inside component templates.
    $assertThrows(
        fn() => $view->render('pages/invalid-extends'),
        ViewException::class,
        'Rejects @extends inside component templates'
    );

    // 19. @section is rejected inside component templates.
    $assertThrows(
        fn() => $view->render('pages/invalid-section'),
        ViewException::class,
        'Rejects @section inside component templates'
    );

    // 20. @yield is rejected inside component templates.
    $assertThrows(
        fn() => $view->render('pages/invalid-yield'),
        ViewException::class,
        'Rejects @yield inside component templates'
    );

    // 21. Component context does not leak between independent renders.
    $firstOutput = $view->render('pages/context-first');
    $secondOutput = $view->render('pages/context-second');

    $assert(
        str_contains($firstOutput, 'FIRST_RENDER_CONTENT')
            && !str_contains($secondOutput, 'FIRST_RENDER_CONTENT'),
        'Keeps component context isolated between independent renders'
    );

    // 22. Output buffers are cleaned after a component rendering error.
    $initialBufferLevel = ob_get_level();

    $assertThrows(
        fn() => $view->render('pages/throwing-component'),
        ViewException::class,
        'Throws ViewException when a component fails'
    );

    $assert(
        ob_get_level() === $initialBufferLevel,
        'Cleans output buffers after a component rendering error'
    );

    // 23. An included partial can render a component.
    $includedComponentOutput = $view->render(
        'pages/include-component'
    );

    $assert(
        str_contains($includedComponentOutput, 'Included Button'),
        'Renders components inside included partials'
    );

    // 24 An included partial can access its active component's slot.
    $includedSlotOutput = $view->render(
        'pages/include-slot'
    );

    $assert(
        str_contains(
            $includedSlotOutput,
            'SLOT_CONTENT_THROUGH_INCLUDE'
        ),
        'Shares component context between components and included partials'
    );

    // 25. Included partials do not inherit caller variables automatically.
    $includeIsolationOutput = $view->render(
        'pages/include-isolation'
    );

    $assert(
        str_contains($includeIsolationOutput, 'ISOLATED')
            && !str_contains($includeIsolationOutput, 'LEAKED'),
        'Keeps included partial data isolated from caller variables'
    );

    // 26. Included partials receive explicitly supplied data.
    $includeDataOutput = $view->render(
        'pages/include-with-data'
    );

    $assert(
        str_contains($includeDataOutput, 'EXPLICIT_INCLUDE_DATA'),
        'Passes explicit data to included partials'
    );
} finally {
    $removeDirectory($basePath);
}
