<?php

declare(strict_types=1);

namespace CivicPulse\Framework\View;

use CivicPulse\Framework\View\Compiler\CompiledViewCache;
use CivicPulse\Framework\View\Exceptions\ViewException;
use CivicPulse\Framework\View\Exceptions\ViewNotFoundException;
use Throwable;

class ViewRenderer
{
    public function __construct(
        private CompiledViewCache $compiledViewCache,
        private HtmlEscaper $escaper
    ) {}

    public function render(
        string $path,
        array $data = [],
        ?View $view = null,
        ?ViewCompositionContext $viewContext = null,
        ?ComponentContext $componentContext = null
    ): string {
        $compiledPath = $this->compiledViewCache->compile($path);

        $viewContext ??= new ViewCompositionContext();

        $componentContext ??= new ComponentContext();

        $initialBufferLevel = ob_get_level();

        $initialComponentDepth = $componentContext->invocationDepth();

        if (!ob_start()) {
            throw new ViewException(
                'Failed to start output buffering.'
            );
        }

        try {
            (static function (
                string $__viewPath,
                string $__compiledViewPath,
                array $__viewData,
                HtmlEscaper $__escaper,
                ?View $view,
                ViewCompositionContext $__viewContext,
                ComponentContext $__componentContext
            ): void {
                extract(
                    $__viewData,
                    EXTR_SKIP
                );

                require $__compiledViewPath;
            })(
                $path,
                $compiledPath,
                $data,
                $this->escaper,
                $view,
                $viewContext,
                $componentContext
            );

            $rendererBufferLevel = $initialBufferLevel + 1;

            if (ob_get_level() < $rendererBufferLevel) {
                throw new ViewException(
                    'The view closed the renderer output buffer.'
                );
            }

            // Preserve output from nested buffers.
            while (ob_get_level() > $rendererBufferLevel) {
                $nestedOutput = ob_get_clean();

                if ($nestedOutput === false) {
                    throw new ViewException(
                        'Failed to capture nested view output.'
                    );
                }

                echo $nestedOutput;
            }

            $output = ob_get_clean();

            if ($output === false) {
                throw new ViewException(
                    'Failed to capture rendered view output.'
                );
            }

            return $output;
        } catch (Throwable $exception) {
            // Clean only buffers opened after this method started.
            while (ob_get_level() > $initialBufferLevel) {
                if (!ob_end_clean()) {
                    break;
                }
            }

            // Remove only component invocations created by this render.
            $componentContext->abortInvocationsToDepth(
                $initialComponentDepth
            );

            // Preserve the specific exception for missing views/components.
            if ($exception instanceof ViewNotFoundException) {
                throw $exception;
            }

            throw new ViewException(
                'Failed to render view: ' . $path,
                previous: $exception
            );
        }
    }
}
