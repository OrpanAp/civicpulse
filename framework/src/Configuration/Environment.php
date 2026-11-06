<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Configuration;

use InvalidArgumentException;
use RuntimeException;

final class Environment
{
    /**
     * Load environment variables from a .env file.
     *
     * Existing operating-system environment variables take precedence.
     */
    public function load(string $path, bool $required = false): void
    {
        if (!is_file($path)) {
            if ($required) {
                throw new RuntimeException(
                    "Environment file not found: {$path}"
                );
            }

            return;
        }

        if (!is_readable($path)) {
            throw new RuntimeException(
                "Environment file is not readable: {$path}"
            );
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(
                "Unable to read environment file: {$path}"
            );
        }

        $variables = $this->parse($contents);

        foreach ($variables as $key => $value) {
            // Preserve values already supplied by the operating system
            // or the existing PHP environment.
            if ($this->exists($key)) {
                continue;
            }

            putenv("{$key}={$value}");

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    /**
     * Parse .env contents into key/value pairs.
     *
     * Supported:
     * - KEY=value
     * - KEY="quoted value"
     * - KEY='quoted value'
     * - Blank lines and full-line comments
     * - Inline comments in unquoted values
     * - Basic escapes in double-quoted values
     *
     * Variable interpolation and shell execution are not supported.
     */
    public function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        $variables = [];
        $lines = preg_split('/\r\n|\r|\n/', $contents);

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!preg_match(
                '/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/',
                $line,
                $matches
            )) {
                throw new InvalidArgumentException(
                    "Invalid environment syntax on line {$lineNumber}."
                );
            }

            $key = $matches[1];
            $rawValue = trim($matches[2]);

            $variables[$key] = $this->parseValue(
                $rawValue,
                $lineNumber
            );
        }

        return $variables;
    }

    private function parseValue(
        string $value,
        int $lineNumber
    ): string {
        if ($value === '') {
            return '';
        }

        $quote = $value[0];

        if ($quote === "'" || $quote === '"') {
            return $this->parseQuotedValue($value, $quote, $lineNumber);
        }

        // An inline comment must be separated from the value by whitespace.
        $value = preg_replace('/\s+#.*$/', '', $value);

        return trim($value);
    }

    private function parseQuotedValue(
        string $value,
        string $quote,
        int $lineNumber
    ): string {
        $escaped = false;
        $closingPosition = null;

        for ($i = 1, $length = strlen($value); $i < $length; $i++) {
            $character = $value[$i];

            if ($quote === '"' && $character === '\\' && !$escaped) {
                $escaped = true;
                continue;
            }

            if ($character === $quote && !$escaped) {
                $closingPosition = $i;
                break;
            }

            $escaped = false;
        }

        if ($closingPosition === null) {
            throw new InvalidArgumentException(
                "Unclosed quoted value on line {$lineNumber}."
            );
        }

        $remaining = trim(substr($value, $closingPosition + 1));

        if ($remaining !== '' && !str_starts_with($remaining, '#')) {
            throw new InvalidArgumentException(
                "Unexpected characters after quoted value on line {$lineNumber}."
            );
        }

        $result = substr($value, 1, $closingPosition - 1);

        if ($quote === '"') {
            $result = strtr($result, [
                '\\n' => "\n",
                '\\r' => "\r",
                '\\t' => "\t",
                '\\"' => '"',
                '\\\\' => '\\',
            ]);
        }

        return $result;
    }

    private function exists(string $key): bool
    {
        return getenv($key) !== false
            || array_key_exists($key, $_ENV)
            || array_key_exists($key, $_SERVER);
    }
}
