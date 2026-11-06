<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CivicPulse\Framework\Http\TrustedProxyResolver;
use CivicPulse\Framework\Application\Application;
use CivicPulse\Framework\Configuration\ConfigRepository;
use CivicPulse\Framework\Container\Container;
use CivicPulse\Framework\Database\ConnectionFactory;
use CivicPulse\Framework\Database\DatabaseConfig;
use CivicPulse\Framework\Database\DatabaseManager;
use CivicPulse\Framework\Routing\Router;
use CivicPulse\Framework\Logging\LoggerInterface;

echo "Testing application service bootstrap..." . PHP_EOL;

function bootstrapCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("FAILED: {$message}");
    }

    echo "PASSED: {$message}" . PHP_EOL;
}

$container = require __DIR__ . '/../bootstrap/app.php';

bootstrapCheck(
    $container instanceof Container,
    'Bootstrap returns a service container'
);

// Configuration repository.

$config = $container->make(ConfigRepository::class);

bootstrapCheck(
    $config instanceof ConfigRepository,
    'Configuration repository resolves from the container'
);

bootstrapCheck(
    $container->make(ConfigRepository::class) === $config,
    'Configuration repository instance is shared'
);

bootstrapCheck(
    $config->has('database.default'),
    'Database default connection is loaded'
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

// Database configuration.

$databaseConfig = $container->make(DatabaseConfig::class);

bootstrapCheck(
    $databaseConfig instanceof DatabaseConfig,
    'Database configuration resolves from the container'
);

bootstrapCheck(
    $databaseConfig->defaultConnection() === $config->get('database.default'),
    'Database configuration uses the configured default connection'
);

bootstrapCheck(
    $container->make(DatabaseConfig::class) === $databaseConfig,
    'Database configuration is a singleton'
);

// Database factory.

$factory = $container->make(ConnectionFactory::class);

bootstrapCheck(
    $factory instanceof ConnectionFactory,
    'Connection factory resolves from the container'
);

bootstrapCheck(
    $container->make(ConnectionFactory::class) === $factory,
    'Connection factory is a singleton'
);

// Database manager.

$manager = $container->make(DatabaseManager::class);

bootstrapCheck(
    $manager instanceof DatabaseManager,
    'Database manager resolves from the container'
);

bootstrapCheck(
    $container->make(DatabaseManager::class) === $manager,
    'Database manager is a singleton'
);

// Trusted proxy resolver.

$trustedProxyResolver = $container->make(TrustedProxyResolver::class);

bootstrapCheck(
    $trustedProxyResolver instanceof TrustedProxyResolver,
    'Trusted proxy resolver resolves through automatic dependency injection'
);

// Router and application.

$router = $container->make(Router::class);

bootstrapCheck(
    $router instanceof Router,
    'Router resolves from the container'
);

bootstrapCheck(
    $container->make(Router::class) === $router,
    'Router is a singleton'
);

$application = $container->make(Application::class);

bootstrapCheck(
    $application instanceof Application,
    'Application resolves through automatic dependency injection'
);

$applicationResolverProperty = new ReflectionProperty(
    Application::class,
    'trustedProxyResolver'
);

bootstrapCheck(
    $applicationResolverProperty->getValue($application) instanceof TrustedProxyResolver,
    'Application receives the trusted proxy resolver through dependency injection'
);

$logger = $container->make(LoggerInterface::class);

bootstrapCheck(
    $logger instanceof LoggerInterface,
    'Logger interface resolves from the container'
);

bootstrapCheck(
    $container->make(LoggerInterface::class) === $logger,
    'Logger is registered as a singleton'
);

echo PHP_EOL . "All application service bootstrap tests passed." . PHP_EOL;
