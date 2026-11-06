<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Http\Middleware;

use CivicPulse\Framework\Configuration\ConfigRepository;
use CivicPulse\Framework\Http\Contracts\RequestHandlerInterface;
use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;
use RuntimeException;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ConfigRepository $config
    ) {}

    public function process(
        Request $request,
        RequestHandlerInterface $handler
    ): Response {
        // Let the remaining middleware and application handle the request.
        $response = $handler->handle($request);

        // Retrieve the configured security headers.
        $headers = $this->config->get('security.headers', []);

        if (!is_array($headers)) {
            throw new RuntimeException(
                'Security headers configuration must be an array.'
            );
        }

        // Add each configured header to the response.
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                throw new RuntimeException(
                    'Security header names and values must be strings.'
                );
            }

            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
