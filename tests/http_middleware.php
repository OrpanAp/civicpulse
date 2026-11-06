<?php

declare(strict_types=1);

use CivicPulse\Framework\Application\Application;
use CivicPulse\Framework\Http\Middleware\MiddlewareInterface;
use CivicPulse\Framework\Http\Contracts\RequestHandlerInterface;
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

// Shared state lets middleware and the route record execution order.
$state = (object) [
    'events' => [],
];

// Create an isolated router.
$router = new Router();

$router->get(
    '/reports',
    static function (Request $request) use ($state): Response {
        $state->events[] = 'router';

        return Response::json([
            'path' => $request->getPath(),
        ]);
    }
);

// Create the application.
$application = new Application($router);

// Register the first middleware.
$application->addMiddleware(
    new class($state) implements MiddlewareInterface {
        public function __construct(
            private object $state
        ) {}

        public function process(
            Request $request,
            RequestHandlerInterface $handler
        ): Response {
            $this->state->events[] = 'first:before';

            $response = $handler->handle($request);

            $this->state->events[] = 'first:after';

            return $response;
        }
    }
);

// Register the second middleware.
$application->addMiddleware(
    new class($state) implements MiddlewareInterface {
        public function __construct(
            private object $state
        ) {}

        public function process(
            Request $request,
            RequestHandlerInterface $handler
        ): Response {
            $this->state->events[] = 'second:before';

            $response = $handler->handle($request);

            $this->state->events[] = 'second:after';

            return $response;
        }
    }
);

// Execute the request.
$response = $application->handle(
    new Request('GET', '/reports')
);

// Verify the response.
$assertSame(
    200,
    $response->getStatusCode(),
    'Middleware pipeline reaches the route and returns HTTP 200'
);

$decoded = json_decode(
    $response->getContent(),
    true,
    512,
    JSON_THROW_ON_ERROR
);

$assertSame(
    '/reports',
    $decoded['path'],
    'The original Request reaches the route handler'
);

// Verify registration order and response unwinding.
$assertSame(
    [
        'first:before',
        'second:before',
        'router',
        'second:after',
        'first:after',
    ],
    $state->events,
    'Middleware executes in registration order and unwinds in reverse order'
);

// Verify that middleware can short-circuit a request.
$shortCircuitState = (object) [
    'routeReached' => false,
];

$shortCircuitRouter = new Router();

$shortCircuitRouter->get(
    '/private',
    static function (Request $request) use ($shortCircuitState): Response {
        $shortCircuitState->routeReached = true;

        return Response::json(['message' => 'Private route']);
    }
);

$shortCircuitApplication = new Application($shortCircuitRouter);

$shortCircuitApplication->addMiddleware(
    new class implements MiddlewareInterface {
        public function process(
            Request $request,
            RequestHandlerInterface $handler
        ): Response {
            return Response::json(
                ['error' => 'Access denied'],
                401
            );
        }
    }
);

$shortCircuitResponse = $shortCircuitApplication->handle(
    new Request('GET', '/private')
);

$assertSame(
    401,
    $shortCircuitResponse->getStatusCode(),
    'Middleware can return its own response without calling the next handler'
);

$assertSame(
    false,
    $shortCircuitState->routeReached,
    'Short-circuit middleware prevents the route from executing'
);

// Verify that an application without middleware still works.
$plainRouter = new Router();

$plainRouter->get(
    '/health',
    static fn(Request $request): Response => Response::json([
        'status' => 'ok',
    ])
);

$plainApplication = new Application($plainRouter);

$plainResponse = $plainApplication->handle(
    new Request('GET', '/health')
);

$assertSame(
    200,
    $plainResponse->getStatusCode(),
    'Application works without registered middleware'
);

echo PHP_EOL . "All HTTP middleware tests passed." . PHP_EOL;
