<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Session;

use CivicPulse\Framework\Configuration\ConfigRepository;
use CivicPulse\Framework\Http\Request;
use LogicException;
use RuntimeException;

final class SessionManager
{
    private bool $ownsSession = false;

    public function __construct(
        private ConfigRepository $config
    ) {}

    public function isEnabled(): bool
    {
        $enabled = $this->config->get('session.enabled', true);

        if (!is_bool($enabled)) {
            throw new RuntimeException(
                'Session enabled configuration must be a boolean.'
            );
        }

        return $enabled;
    }

    public function start(Request $request): ?SessionInterface
    {
        if (!$this->isEnabled()) {
            return null;
        }

        if ($this->ownsSession) {
            throw new LogicException(
                'The session manager has already started a session.'
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new LogicException(
                'Cannot start a session because another session is already active.'
            );
        }

        if (session_status() !== PHP_SESSION_NONE) {
            throw new RuntimeException(
                'Cannot start a session because PHP session handling is disabled.'
            );
        }

        if (headers_sent($file, $line)) {
            throw new RuntimeException(
                "Cannot start a session because headers were already sent in {$file}:{$line}."
            );
        }

        $cookie = $this->config->get('session.cookie', []);
        $security = $this->config->get('session.security', []);

        if (!is_array($cookie) || !is_array($security)) {
            throw new RuntimeException(
                'Session cookie and security configuration must be arrays.'
            );
        }

        $name = $cookie['name'] ?? null;
        $lifetime = $cookie['lifetime'] ?? null;
        $path = $cookie['path'] ?? null;
        $domain = $cookie['domain'] ?? null;
        $configuredSecure = $cookie['secure'] ?? null;
        $httpOnly = $cookie['httponly'] ?? null;
        $sameSite = $cookie['samesite'] ?? null;

        if (
            !is_string($name)
            || $name === ''
            || !preg_match('/^[a-zA-Z0-9_-]+$/', $name)
        ) {
            throw new RuntimeException(
                'Session cookie name is invalid.'
            );
        }

        if (!is_int($lifetime) || $lifetime < 0) {
            throw new RuntimeException(
                'Session cookie lifetime must be a non-negative integer.'
            );
        }

        if (!is_string($path) || $path === '') {
            throw new RuntimeException(
                'Session cookie path must be a non-empty string.'
            );
        }

        if (!is_string($domain)) {
            throw new RuntimeException(
                'Session cookie domain must be a string.'
            );
        }

        if (
            $configuredSecure !== null
            && !is_bool($configuredSecure)
        ) {
            throw new RuntimeException(
                'Session cookie secure setting must be a boolean or null.'
            );
        }

        if (!is_bool($httpOnly)) {
            throw new RuntimeException(
                'Session cookie HttpOnly setting must be a boolean.'
            );
        }

        if (
            !is_string($sameSite)
            || !in_array($sameSite, ['Lax', 'Strict', 'None'], true)
        ) {
            throw new RuntimeException(
                'Session cookie SameSite setting must be Lax, Strict, or None.'
            );
        }

        $cookieSecure = $configuredSecure === true
            || $request->isSecure();

        if ($sameSite === 'None' && !$cookieSecure) {
            throw new RuntimeException(
                'SameSite=None requires a Secure session cookie.'
            );
        }

        $this->configureSecurity($security);

        if (session_name($name) === false) {
            throw new RuntimeException(
                'Failed to configure the session cookie name.'
            );
        }

        $cookieOptions = [
            'lifetime' => $lifetime,
            'path' => $path,
            'domain' => $domain,
            'secure' => $cookieSecure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ];

        if (!session_set_cookie_params($cookieOptions)) {
            throw new RuntimeException(
                'Failed to configure session cookie parameters.'
            );
        }

        if (!session_start()) {
            throw new RuntimeException(
                'Failed to start the PHP session.'
            );
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException(
                'PHP session did not become active.'
            );
        }

        $this->ownsSession = true;

        return new NativeSession();
    }

    public function close(): void
    {
        if (!$this->ownsSession) {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->ownsSession = false;

            throw new RuntimeException(
                'The session manager lost ownership of its active session.'
            );
        }

        if (!session_write_close()) {
            throw new RuntimeException(
                'Failed to write and close the PHP session.'
            );
        }

        $this->ownsSession = false;
    }

    private function configureSecurity(array $security): void
    {
        $settings = [
            'use_strict_mode' => '1',
            'use_only_cookies' => '1',
            'use_trans_sid' => '0',
        ];

        foreach ($settings as $key => $expectedValue) {
            if (($security[$key] ?? null) !== ($expectedValue === '1')) {
                throw new RuntimeException(
                    "Session security policy [{$key}] must be enabled or disabled as required by the framework."
                );
            }

            if (ini_set("session.{$key}", $expectedValue) === false) {
                throw new RuntimeException(
                    "Failed to configure PHP session setting [{$key}]."
                );
            }
        }

        if (ini_set('session.use_cookies', '1') === false) {
            throw new RuntimeException(
                'Failed to enable PHP session cookies.'
            );
        }
    }
}
