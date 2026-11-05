<?php

declare(strict_types=1);

use CivicPulse\Framework\Database\ConnectionFactory;
use CivicPulse\Framework\Database\DatabaseConfig;
use CivicPulse\Framework\Database\DatabaseManager;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$config = require dirname(__DIR__) . '/config/database.php';

$databaseConfig = new DatabaseConfig($config);

$manager = new DatabaseManager(
    $databaseConfig,
    new ConnectionFactory()
);

echo "Testing database connection..." . PHP_EOL;

$connection = $manager->connection();

echo "Connected driver: " . $connection->driverName() . PHP_EOL;

$version = $connection->scalar('SELECT VERSION()');

echo "Database version: {$version}" . PHP_EOL;

$result = $connection->selectOne(
    'SELECT :message AS message',
    ['message' => 'CivicPulse database is working']
);

if ($result === null || $result['message'] !== 'CivicPulse database is working') {
    throw new RuntimeException('Parameterized query test failed.');
}

echo "Parameterized query: PASSED" . PHP_EOL;

if ($manager->connection() !== $manager->connection('mysql')) {
    throw new RuntimeException('Connection caching test failed.');
}

echo "Connection caching: PASSED" . PHP_EOL;

echo "All database connection tests passed." . PHP_EOL;
