<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CivicPulse\Framework\Http\Response;

echo "Testing HTTP Response..." . PHP_EOL;

function responseCheck(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException("FAILED: {$message}");
    }

    echo "[PASS] {$message}" . PHP_EOL;
}

// Basic response.

$response = new Response('Hello CivicPulse');

responseCheck(
    $response->getContent() === 'Hello CivicPulse',
    'Response content is returned correctly'
);

responseCheck(
    $response->getStatusCode() === 200,
    'Default response status is 200'
);

responseCheck(
    $response->getHeaders() === [],
    'Default response headers are empty'
);

// HTML response.

$htmlResponse = Response::html(
    '<h1>Welcome to CivicPulse</h1>'
);

responseCheck(
    $htmlResponse->getContent() === '<h1>Welcome to CivicPulse</h1>',
    'HTML response preserves rendered content'
);

responseCheck(
    $htmlResponse->getStatusCode() === 200,
    'HTML response defaults to status 200'
);

responseCheck(
    $htmlResponse->getHeaders()['Content-Type']
        === 'text/html; charset=utf-8',
    'HTML response has the correct content type'
);

// HTML response with custom status and headers.

$customHtmlResponse = Response::html(
    '<h1>Page created</h1>',
    201,
    [
        'X-Application' => 'CivicPulse',
    ]
);

responseCheck(
    $customHtmlResponse->getStatusCode() === 201,
    'HTML response accepts a custom status code'
);

responseCheck(
    $customHtmlResponse->getHeaders()['X-Application'] === 'CivicPulse',
    'HTML response accepts custom headers'
);

responseCheck(
    $customHtmlResponse->getHeaders()['Content-Type']
        === 'text/html; charset=utf-8',
    'Custom HTML headers preserve the default content type'
);

// Custom Content-Type overrides the default.

$customContentTypeResponse = Response::html(
    '<h1>Custom content type</h1>',
    200,
    [
        'Content-Type' => 'text/html',
    ]
);

responseCheck(
    $customContentTypeResponse->getHeaders()['Content-Type'] === 'text/html',
    'Custom Content-Type overrides the default'
);

// JSON response remains compatible.

$jsonResponse = Response::json([
    'application' => 'CivicPulse',
    'status' => 'running',
]);

responseCheck(
    $jsonResponse->getHeaders()['Content-Type']
        === 'application/json; charset=utf-8',
    'JSON response retains its content type'
);

responseCheck(
    json_decode(
        $jsonResponse->getContent(),
        true,
        512,
        JSON_THROW_ON_ERROR
    )['application'] === 'CivicPulse',
    'JSON response content is encoded correctly'
);

// Immutable header handling.

$originalResponse = Response::html('<h1>Original</h1>');

$modifiedResponse = $originalResponse->withHeader(
    'X-Test',
    'enabled'
);

responseCheck(
    !isset($originalResponse->getHeaders()['X-Test']),
    'withHeader does not modify the original response'
);

responseCheck(
    $modifiedResponse->getHeaders()['X-Test'] === 'enabled',
    'withHeader returns a response with the new header'
);

// Header replacement is case-insensitive.

$replacedHeaderResponse = Response::html(
    '<h1>Headers</h1>',
    200,
    [
        'Content-Type' => 'text/html',
    ]
)->withHeader(
    'content-type',
    'application/xhtml+xml'
);

responseCheck(
    count(
        array_filter(
            array_keys($replacedHeaderResponse->getHeaders()),
            static fn(string $name): bool =>
            strcasecmp($name, 'Content-Type') === 0
        )
    ) === 1,
    'withHeader replaces headers case-insensitively'
);

// Invalid status code.

$invalidStatusRejected = false;

try {
    Response::html('<h1>Invalid</h1>', 99);
} catch (InvalidArgumentException) {
    $invalidStatusRejected = true;
}

responseCheck(
    $invalidStatusRejected,
    'Invalid HTTP status codes are rejected'
);

// Invalid header injection.

$invalidHeaderRejected = false;

try {
    Response::html(
        '<h1>Invalid</h1>',
        200,
        [
            'X-Test' => "value\r\nInjected: header",
        ]
    );
} catch (InvalidArgumentException) {
    $invalidHeaderRejected = true;
}

responseCheck(
    $invalidHeaderRejected,
    'Header injection attempts are rejected'
);

echo PHP_EOL . "All HTTP Response tests passed." . PHP_EOL;
