<?php

declare(strict_types=1);

namespace CivicPulse\Framework\View\Compiler;

use CivicPulse\Framework\View\Exceptions\ViewException;

final class Compiler
{
    public function compile(string $template): string
    {
        $compiled = '';
        $templateLength = strlen($template);
        $position = 0;

        while ($position < $templateLength) {
            // Find the next interpolation opening delimiter.
            $openingPosition = strpos(
                $template,
                '{{',
                $position
            );

            // No more interpolations: preserve the remaining template.
            if ($openingPosition === false) {
                $compiled .= substr($template, $position);
                break;
            }

            // Preserve everything before the interpolation.
            $compiled .= substr(
                $template,
                $position,
                $openingPosition - $position
            );

            // Find the matching closing delimiter.
            $expressionStart = $openingPosition + 2;

            $closingPosition = $this->findExpressionEnd(
                $template,
                $expressionStart
            );

            if ($closingPosition === null) {
                throw new ViewException(
                    'Unclosed template interpolation.'
                );
            }

            $expression = trim(
                substr(
                    $template,
                    $expressionStart,
                    $closingPosition - $expressionStart
                )
            );

            if ($expression === '') {
                throw new ViewException(
                    'Template interpolation cannot be empty.'
                );
            }

            // Generate escaped PHP output.
            $compiled .= '<?= $__escaper->escape('
                . $expression
                . ') ?>';

            // Continue after the closing delimiter.
            $position = $closingPosition + 2;
        }

        return $compiled;
    }

    private function findExpressionEnd(
        string $template,
        int $start
    ): ?int {
        $length = strlen($template);

        $quote = null;
        $escaped = false;

        $parenthesesDepth = 0;
        $bracketsDepth = 0;
        $bracesDepth = 0;

        for ($position = $start; $position < $length; $position++) {
            $character = $template[$position];

            // Inside a quoted string, delimiters and brackets
            // are ordinary string characters.
            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($character === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            // Start a quoted string.
            if (
                $character === "'"
                || $character === '"'
                || $character === '`'
            ) {
                $quote = $character;
                continue;
            }

            // Track nested parentheses.
            if ($character === '(') {
                $parenthesesDepth++;
                continue;
            }

            if ($character === ')') {
                if ($parenthesesDepth === 0) {
                    throw new ViewException(
                        'Unexpected closing parenthesis in template interpolation.'
                    );
                }

                $parenthesesDepth--;
                continue;
            }

            // Track nested square brackets.
            if ($character === '[') {
                $bracketsDepth++;
                continue;
            }

            if ($character === ']') {
                if ($bracketsDepth === 0) {
                    throw new ViewException(
                        'Unexpected closing bracket in template interpolation.'
                    );
                }

                $bracketsDepth--;
                continue;
            }

            // Track nested curly braces.
            if ($character === '{') {
                $bracesDepth++;
                continue;
            }

            if ($character === '}') {
                if ($bracesDepth > 0) {
                    $bracesDepth--;
                    continue;
                }

                // The expression ends only when all nested
                // structures have been closed.
                if (
                    $position + 1 < $length
                    && $template[$position + 1] === '}'
                    && $parenthesesDepth === 0
                    && $bracketsDepth === 0
                ) {
                    return $position;
                }

                throw new ViewException(
                    'Unexpected closing brace in template interpolation.'
                );
            }
        }

        // No valid closing delimiter was found.
        return null;
    }
}
