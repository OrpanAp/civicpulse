<?php

declare(strict_types=1);

namespace CivicPulse\Framework\View;

use CivicPulse\Framework\View\Exceptions\ViewException;

final class ViewCompositionContext
{
    private ?string $layoutName = null;

    private array $layoutData = [];

    private array $sections = [];

    private ?string $activeSection = null;

    private string $phase = 'child';

    public function extend(
        string $layout,
        array $data = []
    ): void {
        if ($this->phase !== 'child') {
            throw new ViewException(
                'A layout can only be declared by a child view.'
            );
        }

        if ($this->layoutName !== null) {
            throw new ViewException(
                'A view cannot extend more than one layout.'
            );
        }

        if ($this->activeSection !== null) {
            throw new ViewException(
                'A layout must be declared before its sections.'
            );
        }

        $this->layoutName = $layout;
        $this->layoutData = $data;
    }

    public function startSection(string $name): void
    {
        if ($this->phase !== 'child') {
            throw new ViewException(
                'Sections can only be declared in a child view.'
            );
        }

        if ($this->activeSection !== null) {
            throw new ViewException(
                'Nested sections are not supported.'
            );
        }

        if (array_key_exists($name, $this->sections)) {
            throw new ViewException(
                'Duplicate section: ' . $name
            );
        }

        if (!ob_start()) {
            throw new ViewException(
                'Failed to start section output buffering.'
            );
        }

        $this->activeSection = $name;
    }

    public function endSection(): void
    {
        if ($this->activeSection === null) {
            throw new ViewException(
                '@endsection has no matching @section.'
            );
        }

        $content = ob_get_clean();

        if ($content === false) {
            throw new ViewException(
                'Failed to capture section output.'
            );
        }

        $this->sections[$this->activeSection] = $content;
        $this->activeSection = null;
    }

    public function yieldSection(string $name): string
    {
        if ($this->phase !== 'layout') {
            throw new ViewException(
                '@yield can only be used inside a layout.'
            );
        }

        return $this->sections[$name] ?? '';
    }

    public function finishChild(): void
    {
        if ($this->activeSection !== null) {
            throw new ViewException(
                'A section was not closed with @endsection.'
            );
        }

        if ($this->layoutName === null && $this->sections !== []) {
            throw new ViewException(
                'Sections require a layout declared with @extends.'
            );
        }
    }

    public function beginLayout(): void
    {
        if ($this->layoutName === null) {
            throw new ViewException(
                'Cannot render a layout before one has been declared.'
            );
        }

        if ($this->activeSection !== null) {
            throw new ViewException(
                'Cannot render a layout while a section is open.'
            );
        }

        $this->phase = 'layout';
    }

    public function getLayoutName(): ?string
    {
        return $this->layoutName;
    }

    public function getLayoutData(): array
    {
        return $this->layoutData;
    }
}
