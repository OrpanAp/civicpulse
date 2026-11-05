<?php

declare(strict_types=1);

use CivicPulse\Framework\Application\Application;
use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;
use CivicPulse\Framework\Routing\Router;

require dirname(__DIR__) . '/vendor/autoload.php';

$assertSame = static function (
    mixed $expected,
    mixed $actual,
    string $description
): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $description
                . "\nExpected: " . var_export($expected, true)
                . "\nActual: " . var_export($actual, true)
        );
    }

    echo "[PASS] {$description}" . PHP_EOL;
};

// Create an isolated router.
$router = new Router();

$router->get(
    '/reports',
    static function (Request $request): Response {
        return Response::json([
            'page' => $request->getQueryParam('page', 1),
        ]);
    }
);

// Test lowercase method normalization in addRoute().
$router->addRoute(
    'get',
    '/normalized',
    static function (Request $request): Response {
        return Response::json(['normalized' => true]);
    }
);

$application = new Application($router);

// Test Request -> Router -> Response.
$response = $application->handle(
    new Request('GET', '/reports?page=2')
);

$assertSame(
    200,
    $response->getStatusCode(),
    'GET /reports returns HTTP 200'
);

$assertSame(
    'application/json; charset=utf-8',
    $response->getHeaders()['Content-Type'],
    'JSON content type is set'
);

$decoded = json_decode(
    $response->getContent(),
    true,
    512,
    JSON_THROW_ON_ERROR
);

$assertSame(
    '2',
    $decoded['page'],
    'Query parameters reach the route handler'
);

// Test method normalization.
$response = $application->handle(
    new Request('GET', '/normalized')
);

$assertSame(
    200,
    $response->getStatusCode(),
    'Router normalizes directly registered HTTP methods'
);

// Test 405 Method Not Allowed.
$response = $application->handle(
    new Request('POST', '/reports')
);

$assertSame(
    405,
    $response->getStatusCode(),
    'Wrong method returns HTTP 405'
);

$assertSame(
    'GET',
    $response->getHeaders()['Allow'],
    '405 response includes the Allow header'
);

// Test 404 Not Found.
$response = $application->handle(
    new Request('GET', '/does-not-exist')
);

$assertSame(
    404,
    $response->getStatusCode(),
    'Unknown route returns HTTP 404'
);

// Test that separate routers do not share routes.
$anotherApplication = new Application(new Router());

$response = $anotherApplication->handle(
    new Request('GET', '/reports')
);

$assertSame(
    404,
    $response->getStatusCode(),
    'Separate Application instances have isolated routes'
);

echo PHP_EOL . "All HTTP lifecycle tests passed." . PHP_EOL;
