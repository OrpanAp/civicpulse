<?php

declare(strict_types=1);

namespace CivicPulse\Framework\View;

class View
{
    public function __construct(
        private ViewFinder $finder,
        private ViewRenderer $renderer
    ) {}

    public function render(
        string $name,
        array $data = []
    ): string {
        // Every independent render receives fresh composition state.
        $viewContext = new ViewCompositionContext();

        $path = $this->finder->find($name);

        // Render the child page first.
        $pageContent = $this->renderer->render(
            $path,
            $data,
            $this,
            $viewContext
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
            $viewContext
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

        // Render the page independently.
        $pageContent = $this->renderer->render(
            $pagePath,
            $pageData,
            $this
        );

        // The rendered page content is controlled by this method.
        $layoutData['content'] = $pageContent;

        // Render the layout with its own data.
        return $this->renderer->render(
            $layoutPath,
            $layoutData,
            $this
        );
    }
}
