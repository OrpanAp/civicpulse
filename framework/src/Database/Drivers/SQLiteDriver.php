<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Database\Drivers;

use CivicPulse\Framework\Database\Contracts\DatabaseDriverInterface;
use InvalidArgumentException;
use PDO;

class SQLiteDriver implements DatabaseDriverInterface
{
    public function connect(array $config): PDO
    {
        $database = $config['database'] ?? null;

        if (!is_string($database) || $database === '') {
            throw new InvalidArgumentException('SQLite database path is required.');
        }

        $options = array_replace([
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ], $config['options'] ?? []);

        return new PDO(
            "sqlite:{$database}",
            null,
            null,
            $options
        );
    }
}
