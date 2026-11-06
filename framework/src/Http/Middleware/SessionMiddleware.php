<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Http\Middleware;

use CivicPulse\Framework\Http\Contracts\RequestHandlerInterface;
use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;
use CivicPulse\Framework\Session\SessionManager;
use Throwable;

final class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionManager $sessionManager
    ) {}

    public function process(
        Request $request,
        RequestHandlerInterface $handler
    ): Response {
        // Start the session before the application handles the request.
        $session = $this->sessionManager->start($request);

        // Sessions may be disabled through configuration.
        if ($session === null) {
            return $handler->handle($request);
        }

        // Make the session available to controllers and other middleware.
        $request->setAttribute('session', $session);

        $handlerException = null;

        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            $handlerException = $exception;

            throw $exception;
        } finally {
            try {
                $this->sessionManager->close();
            } catch (Throwable $closeException) {
                if ($handlerException === null) {
                    throw $closeException;
                }

                // Preserve the original application exception.
                error_log(
                    'Session cleanup failed after an application exception: '
                        . $closeException->getMessage()
                );
            }
        }
    }
}
