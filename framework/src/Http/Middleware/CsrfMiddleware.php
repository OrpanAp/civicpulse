<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Http\Middleware;

use CivicPulse\Framework\Http\Contracts\RequestHandlerInterface;
use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;
use CivicPulse\Framework\Security\Csrf\CsrfTokenManager;
use CivicPulse\Framework\Session\SessionInterface;

final class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = [
        'GET',
        'HEAD',
        'OPTIONS',
    ];

    public function __construct(
        private CsrfTokenManager $tokenManager
    ) {}

    public function process(
        Request $request,
        RequestHandlerInterface $handler
    ): Response {
        // Safe HTTP methods do not require CSRF validation.
        if (in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return $handler->handle($request);
        }

        // SessionMiddleware must have started and attached the session.
        $session = $request->getAttribute('session');

        if (!$session instanceof SessionInterface) {
            return $this->forbiddenResponse();
        }

        // Read the token from the HTTP header, if supplied.
        $headerToken = $request->getHeader('X-CSRF-Token');

        // Read the token from the parsed form body, if supplied.
        $parsedBody = $request->getParsedBody();

        $hasFormToken = array_key_exists('_token', $parsedBody);

        $formToken = $hasFormToken
            ? $parsedBody['_token']
            : null;

        // A form token must be a string.
        if ($hasFormToken && !is_string($formToken)) {
            return $this->forbiddenResponse();
        }

        // Reject requests containing contradictory token values.
        if (
            $headerToken !== null
            && $hasFormToken
            && $headerToken !== $formToken
        ) {
            return $this->forbiddenResponse();
        }

        // Prefer the header when present; otherwise use the form token.
        $submittedToken = $headerToken ?? $formToken;

        if (
            !is_string($submittedToken)
            || $submittedToken === ''
        ) {
            return $this->forbiddenResponse();
        }

        // Validate the submitted token against the session token.
        if (!$this->tokenManager->isTokenValid(
            $session,
            $submittedToken
        )) {
            return $this->forbiddenResponse();
        }

        return $handler->handle($request);
    }

    private function forbiddenResponse(): Response
    {
        return new Response(
            'Forbidden',
            403,
            [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-store',
            ]
        );
    }
}
