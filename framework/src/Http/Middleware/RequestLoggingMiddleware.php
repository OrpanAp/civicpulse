<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Http\Middleware;

use CivicPulse\Framework\Http\Contracts\RequestHandlerInterface;
use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;
use CivicPulse\Framework\Logging\LoggerInterface;
use Throwable;

final class RequestLoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function process(
        Request $request,
        RequestHandlerInterface $handler
    ): Response {
        $startedAt = hrtime(true);

        try {
            $response = $handler->handle($request);
        } catch (Throwable $exception) {
            $duration = (hrtime(true) - $startedAt) / 1_000_000;

            $this->logSafely(
                'error',
                'HTTP request failed',
                [
                    'request_id' => $request->getAttribute('request_id'),
                    'method' => $request->getMethod(),
                    'path' => $request->getPath(),
                    'duration_ms' => round($duration, 3),
                    'exception' => $exception::class,
                ]
            );

            // Preserve the original application exception.
            throw $exception;
        }

        $duration = (hrtime(true) - $startedAt) / 1_000_000;

        $this->logSafely(
            'info',
            'HTTP request completed',
            [
                'request_id' => $request->getAttribute('request_id'),
                'method' => $request->getMethod(),
                'path' => $request->getPath(),
                'status' => $response->getStatusCode(),
                'duration_ms' => round($duration, 3),
            ]
        );

        return $response;
    }

    private function logSafely(
        string $level,
        string $message,
        array $context
    ): void {
        try {
            $this->logger->log($level, $message, $context);
        } catch (Throwable) {
            // Logging failures must not interrupt HTTP request handling.
        }
    }
}
