<?php

declare(strict_types=1);

use CivicPulse\Framework\Database\ConnectionFactory;
use CivicPulse\Framework\Database\DatabaseConfig;
use CivicPulse\Framework\Database\DatabaseManager;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$config = require dirname(__DIR__) . '/config/database.php';

$config['connections']['mysql']['database'] = 'civicpulse_test';

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


echo "Testing database transactions..." . PHP_EOL;

// Create a temporary table for isolated transaction tests.
$connection->statement(
    'CREATE TEMPORARY TABLE civicpulse_transaction_tests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        value VARCHAR(100) NOT NULL
    )'
);

// Test 1: Successful transaction commits.
$result = $connection->transaction(function ($connection) {
    $connection->statement(
        'INSERT INTO civicpulse_transaction_tests (value) VALUES (?)',
        ['Committed record']
    );

    return 'transaction committed';
});

if ($result !== 'transaction committed') {
    throw new RuntimeException('Transaction return value test failed.');
}

$count = (int) $connection->scalar(
    'SELECT COUNT(*) FROM civicpulse_transaction_tests'
);

if ($count !== 1) {
    throw new RuntimeException('Transaction commit test failed.');
}

echo "Transaction commit: PASSED" . PHP_EOL;

// Test 3: Nested transactions are rejected.
try {
    $connection->transaction(function ($connection) {
        $connection->transaction(function ($connection) {
            return true;
        });
    });

    throw new RuntimeException(
        'Nested transaction rejection test failed.'
    );
} catch (LogicException $exception) {
    if (
        $exception->getMessage()
        !== 'Nested transactions are not supported yet.'
    ) {
        throw $exception;
    }
}

echo "Nested transaction rejection: PASSED" . PHP_EOL;

// Test 2: Failed transaction rolls back.
try {
    $connection->transaction(function ($connection) {
        $connection->statement(
            'INSERT INTO civicpulse_transaction_tests (value) VALUES (?)',
            ['Rolled back record']
        );

        throw new RuntimeException('Intentional rollback test.');
    });

    throw new RuntimeException(
        'Transaction rollback test failed: exception was not propagated.'
    );
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== 'Intentional rollback test.') {
        throw $exception;
    }
}

$count = (int) $connection->scalar(
    'SELECT COUNT(*) FROM civicpulse_transaction_tests'
);

if ($count !== 1) {
    throw new RuntimeException('Transaction rollback test failed.');
}

echo "Transaction rollback: PASSED" . PHP_EOL;

echo "All transaction tests passed." . PHP_EOL;

echo "All database connection tests passed." . PHP_EOL;
