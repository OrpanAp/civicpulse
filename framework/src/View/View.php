<?php

declare(strict_types=1);

namespace CivicPulse\Framework\View;

class View
{
    private ComponentRenderer $componentRenderer;

    private HtmlEscaper $escaper;

    private HtmlSanitizer $htmlSanitizer;

    public function __construct(
        private ViewFinder $finder,
        private ViewRenderer $renderer,
        ?ComponentRenderer $componentRenderer = null,
        private ?AssetResolver $assetResolver = null,
        ?HtmlEscaper $escaper = null,
        ?HtmlSanitizer $htmlSanitizer = null
    ) {
        $this->componentRenderer = $componentRenderer
            ?? new ComponentRenderer($finder, $renderer);

        $this->escaper = $escaper ?? new HtmlEscaper();

        $this->htmlSanitizer = $htmlSanitizer ?? new HtmlSanitizer();
    }

    public function render(
        string $name,
        array $data = []
    ): string {
        return $this->renderUsingContext(
            $name,
            $data,
            new ComponentContext(),
            new ViewResourceContext()
        );
    }

    /**
     * Render an included view while sharing the active component context.
     *
     * The included view still receives fresh layout composition state.
     */
    public function renderIncluded(
        string $name,
        array $data,
        ComponentContext $componentContext,
        ?ViewResourceContext $resourceContext = null
    ): string {
        $resourceContext ??= new ViewResourceContext();

        return $this->renderUsingContext(
            $name,
            $data,
            $componentContext,
            $resourceContext
        );
    }

    /**
     * Render a view using the supplied component context.
     */
    private function renderUsingContext(
        string $name,
        array $data,
        ComponentContext $componentContext,
        ViewResourceContext $resourceContext
    ): string {
        // Every render receives fresh layout composition state.
        $viewContext = new ViewCompositionContext();

        $path = $this->finder->find($name);

        // Render the child page first.
        $pageContent = $this->renderer->render(
            $path,
            $data,
            $this,
            $viewContext,
            $componentContext,
            $resourceContext
        );

        // Validate the child's section declarations.
        $viewContext->finishChild();

        $layoutName = $viewContext->getLayoutName();

        // Ordinary views without @extends render normally.
        if ($layoutName === null) {
            return $pageContent;
        }

        // Switch the context to layout rendering.
        $viewContext->beginLayout();

        $layoutPath = $this->finder->find($layoutName);

        // Render the layout with only its explicitly supplied data.
        return $this->renderer->render(
            $layoutPath,
            $viewContext->getLayoutData(),
            $this,
            $viewContext,
            $componentContext,
            $resourceContext
        );
    }

    public function renderWithLayout(
        string $page,
        string $layout,
        array $pageData = [],
        array $layoutData = []
    ): string {
        // Resolve both paths before executing either template.
        $pagePath = $this->finder->find($page);
        $layoutPath = $this->finder->find($layout);

        $componentContext = new ComponentContext();

        $resourceContext = new ViewResourceContext();

        // Render the page independently.
        $pageContent = $this->renderer->render(
            $pagePath,
            $pageData,
            $this,
            null,
            $componentContext,
            $resourceContext
        );

        // The rendered page content is controlled by this method.
        $layoutData['content'] = $pageContent;

        // Render the layout with its own data.
        return $this->renderer->render(
            $layoutPath,
            $layoutData,
            $this,
            null,
            $componentContext,
            $resourceContext
        );
    }

    /**
     * Register resources and render partials at the current template position.
     *
     * Supported definition:
     *
     * [
     *     'partials' => ['partials/report-meta'],
     *     'styles' => [
     *         'components/report-card',
     *         [
     *             'key' => 'report-card',
     *             'path' => 'components/report-card',
     *             'override' => true,
     *         ],
     *     ],
     *     'scripts' => ['components/report-card'],
     * ]
     */
    public function call(
        array $definition,
        ComponentContext $componentContext,
        ViewResourceContext $resourceContext
    ): string {
        $allowedKeys = [
            'partials',
            'styles',
            'scripts',
        ];

        foreach ($definition as $key => $value) {
            if (
                !is_string($key)
                || !in_array($key, $allowedKeys, true)
            ) {
                throw new Exceptions\ViewException(
                    'Unsupported @call resource category.'
                );
            }

            if (!is_array($value) || !array_is_list($value)) {
                throw new Exceptions\ViewException(
                    sprintf(
                        'The @call "%s" value must be a list.',
                        $key
                    )
                );
            }
        }

        $output = '';

        // Partials render immediately at the directive location.
        foreach ($definition['partials'] ?? [] as $partial) {
            if (!is_string($partial) || trim($partial) === '') {
                throw new Exceptions\ViewException(
                    'Every @call partial must be a non-empty view name.'
                );
            }

            $output .= $this->renderIncluded(
                $partial,
                [],
                $componentContext,
                $resourceContext
            );
        }

        // Register CSS and JavaScript resources.
        foreach (['styles', 'scripts'] as $type) {
            foreach ($definition[$type] ?? [] as $resource) {
                $key = null;
                $path = null;
                $override = false;

                if (is_string($resource)) {
                    $path = $resource;

                    // Convert a logical path into a valid default key.
                    $key = str_replace('/', '.', $path);
                } elseif (is_array($resource)) {
                    $allowedDescriptorKeys = [
                        'key',
                        'path',
                        'override',
                    ];

                    foreach ($resource as $descriptorKey => $value) {
                        if (
                            !is_string($descriptorKey)
                            || !in_array(
                                $descriptorKey,
                                $allowedDescriptorKeys,
                                true
                            )
                        ) {
                            throw new Exceptions\ViewException(
                                'Unsupported @call asset descriptor option.'
                            );
                        }
                    }

                    if (
                        !isset($resource['key'])
                        || !is_string($resource['key'])
                        || !isset($resource['path'])
                        || !is_string($resource['path'])
                    ) {
                        throw new Exceptions\ViewException(
                            'An @call asset descriptor requires a string key and path.'
                        );
                    }

                    $key = $resource['key'];
                    $path = $resource['path'];

                    if (array_key_exists('override', $resource)) {
                        if (!is_bool($resource['override'])) {
                            throw new Exceptions\ViewException(
                                '@call asset override must be a boolean.'
                            );
                        }

                        $override = $resource['override'];
                    }
                } else {
                    throw new Exceptions\ViewException(
                        'Every @call asset must be a string or descriptor array.'
                    );
                }

                if (
                    $key === ''
                    || trim($path) === ''
                ) {
                    throw new Exceptions\ViewException(
                        'Asset keys and paths cannot be empty.'
                    );
                }

                if ($this->assetResolver === null) {
                    throw new Exceptions\ViewException(
                        'The asset resolver is not configured.'
                    );
                }

                $url = $this->assetResolver->resolve(
                    $type,
                    $path
                );

                $resourceContext->register(
                    $type,
                    $key,
                    $url,
                    $override
                );
            }
        }

        return $output;
    }

    /**
     * Render registered stylesheet or JavaScript tags.
     */
    public function resources(
        string $type,
        ViewResourceContext $resourceContext
    ): string {
        if (!in_array($type, ['styles', 'scripts'], true)) {
            throw new Exceptions\ViewException(
                'Unsupported resource output type: ' . $type
            );
        }

        $output = [];

        foreach ($resourceContext->getResources($type) as $resource) {
            $url = $this->escaper->escape($resource['path']);

            if ($type === 'styles') {
                $output[] = sprintf(
                    '<link rel="stylesheet" href="%s">',
                    $url
                );
            } else {
                $output[] = sprintf(
                    '<script src="%s" defer></script>',
                    $url
                );
            }
        }

        return implode(PHP_EOL, $output);
    }

    /**
     * Sanitize and render untrusted stored HTML.
     *
     * Unlike ordinary interpolation, this explicitly permits a
     * restricted set of HTML elements and attributes.
     */
    public function build(string $html): string
    {
        return $this->htmlSanitizer->sanitize($html);
    }

    public function renderComponent(
        string $name,
        array $props,
        array $slots,
        ComponentContext $componentContext,
        ?ViewCompositionContext $viewContext = null,
        ?ViewResourceContext $resourceContext = null
    ): string {
        return $this->componentRenderer->render(
            $name,
            $props,
            $slots,
            $componentContext,
            $this,
            $viewContext,
            $resourceContext
        );
    }
}
