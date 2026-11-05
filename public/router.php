<?php

declare(strict_types=1);

$requestedPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
);

if (!is_string($requestedPath)) {
    $requestedPath = '/';
}

$publicRoot = realpath(__DIR__);
$requestedFile = realpath(__DIR__ . $requestedPath);

// Allow existing public files to be served directly.
if (
    $publicRoot !== false
    && $requestedFile !== false
    && is_file($requestedFile)
    && str_starts_with(
        $requestedFile,
        $publicRoot . DIRECTORY_SEPARATOR
    )
) {
    return false;
}

// Everything else goes through the application.
require __DIR__ . '/index.php';
