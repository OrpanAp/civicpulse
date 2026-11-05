<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Database\Drivers;

use CivicPulse\Framework\Database\Contracts\DatabaseDriverInterface;
use InvalidArgumentException;
use PDO;

class MySqlDriver implements DatabaseDriverInterface
{
    public function connect(array $config): PDO
    {
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 3306);
        $database = $config['database'] ?? null;
        $charset = (string) ($config['charset'] ?? 'utf8mb4');

        if ($host === '' || preg_match('/[;\x00]/', $host)) {
            throw new InvalidArgumentException('Invalid MySQL host.');
        }

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Invalid MySQL port.');
        }

        if ($database !== null) {
            if (!is_string($database) || preg_match('/[;\x00]/', $database)) {
                throw new InvalidArgumentException('Invalid MySQL database name.');
            }
        }

        if ($charset === '' || preg_match('/[^a-zA-Z0-9_]/', $charset)) {
            throw new InvalidArgumentException('Invalid MySQL charset.');
        }

        $dsn = "mysql:host={$host};port={$port};charset={$charset}";

        if ($database !== null && $database !== '') {
            $dsn .= ";dbname={$database}";
        }

        $options = array_replace([
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ], $config['options'] ?? []);

        return new PDO(
            $dsn,
            (string) ($config['username'] ?? ''),
            (string) ($config['password'] ?? ''),
            $options
        );
    }
}
