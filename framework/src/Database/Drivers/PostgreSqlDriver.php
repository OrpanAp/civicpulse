<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Database\Drivers;

use CivicPulse\Framework\Database\Contracts\DatabaseDriverInterface;
use InvalidArgumentException;
use PDO;

class PostgreSqlDriver implements DatabaseDriverInterface
{
    public function connect(array $config): PDO
    {
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 5432);
        $database = $config['database'] ?? null;
        $sslmode = $config['sslmode'] ?? null;

        if ($host === '' || preg_match('/[;\x00]/', $host)) {
            throw new InvalidArgumentException('Invalid PostgreSQL host.');
        }

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Invalid PostgreSQL port.');
        }

        if (!is_string($database) || $database === '') {
            throw new InvalidArgumentException('PostgreSQL database name is required.');
        }

        if (preg_match('/[;\x00]/', $database)) {
            throw new InvalidArgumentException('Invalid PostgreSQL database name.');
        }

        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";

        if ($sslmode !== null) {
            if (!is_string($sslmode) || preg_match('/[^a-zA-Z0-9_-]/', $sslmode)) {
                throw new InvalidArgumentException('Invalid PostgreSQL SSL mode.');
            }

            $dsn .= ";sslmode={$sslmode}";
        }

        $options = array_replace([
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ], $config['options'] ?? []);

        return new PDO(
            $dsn,
            (string) ($config['username'] ?? ''),
            (string) ($config['password'] ?? ''),
            $options
        );
    }
}
