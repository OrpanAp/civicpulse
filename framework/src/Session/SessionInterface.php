<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Session;

interface SessionInterface
{
    /**
     * Retrieve a value from the session.
     */
    public function get(
        string $key,
        mixed $default = null
    ): mixed;

    /**
     * Store a value in the session.
     */
    public function set(
        string $key,
        mixed $value
    ): void;

    /**
     * Determine whether a key exists in the session.
     */
    public function has(string $key): bool;

    /**
     * Remove a value from the session.
     */
    public function remove(string $key): void;

    /**
     * Remove all session data.
     */
    public function clear(): void;

    /**
     * Generate a new session ID.
     */
    public function regenerateId(
        bool $deleteOldSession = true
    ): void;
}
