<?php

declare(strict_types=1);

use CivicPulse\Framework\Application\Application;
use CivicPulse\Framework\Routing\Router;

require dirname(__DIR__) . '/vendor/autoload.php';

$router = new Router();

$registerRoutes = require dirname(__DIR__) . '/routes/web.php';

$registerRoutes($router);

$application = new Application($router);

$application->run();
