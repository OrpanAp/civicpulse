<?php

declare(strict_types=1);

use CivicPulse\Framework\Database\DatabaseConfig;

require_once dirname(__DIR__) . '/vendor/autoload.php';

echo "Testing database configuration..." . PHP_EOL;

function expectInvalidConfiguration(
    array $config,
    string $expectedMessage
): void {
    try {
        new DatabaseConfig($config);
    } catch (InvalidArgumentException $exception) {
        if ($exception->getMessage() !== $expectedMessage) {
            throw new RuntimeException(
                "Unexpected exception message: {$exception->getMessage()}"
            );
        }

        echo "PASSED: {$expectedMessage}" . PHP_EOL;
        return;
    }

    throw new RuntimeException(
        "Expected InvalidArgumentException: {$expectedMessage}"
    );
}

// 1. Missing default connection name.
expectInvalidConfiguration(
    ['connections' => ['mysql' => ['driver' => 'mysql']]],
    'A default database connection must be configured.'
);

// 2. Empty default connection name.
expectInvalidConfiguration(
    [
        'default' => '',
        'connections' => ['mysql' => ['driver' => 'mysql']],
    ],
    'A default database connection must be configured.'
);

// 3. Connections must be an array.
expectInvalidConfiguration(
    ['default' => 'mysql', 'connections' => 'invalid'],
    'Database connections must be an array.'
);

// 4. Default connection must exist.
expectInvalidConfiguration(
    [
        'default' => 'pgsql',
        'connections' => ['mysql' => ['driver' => 'mysql']],
    ],
    'Default database connection [pgsql] is not configured.'
);

// 5. Each connection must be an array.
expectInvalidConfiguration(
    [
        'default' => 'mysql',
        'connections' => ['mysql' => 'invalid'],
    ],
    'Database connection [mysql] must be an array.'
);

// 6. Driver must exist.
expectInvalidConfiguration(
    [
        'default' => 'mysql',
        'connections' => ['mysql' => []],
    ],
    'Database connection [mysql] must define a non-empty driver.'
);

// 7. Driver cannot be empty.
expectInvalidConfiguration(
    [
        'default' => 'mysql',
        'connections' => ['mysql' => ['driver' => '']],
    ],
    'Database connection [mysql] must define a non-empty driver.'
);

// 8. Valid configuration.
$config = new DatabaseConfig([
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
        ],
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ],
    ],
]);

if ($config->defaultConnection() !== 'mysql') {
    throw new RuntimeException('Default connection test failed.');
}

if ($config->connection('sqlite')['driver'] !== 'sqlite') {
    throw new RuntimeException('Named connection test failed.');
}

echo "PASSED: Valid configuration and named connections" . PHP_EOL;

echo "All database configuration tests passed." . PHP_EOL;
