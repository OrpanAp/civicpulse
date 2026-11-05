<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Database;

use InvalidArgumentException;

class DatabaseConfig
{
    private string $default;

    private array $connections;

    public function __construct(array $config)
    {
        $default = $config['default'] ?? null;
        $connections = $config['connections'] ?? null;

        if (!is_string($default) || trim($default) === '') {
            throw new InvalidArgumentException(
                'A default database connection must be configured.'
            );
        }

        if (!is_array($connections)) {
            throw new InvalidArgumentException(
                'Database connections must be an array.'
            );
        }

        if (!array_key_exists($default, $connections)) {
            throw new InvalidArgumentException(
                "Default database connection [{$default}] is not configured."
            );
        }

        foreach ($connections as $name => $connection) {
            if (!is_array($connection)) {
                throw new InvalidArgumentException(
                    "Database connection [{$name}] must be an array."
                );
            }

            if (
                !isset($connection['driver'])
                || !is_string($connection['driver'])
                || trim($connection['driver']) === ''
            ) {
                throw new InvalidArgumentException(
                    "Database connection [{$name}] must define a non-empty driver."
                );
            }
        }

        // Assign properties only after validating the configuration.
        $this->default = $default;
        $this->connections = $connections;
    }

    public function defaultConnection(): string
    {
        return $this->default;
    }

    public function connection(string $name): array
    {
        if (!isset($this->connections[$name])) {
            throw new InvalidArgumentException(
                "Database connection [{$name}] is not configured."
            );
        }

        return $this->connections[$name];
    }
}
