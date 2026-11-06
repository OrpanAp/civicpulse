<?php

declare(strict_types=1);

use CivicPulse\Framework\Application\Application;
use CivicPulse\Framework\Container\Container;
use CivicPulse\Framework\Routing\Router;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var Container $container */
$container = require dirname(__DIR__) . '/bootstrap/app.php';

// Resolve the shared router.
$router = $container->make(Router::class);

// Register application routes.
$registerRoutes = require dirname(__DIR__) . '/routes/web.php';

$registerRoutes($router);

// Resolve the application with its router dependency.
$application = $container->make(Application::class);

// Handle the HTTP request.
$application->run();
