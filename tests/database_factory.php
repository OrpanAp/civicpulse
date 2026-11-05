<?php

declare(strict_types=1);

use CivicPulse\Framework\Database\ConnectionFactory;

require_once dirname(__DIR__) . '/vendor/autoload.php';

echo "Testing database connection factory..." . PHP_EOL;

$factory = new ConnectionFactory();

function expectException(
    callable $callback,
    string $expectedClass,
    string $expectedMessage
): void {
    try {
        $callback();
    } catch (Throwable $exception) {
        if (!$exception instanceof $expectedClass) {
            throw new RuntimeException(
                'Expected ' . $expectedClass
                    . ', received ' . get_class($exception)
                    . ': ' . $exception->getMessage()
            );
        }

        if ($exception->getMessage() !== $expectedMessage) {
            throw new RuntimeException(
                "Unexpected exception message: {$exception->getMessage()}"
            );
        }

        echo "PASSED: {$expectedMessage}" . PHP_EOL;
        return;
    }

    throw new RuntimeException(
        "Expected exception {$expectedClass}: {$expectedMessage}"
    );
}

// 1. Unsupported driver.
expectException(
    fn() => $factory->make(['driver' => 'mongodb']),
    InvalidArgumentException::class,
    'Unsupported database driver [mongodb].'
);

// 2. PostgreSQL extension availability.
if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
    foreach (['pgsql', 'postgres', 'postgresql'] as $alias) {
        expectException(
            fn() => $factory->make(['driver' => $alias]),
            RuntimeException::class,
            'PDO driver [pgsql] is unavailable. '
                . 'Enable the corresponding PHP PDO extension first.'
        );
    }
} else {
    echo "SKIPPED: PostgreSQL extension is installed." . PHP_EOL;
}

// 3. SQLite extension availability.
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    expectException(
        fn() => $factory->make(['driver' => 'sqlite']),
        RuntimeException::class,
        'PDO driver [sqlite] is unavailable. '
            . 'Enable the corresponding PHP PDO extension first.'
    );
} else {
    echo "SKIPPED: SQLite extension is installed." . PHP_EOL;
}

// 4. MySQL aliases: both should connect to the same PDO driver.
foreach (['mysql', 'mariadb'] as $alias) {
    $connection = $factory->make([
        'driver' => $alias,
        'host' => '127.0.0.1',
        'port' => 3306,
        'username' => 'root',
        'password' => '',
    ]);

    if ($connection->driverName() !== 'mysql') {
        throw new RuntimeException(
            "MySQL alias [{$alias}] returned an unexpected PDO driver."
        );
    }

    echo "PASSED: {$alias} driver alias" . PHP_EOL;
}

echo "All database factory tests passed." . PHP_EOL;
