<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Database;

use CivicPulse\Framework\Database\Contracts\DatabaseDriverInterface;
use CivicPulse\Framework\Database\Drivers\MySqlDriver;
use CivicPulse\Framework\Database\Drivers\PostgreSqlDriver;
use CivicPulse\Framework\Database\Drivers\SQLiteDriver;
use InvalidArgumentException;
use RuntimeException;
use PDO;

class ConnectionFactory
{
    public function make(array $config): Connection
    {
        $driverName = strtolower($config['driver'] ?? '');

        $driver = match ($driverName) {
            'mysql', 'mariadb' => new MySqlDriver(),
            'pgsql', 'postgres', 'postgresql' => new PostgreSqlDriver(),
            'sqlite' => new SQLiteDriver(),

            default => throw new InvalidArgumentException(
                "Unsupported database driver [{$driverName}]."
            ),
        };

        $this->ensureDriverAvailable($driverName);

        return new Connection(
            $driver->connect($config)
        );
    }

    private function ensureDriverAvailable(string $driverName): void
    {
        $pdoDriver = match ($driverName) {
            'mariadb' => 'mysql',
            'postgres', 'postgresql' => 'pgsql',
            default => $driverName,
        };

        if (!in_array($pdoDriver, PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException(
                "PDO driver [{$pdoDriver}] is unavailable. "
                    . "Enable the corresponding PHP PDO extension first."
            );
        }
    }
}
