<?php

declare(strict_types=1);

return [

    'default' => getenv('DB_CONNECTION') ?: 'mysql',

    'connections' => [

        'mysql' => [
            'driver' => 'mysql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_DATABASE') ?: null,
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => getenv('DB_PGSQL_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PGSQL_PORT') ?: 5432),
            'database' => getenv('DB_PGSQL_DATABASE') ?: null,
            'username' => getenv('DB_PGSQL_USERNAME') ?: 'postgres',
            'password' => getenv('DB_PGSQL_PASSWORD') ?: '',
            'sslmode' => getenv('DB_PGSQL_SSLMODE') ?: null,
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => getenv('DB_SQLITE_DATABASE')
                ?: dirname(__DIR__) . '/database/civicpulse.sqlite',
        ],

    ],

];
