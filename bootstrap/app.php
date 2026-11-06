<?php

declare(strict_types=1);

use CivicPulse\Framework\Configuration\ConfigRepository;
use CivicPulse\Framework\Configuration\Environment;
use CivicPulse\Framework\Container\Container;
use CivicPulse\Framework\Database\ConnectionFactory;
use CivicPulse\Framework\Database\DatabaseConfig;
use CivicPulse\Framework\Database\DatabaseManager;
use CivicPulse\Framework\Routing\Router;

$basePath = dirname(__DIR__);

// 1. Load environment variables before configuration.
$environment = new Environment();
$environment->load($basePath . '/.env');

// 2. Load trusted PHP configuration files.
$config = new ConfigRepository();
$config->loadDirectory($basePath . '/config');

// 3. Create the service container.
$container = new Container();

// 4. Register the configuration repository as an existing instance.
$container->instance(ConfigRepository::class, $config);

// 5. Register database services.
$container->singleton(
    DatabaseConfig::class,
    static function (Container $container): DatabaseConfig {
        $config = $container->make(ConfigRepository::class);

        return new DatabaseConfig(
            $config->get('database', [])
        );
    }
);

$container->singleton(ConnectionFactory::class);

$container->singleton(DatabaseManager::class);

// 6. Register the router as a singleton.
$container->singleton(Router::class);

// Return the configured container.
return $container;
