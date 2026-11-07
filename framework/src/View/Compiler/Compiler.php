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

        $conditionalStack = [];

        $directives = [
            '@include(',
            '@extends(',
            '@section(',
            '@yield(',
            '@endsection',
            '@elseif(',
            '@if(',
            '@else',
            '@endif',
        ];

        while ($position < $templateLength) {
            $interpolationPosition = strpos(
                $template,
                '{{',
                $position
            );

            $nextDirectivePosition = false;
            $nextDirective = null;

            foreach ($directives as $directive) {
                $directivePosition = strpos(
                    $template,
                    $directive,
                    $position
                );

                if (
                    $directivePosition !== false
                    && (
                        $nextDirectivePosition === false
                        || $directivePosition < $nextDirectivePosition
                    )
                ) {
                    $nextDirectivePosition = $directivePosition;
                    $nextDirective = $directive;
                }
            }

            // No more supported template syntax.
            if (
                $interpolationPosition === false
                && $nextDirectivePosition === false
            ) {
                $compiled .= substr($template, $position);
                break;
            }

            // Find whichever syntax appears first.
            $nextPosition = $interpolationPosition === false
                ? $nextDirectivePosition
                : (
                    $nextDirectivePosition === false
                    ? $interpolationPosition
                    : min(
                        $interpolationPosition,
                        $nextDirectivePosition
                    )
                );

            $compiled .= substr(
                $template,
                $position,
                $nextPosition - $position
            );

            // Compile escaped interpolation.
            if ($nextPosition === $interpolationPosition) {
                $expressionStart = $nextPosition + 2;

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

                $compiled .= '<?= $__escaper->escape('
                    . $expression
                    . ') ?>';

                $position = $closingPosition + 2;
                continue;
            }

            // Compile @endsection.
            if ($nextDirective === '@endsection') {
                $compiled .= '<?php $__viewContext->endSection(); ?>';

                $position = $nextPosition + strlen('@endsection');
                continue;
            }

            // Compile @else.
            if ($nextDirective === '@else') {
                if ($conditionalStack === []) {
                    throw new ViewException(
                        '@else must appear inside an @if block.'
                    );
                }

                $conditionalIndex = array_key_last($conditionalStack);

                if ($conditionalStack[$conditionalIndex]['hasElse']) {
                    throw new ViewException(
                        'An @if block cannot contain multiple @else directives.'
                    );
                }

                $conditionalStack[$conditionalIndex]['hasElse'] = true;

                $compiled .= '<?php else: ?>';

                $position = $nextPosition + strlen('@else');
                continue;
            }

            // Compile @endif.
            if ($nextDirective === '@endif') {
                if ($conditionalStack === []) {
                    throw new ViewException(
                        '@endif does not have a matching @if directive.'
                    );
                }

                array_pop($conditionalStack);

                $compiled .= '<?php endif; ?>';

                $position = $nextPosition + strlen('@endif');
                continue;
            }

            // Find the directive's closing parenthesis.
            $directiveArgumentsStart = $nextPosition
                + strlen($nextDirective);

            $closingPosition = $this->findDirectiveEnd(
                $template,
                $directiveArgumentsStart
            );

            if ($closingPosition === null) {
                throw new ViewException(
                    'Unclosed ' . rtrim($nextDirective, '(') . ' directive.'
                );
            }

            $arguments = $this->splitDirectiveArguments(
                substr(
                    $template,
                    $directiveArgumentsStart,
                    $closingPosition - $directiveArgumentsStart
                )
            );

            // Compile @if.
            if ($nextDirective === '@if(') {
                if (count($arguments) !== 1) {
                    throw new ViewException(
                        '@if accepts exactly one condition.'
                    );
                }

                $condition = trim($arguments[0]);

                if ($condition === '') {
                    throw new ViewException(
                        '@if condition cannot be empty.'
                    );
                }

                $conditionalStack[] = [
                    'hasElse' => false,
                ];

                $compiled .= '<?php if ('
                    . $condition
                    . '): ?>';
            }

            // Compile @elseif.
            elseif ($nextDirective === '@elseif(') {
                if ($conditionalStack === []) {
                    throw new ViewException(
                        '@elseif must appear inside an @if block.'
                    );
                }

                $conditionalIndex = array_key_last($conditionalStack);

                if ($conditionalStack[$conditionalIndex]['hasElse']) {
                    throw new ViewException(
                        '@elseif cannot appear after @else.'
                    );
                }

                if (count($arguments) !== 1) {
                    throw new ViewException(
                        '@elseif accepts exactly one condition.'
                    );
                }

                $condition = trim($arguments[0]);

                if ($condition === '') {
                    throw new ViewException(
                        '@elseif condition cannot be empty.'
                    );
                }

                $compiled .= '<?php elseif ('
                    . $condition
                    . '): ?>';
            }

            // Compile @include.
            if ($nextDirective === '@include(') {
                if (count($arguments) < 1 || count($arguments) > 2) {
                    throw new ViewException(
                        '@include accepts a view name and an optional data array.'
                    );
                }

                $viewName = $this->extractStaticName(
                    $arguments[0],
                    '@include'
                );

                if (count($arguments) === 1) {
                    $compiled .= '<?= $view->render(\''
                        . $viewName
                        . '\') ?>';
                } else {
                    $dataExpression = trim($arguments[1]);

                    if (!$this->isSingleArrayLiteral($dataExpression)) {
                        throw new ViewException(
                            '@include data must be an explicit array literal.'
                        );
                    }

                    $compiled .= '<?= $view->render(\''
                        . $viewName
                        . '\', '
                        . $dataExpression
                        . ') ?>';
                }
            }

            // Compile @extends.
            elseif ($nextDirective === '@extends(') {
                if (count($arguments) < 1 || count($arguments) > 2) {
                    throw new ViewException(
                        '@extends accepts a layout name and an optional data array.'
                    );
                }

                $layoutName = $this->extractStaticName(
                    $arguments[0],
                    '@extends'
                );

                $dataExpression = '[]';

                if (count($arguments) === 2) {
                    $dataExpression = trim($arguments[1]);

                    if (!$this->isSingleArrayLiteral($dataExpression)) {
                        throw new ViewException(
                            '@extends data must be an explicit array literal.'
                        );
                    }
                }

                $compiled .= '<?php $__viewContext->extend(\''
                    . $layoutName
                    . '\', '
                    . $dataExpression
                    . '); ?>';
            }

            // Compile @section.
            elseif ($nextDirective === '@section(') {
                if (count($arguments) !== 1) {
                    throw new ViewException(
                        '@section accepts exactly one section name.'
                    );
                }

                $sectionName = $this->extractStaticName(
                    $arguments[0],
                    '@section'
                );

                $compiled .= '<?php $__viewContext->startSection(\''
                    . $sectionName
                    . '\'); ?>';
            }

            // Compile @yield.
            elseif ($nextDirective === '@yield(') {
                if (count($arguments) !== 1) {
                    throw new ViewException(
                        '@yield accepts exactly one section name.'
                    );
                }

                $sectionName = $this->extractStaticName(
                    $arguments[0],
                    '@yield'
                );

                $compiled .= '<?= $__viewContext->yieldSection(\''
                    . $sectionName
                    . '\') ?>';
            }

            $position = $closingPosition + 1;
        }

        if ($conditionalStack !== []) {
            throw new ViewException(
                'Unclosed @if conditional block.'
            );
        }

        return $compiled;
    }

    /**
     * Extracts and validates a quoted, static template or section name.
     */
    private function extractStaticName(
        string $expression,
        string $directive
    ): string {
        $expression = trim($expression);

        $pattern = "~\\A'([A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*)'\\z~D";

        $matches = [];

        $valid = preg_match(
            $pattern,
            $expression,
            $matches
        ) === 1;

        if (!$valid) {
            $pattern = '~\A"([A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*)"\z~D';

            $valid = preg_match(
                $pattern,
                $expression,
                $matches
            ) === 1;
        }

        if (!$valid) {
            throw new ViewException(
                $directive . ' requires a quoted, static name.'
            );
        }

        return $matches[1];
    }

    /**
     * Finds the closing parenthesis of an @include directive.
     */
    private function findDirectiveEnd(
        string $template,
        int $start
    ): ?int {
        $length = strlen($template);

        $quote = null;
        $escaped = false;

        $parenthesesDepth = 1;
        $bracketsDepth = 0;
        $bracesDepth = 0;

        for ($position = $start; $position < $length; $position++) {
            $character = $template[$position];

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

            if (
                $character === "'"
                || $character === '"'
                || $character === '`'
            ) {
                $quote = $character;
                continue;
            }

            if ($character === '(') {
                $parenthesesDepth++;
                continue;
            }

            if ($character === ')') {
                if ($bracketsDepth === 0 && $bracesDepth === 0) {
                    if ($parenthesesDepth === 1) {
                        return $position;
                    }

                    $parenthesesDepth--;
                    continue;
                }

                if ($parenthesesDepth > 1) {
                    $parenthesesDepth--;
                    continue;
                }

                throw new ViewException(
                    'Unexpected closing parenthesis in @include directive.'
                );
            }

            if ($character === '[') {
                $bracketsDepth++;
                continue;
            }

            if ($character === ']') {
                if ($bracketsDepth === 0) {
                    throw new ViewException(
                        'Unexpected closing bracket in @include directive.'
                    );
                }

                $bracketsDepth--;
                continue;
            }

            if ($character === '{') {
                $bracesDepth++;
                continue;
            }

            if ($character === '}') {
                if ($bracesDepth === 0) {
                    throw new ViewException(
                        'Unexpected closing brace in @include directive.'
                    );
                }

                $bracesDepth--;
            }
        }

        return null;
    }

    /**
     * Splits directive arguments at top-level commas.
     */
    private function splitDirectiveArguments(string $expression): array
    {
        $arguments = [];
        $start = 0;
        $length = strlen($expression);

        $quote = null;
        $escaped = false;

        $parenthesesDepth = 0;
        $bracketsDepth = 0;
        $bracesDepth = 0;

        for ($position = 0; $position < $length; $position++) {
            $character = $expression[$position];

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

            if (
                $character === "'"
                || $character === '"'
                || $character === '`'
            ) {
                $quote = $character;
                continue;
            }

            if ($character === '(') {
                $parenthesesDepth++;
                continue;
            }

            if ($character === ')') {
                if ($parenthesesDepth === 0) {
                    throw new ViewException(
                        'Unexpected closing parenthesis in @include arguments.'
                    );
                }

                $parenthesesDepth--;
                continue;
            }

            if ($character === '[') {
                $bracketsDepth++;
                continue;
            }

            if ($character === ']') {
                if ($bracketsDepth === 0) {
                    throw new ViewException(
                        'Unexpected closing bracket in @include arguments.'
                    );
                }

                $bracketsDepth--;
                continue;
            }

            if ($character === '{') {
                $bracesDepth++;
                continue;
            }

            if ($character === '}') {
                if ($bracesDepth === 0) {
                    throw new ViewException(
                        'Unexpected closing brace in @include arguments.'
                    );
                }

                $bracesDepth--;
                continue;
            }

            if (
                $character === ','
                && $parenthesesDepth === 0
                && $bracketsDepth === 0
                && $bracesDepth === 0
            ) {
                $arguments[] = trim(
                    substr($expression, $start, $position - $start)
                );

                $start = $position + 1;
            }
        }

        if (
            $quote !== null
            || $parenthesesDepth !== 0
            || $bracketsDepth !== 0
            || $bracesDepth !== 0
        ) {
            throw new ViewException(
                'Malformed @include arguments.'
            );
        }

        $arguments[] = trim(substr($expression, $start));

        if (in_array('', $arguments, true)) {
            throw new ViewException(
                'Empty @include arguments are not allowed.'
            );
        }

        return $arguments;
    }

    /**
     * Ensures the expression is one complete short-array literal.
     */
    private function isSingleArrayLiteral(string $expression): bool
    {
        $length = strlen($expression);

        if (
            $length < 2
            || $expression[0] !== '['
            || $expression[$length - 1] !== ']'
        ) {
            return false;
        }

        $quote = null;
        $escaped = false;

        $bracketsDepth = 0;
        $parenthesesDepth = 0;
        $bracesDepth = 0;

        for ($position = 0; $position < $length; $position++) {
            $character = $expression[$position];

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

            if (
                $character === "'"
                || $character === '"'
                || $character === '`'
            ) {
                $quote = $character;
                continue;
            }

            if ($character === '[') {
                $bracketsDepth++;
                continue;
            }

            if ($character === ']') {
                $bracketsDepth--;

                if ($bracketsDepth < 0) {
                    return false;
                }

                if (
                    $bracketsDepth === 0
                    && $position !== $length - 1
                ) {
                    return false;
                }

                continue;
            }

            if ($character === '(') {
                $parenthesesDepth++;
                continue;
            }

            if ($character === ')') {
                $parenthesesDepth--;

                if ($parenthesesDepth < 0) {
                    return false;
                }

                continue;
            }

            if ($character === '{') {
                $bracesDepth++;
                continue;
            }

            if ($character === '}') {
                $bracesDepth--;

                if ($bracesDepth < 0) {
                    return false;
                }
            }
        }

        return $quote === null
            && $bracketsDepth === 0
            && $parenthesesDepth === 0
            && $bracesDepth === 0;
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

            if (
                $character === "'"
                || $character === '"'
                || $character === '`'
            ) {
                $quote = $character;
                continue;
            }

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

            if ($character === '{') {
                $bracesDepth++;
                continue;
            }

            if ($character === '}') {
                if ($bracesDepth > 0) {
                    $bracesDepth--;
                    continue;
                }

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

        return null;
    }
}
