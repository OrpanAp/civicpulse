<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Security\Csrf;

use CivicPulse\Framework\Session\SessionInterface;

final class CsrfTokenManager
{
    private const SESSION_KEY = '_civicpulse_csrf_token';

    /**
     * Retrieve the existing CSRF token or generate a new one.
     */
    public function getToken(SessionInterface $session): string
    {
        $token = $session->get(self::SESSION_KEY);

        // Reuse an existing valid token.
        if (
            is_string($token)
            && preg_match('/\A[a-f0-9]{64}\z/', $token) === 1
        ) {
            return $token;
        }

        // Generate a cryptographically secure token.
        $token = bin2hex(random_bytes(32));

        $session->set(self::SESSION_KEY, $token);

        return $token;
    }

    /**
     * Validate a submitted CSRF token against the session token.
     */
    public function isTokenValid(
        SessionInterface $session,
        string $submittedToken
    ): bool {
        $storedToken = $session->get(self::SESSION_KEY);

        if (
            !is_string($storedToken)
            || preg_match('/\A[a-f0-9]{64}\z/', $storedToken) !== 1
        ) {
            return false;
        }

        return hash_equals($storedToken, $submittedToken);
    }
}
