<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Session;

use RuntimeException;

final class NativeSession implements SessionInterface
{
    public function get(
        string $key,
        mixed $default = null
    ): mixed {
        $this->assertSessionIsActive();

        return array_key_exists($key, $_SESSION)
            ? $_SESSION[$key]
            : $default;
    }

    public function set(
        string $key,
        mixed $value
    ): void {
        $this->assertSessionIsActive();

        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->assertSessionIsActive();

        return array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): void
    {
        $this->assertSessionIsActive();

        unset($_SESSION[$key]);
    }

    public function clear(): void
    {
        $this->assertSessionIsActive();

        $_SESSION = [];
    }

    public function regenerateId(
        bool $deleteOldSession = true
    ): void {
        $this->assertSessionIsActive();

        if (!session_regenerate_id($deleteOldSession)) {
            throw new RuntimeException(
                'Failed to regenerate the session ID.'
            );
        }
    }

    /**
     * Ensure session operations only happen during an active session.
     */
    private function assertSessionIsActive(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException(
                'Cannot access session data because the session is not active.'
            );
        }

        if (!isset($_SESSION) || !is_array($_SESSION)) {
            throw new RuntimeException(
                'The active session data is not available.'
            );
        }
    }
}
