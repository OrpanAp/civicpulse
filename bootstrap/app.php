<?php

declare(strict_types=1);

use CivicPulse\Framework\Configuration\ConfigRepository;
use CivicPulse\Framework\Configuration\Environment;

$basePath = dirname(__DIR__);

// Load environment variables before loading PHP configuration files.
// This matters because config/database.php reads values using getenv().
$environment = new Environment();

$environment->load($basePath . '/.env');

// Load all trusted PHP configuration files.
$config = new ConfigRepository();

$config->loadDirectory($basePath . '/config');

return $config;
