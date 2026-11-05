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
        $this->default = $config['default'] ?? '';
        $this->connections = $config['connections'] ?? [];

        if ($this->default === '') {
            throw new InvalidArgumentException(
                'A default database connection must be configured.'
            );
        }

        if (!isset($this->connections[$this->default])) {
            throw new InvalidArgumentException(
                "Default database connection [{$this->default}] is not configured."
            );
        }

        if (!is_array($this->connections)) {
            throw new InvalidArgumentException(
                'Database connections must be an array.'
            );
        }

        foreach ($this->connections as $name => $connection) {
            if (!is_array($connection)) {
                throw new InvalidArgumentException(
                    "Database connection [{$name}] must be an array."
                );
            }

            if (!isset($connection['driver'])) {
                throw new InvalidArgumentException(
                    "Database connection [{$name}] must define a driver."
                );
            }
        }
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
