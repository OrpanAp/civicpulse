<?php

declare(strict_types=1);

namespace CivicPulse\Framework\View;

class View
{
    private ComponentRenderer $componentRenderer;

    public function __construct(
        private ViewFinder $finder,
        private ViewRenderer $renderer,
        ?ComponentRenderer $componentRenderer = null
    ) {
        $this->componentRenderer = $componentRenderer
            ?? new ComponentRenderer($finder, $renderer);
    }

    public function render(
        string $name,
        array $data = []
    ): string {
        return $this->renderUsingContext(
            $name,
            $data,
            new ComponentContext()
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
        ComponentContext $componentContext
    ): string {
        return $this->renderUsingContext(
            $name,
            $data,
            $componentContext
        );
    }

    /**
     * Render a view using the supplied component context.
     */
    private function renderUsingContext(
        string $name,
        array $data,
        ComponentContext $componentContext
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
            $componentContext
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
            $componentContext
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

        // Render the page independently.
        $pageContent = $this->renderer->render(
            $pagePath,
            $pageData,
            $this,
            null,
            $componentContext
        );

        // The rendered page content is controlled by this method.
        $layoutData['content'] = $pageContent;

        // Render the layout with its own data.
        return $this->renderer->render(
            $layoutPath,
            $layoutData,
            $this,
            null,
            $componentContext
        );
    }

    public function renderComponent(
        string $name,
        array $props,
        array $slots,
        ComponentContext $componentContext,
        ?ViewCompositionContext $viewContext = null
    ): string {
        return $this->componentRenderer->render(
            $name,
            $props,
            $slots,
            $componentContext,
            $this,
            $viewContext
        );
    }
}
