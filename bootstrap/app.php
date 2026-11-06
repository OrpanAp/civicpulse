<?php

declare(strict_types=1);

use CivicPulse\Framework\Configuration\ConfigRepository;
use CivicPulse\Framework\Configuration\Environment;
use CivicPulse\Framework\Container\Container;
use CivicPulse\Framework\Database\ConnectionFactory;
use CivicPulse\Framework\Database\DatabaseConfig;
use CivicPulse\Framework\Database\DatabaseManager;
use CivicPulse\Framework\Routing\Router;
use CivicPulse\Framework\Logging\ErrorLogLogger;
use CivicPulse\Framework\Logging\LoggerInterface;
use CivicPulse\Framework\Session\SessionManager;
use CivicPulse\Framework\Security\Csrf\CsrfTokenManager;
use CivicPulse\Framework\View\Compiler\Compiler;
use CivicPulse\Framework\View\Compiler\CompiledViewCache;
use CivicPulse\Framework\View\HtmlEscaper;
use CivicPulse\Framework\View\View;
use CivicPulse\Framework\View\ViewFinder;
use CivicPulse\Framework\View\ViewRenderer;

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

// Register the session manager.
$container->singleton(
    SessionManager::class,
    static function (Container $container): SessionManager {
        return new SessionManager(
            $container->make(ConfigRepository::class)
        );
    }
);

// Register the CSRF token manager.
$container->singleton(CsrfTokenManager::class);

// Register the application logger.
$container->singleton(
    LoggerInterface::class,
    ErrorLogLogger::class
);

$container->singleton(ConnectionFactory::class);

$container->singleton(DatabaseManager::class);

// 6. Register the View system.

// The template compiler.
$container->singleton(Compiler::class);

// The compiled template cache needs an explicit filesystem path.
$container->singleton(
    CompiledViewCache::class,
    static function (Container $container) use ($basePath): CompiledViewCache {
        return new CompiledViewCache(
            $basePath . '/storage/framework/views',
            $container->make(Compiler::class)
        );
    }
);

// HTML escaping service.
$container->singleton(HtmlEscaper::class);

// Locate templates inside resources/views.
$container->singleton(
    ViewFinder::class,
    static function () use ($basePath): ViewFinder {
        return new ViewFinder(
            $basePath . '/resources/views'
        );
    }
);

// The renderer receives CompiledViewCache and HtmlEscaper
// through the container's automatic constructor injection.
$container->singleton(ViewRenderer::class);

// The main View service receives ViewFinder and ViewRenderer.
$container->singleton(View::class);

// 7. Register the router as a singleton.
$container->singleton(Router::class);

// Return the configured container.
return $container;
