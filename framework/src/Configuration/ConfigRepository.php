<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Configuration;

use InvalidArgumentException;
use RuntimeException;

final class ConfigRepository
{
    private array $items = [];

    /**
     * Load all PHP configuration files from a directory.
     *
     * Each filename becomes a top-level configuration key.
     * Example: config/database.php becomes database.*
     */
    public function loadDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            throw new RuntimeException(
                "Configuration directory not found: {$directory}"
            );
        }

        if (!is_readable($directory)) {
            throw new RuntimeException(
                "Configuration directory is not readable: {$directory}"
            );
        }

        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . '*.php');

        if ($files === false) {
            throw new RuntimeException(
                "Unable to scan configuration directory: {$directory}"
            );
        }

        sort($files);

        foreach ($files as $file) {
            $name = basename($file, '.php');

            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
                throw new InvalidArgumentException(
                    "Invalid configuration filename: {$name}"
                );
            }

            $configuration = require $file;

            if (!is_array($configuration)) {
                throw new RuntimeException(
                    "Configuration file must return an array: {$file}"
                );
            }

            $this->items[$name] = $configuration;
        }
    }

    /**
     * Retrieve a configuration value using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->items;
        }

        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (
                !is_array($value)
                || !array_key_exists($segment, $value)
            ) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Determine whether a configuration key exists.
     *
     * A key whose value is null still counts as existing.
     */
    public function has(string $key): bool
    {
        if ($key === '') {
            return true;
        }

        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (
                !is_array($value)
                || !array_key_exists($segment, $value)
            ) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    /**
     * Return the complete configuration array.
     */
    public function all(): array
    {
        return $this->items;
    }
}
