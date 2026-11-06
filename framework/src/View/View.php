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
        $path = $this->finder->find($name);

        return $this->renderer->render($path, $data);
    }
}
