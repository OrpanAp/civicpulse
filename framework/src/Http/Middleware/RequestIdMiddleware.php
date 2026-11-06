<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Http\Middleware;

use CivicPulse\Framework\Http\Contracts\RequestHandlerInterface;
use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;

final class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(
        Request $request,
        RequestHandlerInterface $handler
    ): Response {
        // Generate 128 bits of cryptographically secure random data.
        $requestId = bin2hex(random_bytes(16));

        // Make the ID available to downstream middleware and handlers.
        $request->setAttribute('request_id', $requestId);

        // Continue processing the request.
        $response = $handler->handle($request);

        // Return a new response containing the correlation ID.
        return $response->withHeader(
            'X-Request-ID',
            $requestId
        );
    }
}
