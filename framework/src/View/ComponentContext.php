<?php

declare(strict_types=1);

namespace CivicPulse\Framework\View;

use CivicPulse\Framework\View\Exceptions\ViewException;

/**
 * Manages component invocation and slot rendering state.
 *
 * Invocation frames capture component props and slot content.
 * Rendering frames expose slots to the currently rendering component.
 *
 * Each invocation owns its output buffer, allowing nested components
 * to capture and render their own content independently.
 */
final class ComponentContext
{
    /**
     * Active component invocation frames.
     *
     * @var list<array{
     *     name: string,
     *     props: array,
     *     slots: array<string, string>,
     *     activeSlot: ?string,
     *     bufferLevel: int
     * }>
     */
    private array $invocations = [];

    /**
     * Slot collections belonging to components currently being rendered.
     *
     * @var list<array<string, string>>
     */
    private array $renderingFrames = [];

    /**
     * Begin capturing a component invocation.
     */
    public function begin(
        string $name,
        array $props = []
    ): void {
        if ($name === '') {
            throw new ViewException(
                'Component name cannot be empty.'
            );
        }

        if (!ob_start()) {
            throw new ViewException(
                'Failed to start component output buffering.'
            );
        }

        $this->invocations[] = [
            'name' => $name,
            'props' => $props,
            'slots' => [],
            'activeSlot' => null,
            'bufferLevel' => ob_get_level(),
        ];
    }

    /**
     * Begin capturing a named slot.
     *
     * Content outside named slots belongs to the default slot.
     */
    public function startSlot(string $name): void
    {
        $index = array_key_last($this->invocations);

        if ($index === null) {
            throw new ViewException(
                '@slot can only be used inside a component invocation.'
            );
        }

        if ($name === '' || $name === 'default') {
            throw new ViewException(
                'Invalid named slot: ' . $name
            );
        }

        if ($this->invocations[$index]['activeSlot'] !== null) {
            throw new ViewException(
                'Nested named slots are not supported.'
            );
        }

        if (array_key_exists(
            $name,
            $this->invocations[$index]['slots']
        )) {
            throw new ViewException(
                'Duplicate component slot: ' . $name
            );
        }

        // Finish the current default-slot buffer.
        $content = $this->captureCurrentBuffer($index);

        $this->invocations[$index]['slots']['default'] =
            ($this->invocations[$index]['slots']['default'] ?? '')
            . $content;

        // Start capturing the named slot.
        $this->startBuffer($index);

        $this->invocations[$index]['activeSlot'] = $name;
    }

    /**
     * Finish capturing the active named slot.
     */
    public function endSlot(): void
    {
        $index = array_key_last($this->invocations);

        if ($index === null) {
            throw new ViewException(
                '@endslot has no matching component invocation.'
            );
        }

        $name = $this->invocations[$index]['activeSlot'];

        if ($name === null) {
            throw new ViewException(
                '@endslot has no matching @slot.'
            );
        }

        $content = $this->captureCurrentBuffer($index);

        $this->invocations[$index]['slots'][$name] = $content;

        $this->invocations[$index]['activeSlot'] = null;

        // Resume capturing the default slot.
        $this->startBuffer($index);
    }

    /**
     * Finish a component invocation and return its rendering data.
     *
     * @return array{
     *     name: string,
     *     props: array,
     *     slots: array<string, string>
     * }
     */
    public function end(): array
    {
        $index = array_key_last($this->invocations);

        if ($index === null) {
            throw new ViewException(
                '@endcomponents has no matching @components.'
            );
        }

        if ($this->invocations[$index]['activeSlot'] !== null) {
            throw new ViewException(
                'A named slot was not closed with @endslot.'
            );
        }

        $content = $this->captureCurrentBuffer($index);

        $this->invocations[$index]['slots']['default'] =
            ($this->invocations[$index]['slots']['default'] ?? '')
            . $content;

        $invocation = $this->invocations[$index];

        array_pop($this->invocations);

        return [
            'name' => $invocation['name'],
            'props' => $invocation['props'],
            'slots' => $invocation['slots'],
        ];
    }

    /**
     * Make a component's slots available during its rendering.
     *
     * @param array<string, string> $slots
     */
    public function beginRendering(array $slots): void
    {
        $this->renderingFrames[] = $slots;
    }

    /**
     * Finish the current component rendering frame.
     */
    public function endRendering(): void
    {
        if ($this->renderingFrames === []) {
            throw new ViewException(
                'No active component rendering frame exists.'
            );
        }

        array_pop($this->renderingFrames);
    }

    /**
     * Retrieve a slot from the currently rendering component.
     *
     * Missing slots intentionally produce empty output.
     */
    public function renderSlot(string $name = 'default'): string
    {
        $index = array_key_last($this->renderingFrames);

        if ($index === null) {
            throw new ViewException(
                '@renderSlot can only be used inside a component template.'
            );
        }

        return $this->renderingFrames[$index][$name] ?? '';
    }

    /**
     * Return the number of active invocation frames.
     */
    public function invocationDepth(): int
    {
        return count($this->invocations);
    }

    /**
     * Determine whether a component template is currently being rendered.
     */
    public function isRenderingComponent(): bool
    {
        return $this->renderingFrames !== [];
    }

    /**
     * Prevent layout composition directives inside component templates.
     */
    public function assertCompositionAllowed(): void
    {
        if ($this->isRenderingComponent()) {
            throw new ViewException(
                'Layout composition directives cannot be used inside component templates.'
            );
        }
    }

    /**
     * Abort incomplete invocations after a rendering failure.
     *
     * Only frames created after the supplied checkpoint are removed.
     * This preserves any parent invocation owned by an outer renderer.
     */
    public function abortInvocationsToDepth(int $depth): void
    {
        if ($depth < 0 || $depth > count($this->invocations)) {
            throw new ViewException(
                'Invalid component invocation depth.'
            );
        }

        while (count($this->invocations) > $depth) {
            $index = array_key_last($this->invocations);

            if ($index === null) {
                break;
            }

            $bufferLevel = $this->invocations[$index]['bufferLevel'];

            // Discard this invocation's buffer and any nested buffers.
            while (ob_get_level() >= $bufferLevel) {
                if (!ob_end_clean()) {
                    break;
                }
            }

            array_pop($this->invocations);
        }
    }

    /**
     * Capture and close the active invocation buffer.
     */
    private function captureCurrentBuffer(int $index): string
    {
        $bufferLevel = $this->invocations[$index]['bufferLevel'];

        if (ob_get_level() < $bufferLevel) {
            throw new ViewException(
                'A component closed its invocation output buffer.'
            );
        }

        // Preserve output from nested buffers.
        while (ob_get_level() > $bufferLevel) {
            $nestedOutput = ob_get_clean();

            if ($nestedOutput === false) {
                throw new ViewException(
                    'Failed to capture nested component output.'
                );
            }

            echo $nestedOutput;
        }

        $content = ob_get_clean();

        if ($content === false) {
            throw new ViewException(
                'Failed to capture component slot content.'
            );
        }

        return $content;
    }

    /**
     * Start a buffer for the active invocation.
     */
    private function startBuffer(int $index): void
    {
        if (!ob_start()) {
            throw new ViewException(
                'Failed to start component slot buffering.'
            );
        }

        $this->invocations[$index]['bufferLevel'] = ob_get_level();
    }
}
