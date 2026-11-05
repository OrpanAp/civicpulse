<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Database;

class DatabaseManager
{
    private array $connections = [];

    public function __construct(
        private readonly DatabaseConfig $config,
        private readonly ConnectionFactory $factory
    ) {}

    public function connection(?string $name = null): Connection
    {
        $name ??= $this->config->defaultConnection();

        if (!isset($this->connections[$name])) {
            $connectionConfig = $this->config->connection($name);

            $this->connections[$name] = $this->factory->make(
                $connectionConfig
            );
        }

        return $this->connections[$name];
    }
}
