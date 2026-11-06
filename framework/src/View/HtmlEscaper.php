<?php

declare(strict_types=1);

namespace CivicPulse\Framework\View;

final class HtmlEscaper
{
    public function escape(
        string|int|float|bool|null $value
    ): string {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}
