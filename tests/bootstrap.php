<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CivicPulse\Framework\Configuration\ConfigRepository;

echo "Testing application configuration bootstrap..." . PHP_EOL;

$config = require __DIR__ . '/../bootstrap/app.php';

function bootstrapCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("FAILED: {$message}");
    }

    echo "PASSED: {$message}" . PHP_EOL;
}

bootstrapCheck(
    $config instanceof ConfigRepository,
    'Bootstrap returns a configuration repository'
);

bootstrapCheck(
    $config->has('database.default'),
    'Database default connection is loaded'
);

bootstrapCheck(
    $config->get('database.default') === 'mysql',
    'Default database connection is mysql'
);

bootstrapCheck(
    $config->get('database.connections.mysql.driver') === 'mysql',
    'MySQL configuration is loaded'
);

bootstrapCheck(
    $config->get('database.connections.pgsql.driver') === 'pgsql',
    'PostgreSQL configuration is loaded'
);

bootstrapCheck(
    $config->get('database.connections.sqlite.driver') === 'sqlite',
    'SQLite configuration is loaded'
);

bootstrapCheck(
    is_int($config->get('database.connections.mysql.port')),
    'Database port retains its integer type'
);

bootstrapCheck(
    $config->get('database.connections.mysql.charset') === 'utf8mb4',
    'MySQL charset configuration is loaded'
);

echo PHP_EOL . "All bootstrap tests passed." . PHP_EOL;
