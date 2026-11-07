<?php

declare(strict_types=1);

namespace CivicPulse\Framework\View;

final class ComponentRenderer
{
    public function __construct(
        private ViewFinder $finder,
        private ViewRenderer $renderer
    ) {}

    /**
     * Render a component using explicitly supplied props and slots.
     *
     * Component templates are resolved from the components directory.
     * Their variable scope contains only the supplied props.
     *
     * @param array<string, mixed> $props
     * @param array<string, string> $slots
     */
    public function render(
        string $name,
        array $props,
        array $slots,
        ComponentContext $componentContext,
        ?View $view = null,
        ?ViewCompositionContext $viewContext = null
    ): string {
        // Resolve the component before rendering.
        // Missing components retain ViewNotFoundException.
        $path = $this->finder->find(
            'components/' . $name
        );

        // Make this component's slots available to its template.
        $componentContext->beginRendering($slots);

        try {
            return $this->renderer->render(
                $path,
                $props,
                $view,
                $viewContext,
                $componentContext
            );
        } finally {
            // Always restore the previous rendering frame.
            $componentContext->endRendering();
        }
    }
}
