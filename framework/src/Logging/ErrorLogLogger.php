<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Logging;

use JsonException;

final class ErrorLogLogger implements LoggerInterface
{
    /**
     * @throws JsonException
     */
    public function log(
        string $level,
        string $message,
        array $context = []
    ): void {
        $record = [
            'timestamp' => gmdate('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        $encoded = json_encode(
            $record,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
        );

        error_log($encoded);
    }
}
