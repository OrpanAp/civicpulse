<?php

declare(strict_types=1);

$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]
);

$pdo->exec('CREATE DATABASE IF NOT EXISTS civicpulse_test');

echo "Test database ready." . PHP_EOL;
