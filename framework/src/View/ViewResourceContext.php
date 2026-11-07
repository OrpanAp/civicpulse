<?php

declare(strict_types=1);

namespace CivicPulse\Framework\View;

use CivicPulse\Framework\View\Exceptions\ViewException;

/**
 * Stores CSS and JavaScript registrations for one view render.
 *
 * Resources are deduplicated by their logical keys.
 * The first registration wins unless a later registration
 * explicitly requests an override.
 */
final class ViewResourceContext
{
    /**
     * Registered resources grouped by type and logical key.
     *
     * @var array{
     *     styles: array<string, string>,
     *     scripts: array<string, string>
     * }
     */
    private array $resources = [
        'styles' => [],
        'scripts' => [],
    ];

    /**
     * Register a stylesheet or script.
     *
     * @param 'styles'|'scripts' $type
     */
    public function register(
        string $type,
        string $key,
        string $path,
        bool $override = false
    ): void {
        if (!array_key_exists($type, $this->resources)) {
            throw new ViewException(
                'Unsupported resource type: ' . $type
            );
        }

        if (
            $key === ''
            || preg_match(
                '/\A[a-zA-Z0-9][a-zA-Z0-9._:-]*\z/',
                $key
            ) !== 1
        ) {
            throw new ViewException(
                'Invalid resource key: ' . $key
            );
        }

        if (trim($path) === '') {
            throw new ViewException(
                'Resource path cannot be empty.'
            );
        }

        // First registration wins unless explicitly overridden.
        if (
            array_key_exists($key, $this->resources[$type])
            && !$override
        ) {
            return;
        }

        // Replacing an existing associative-array value
        // preserves its original insertion position.
        $this->resources[$type][$key] = $path;
    }

    /**
     * Retrieve registered resources in their registration order.
     *
     * @param 'styles'|'scripts' $type
     * @return list<array{key: string, path: string}>
     */
    public function getResources(string $type): array
    {
        if (!array_key_exists($type, $this->resources)) {
            throw new ViewException(
                'Unsupported resource type: ' . $type
            );
        }

        $result = [];

        foreach ($this->resources[$type] as $key => $path) {
            $result[] = [
                'key' => $key,
                'path' => $path,
            ];
        }

        return $result;
    }
}
