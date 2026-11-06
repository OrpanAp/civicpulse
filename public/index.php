<?php

declare(strict_types=1);

use CivicPulse\Framework\Application\Application;
use CivicPulse\Framework\Container\Container;
use CivicPulse\Framework\Routing\Router;
use CivicPulse\Framework\View\View;
use CivicPulse\Framework\Http\Middleware\RequestIdMiddleware;
use CivicPulse\Framework\Http\Middleware\RequestLoggingMiddleware;
use CivicPulse\Framework\Http\Middleware\SecurityHeadersMiddleware;
use CivicPulse\Framework\Http\Middleware\SessionMiddleware;
use CivicPulse\Framework\Http\Middleware\CsrfMiddleware;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var Container $container */
$container = require dirname(__DIR__) . '/bootstrap/app.php';

// Resolve the shared router.
$router = $container->make(Router::class);

// Resolve the shared View service.
$view = $container->make(View::class);

// Register application routes.
$registerRoutes = require dirname(__DIR__) . '/routes/web.php';

$registerRoutes($router, $view);

// Resolve the application with its router dependency.
$application = $container->make(Application::class);

// Register global HTTP middleware in execution order.
$application->addMiddleware(
    $container->make(RequestIdMiddleware::class)
);

$application->addMiddleware(
    $container->make(RequestLoggingMiddleware::class)
);

$application->addMiddleware(
    $container->make(SecurityHeadersMiddleware::class)
);

$application->addMiddleware(
    $container->make(SessionMiddleware::class)
);

$application->addMiddleware(
    $container->make(CsrfMiddleware::class)
);

// Handle the HTTP request.
$application->run();
