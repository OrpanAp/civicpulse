<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CivicPulse\Framework\Http\Request;

function requestParsedBodyCheck(
    bool $condition,
    string $description
): void {
    if (!$condition) {
        throw new RuntimeException('[FAIL] ' . $description);
    }

    echo '[PASS] ' . $description . PHP_EOL;
}

echo "Testing parsed request body...\n";

// Test 1: Parsed form data is stored and returned.
$parsedData = [
    'title' => 'Broken road',
    'description' => 'A large pothole near the junction.',
];

$request = new Request(
    'POST',
    '/reports',
    [],
    '',
    $parsedData
);

requestParsedBodyCheck(
    $request->getParsedBody() === $parsedData,
    'Parsed request data is returned correctly'
);

// Test 2: Missing parsed data defaults to an empty array.
$emptyRequest = new Request(
    'GET',
    '/',
);

requestParsedBodyCheck(
    $emptyRequest->getParsedBody() === [],
    'Parsed request data defaults to an empty array'
);

// Test 3: Raw body and parsed body remain separate.
$rawBody = '{"title":"Broken road"}';

$jsonRequest = new Request(
    'POST',
    '/api/reports',
    ['Content-Type' => 'application/json'],
    $rawBody,
    []
);

requestParsedBodyCheck(
    $jsonRequest->getBody() === $rawBody,
    'Raw request body remains unchanged'
);

requestParsedBodyCheck(
    $jsonRequest->getParsedBody() === [],
    'Raw JSON body is not mistaken for parsed form data'
);

echo "\nAll parsed request body tests passed.\n";
