<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Logging;

interface LoggerInterface
{
    public function log(
        string $level,
        string $message,
        array $context = []
    ): void;
}
