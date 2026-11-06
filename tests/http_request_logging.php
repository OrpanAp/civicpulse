<?php

declare(strict_types=1);

use CivicPulse\Framework\Application\Application;
use CivicPulse\Framework\Http\Contracts\RequestHandlerInterface;
use CivicPulse\Framework\Http\Middleware\MiddlewareInterface;
use CivicPulse\Framework\Http\Middleware\RequestIdMiddleware;
use CivicPulse\Framework\Http\Middleware\RequestLoggingMiddleware;
use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;
use CivicPulse\Framework\Logging\LoggerInterface;
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

$assertTrue = static function (
    bool $condition,
    string $description
): void {
    if (!$condition) {
        throw new RuntimeException($description);
    }

    echo "[PASS] {$description}" . PHP_EOL;
};

// Store fake log records for inspection.
$logState = (object) [
    'records' => [],
];

$logger = new class($logState) implements LoggerInterface {
    public function __construct(
        private object $state
    ) {}

    public function log(
        string $level,
        string $message,
        array $context = []
    ): void {
        $this->state->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
};

// Create the route.
$router = new Router();

$router->get(
    '/reports',
    static fn(Request $request): Response => Response::json([
        'message' => 'Reports endpoint',
    ])
);

// Build the application.
$application = new Application($router);

// Order matters: request ID middleware must run first.
$application->addMiddleware(new RequestIdMiddleware());

$application->addMiddleware(
    new RequestLoggingMiddleware($logger)
);

// Execute the request.
$request = new Request(
    'GET',
    '/reports?page=2'
);

$response = $application->handle($request);

// Verify the response.
$assertSame(
    200,
    $response->getStatusCode(),
    'Request reaches the route successfully'
);

$requestId = $response->getHeaders()['X-Request-ID'] ?? null;

$assertTrue(
    is_string($requestId)
        && preg_match('/^[a-f0-9]{32}$/D', $requestId) === 1,
    'Response contains a valid generated request ID'
);

$assertSame(
    $requestId,
    $request->getAttribute('request_id'),
    'Request ID is stored on the Request object'
);

// Verify successful request logging.
$assertSame(
    1,
    count($logState->records),
    'One log record is created for a completed request'
);

$record = $logState->records[0];

$assertSame(
    'info',
    $record['level'],
    'Completed request is logged at info level'
);

$assertSame(
    'HTTP request completed',
    $record['message'],
    'Completed request has the expected log message'
);

$assertSame(
    $requestId,
    $record['context']['request_id'],
    'Log record contains the response request ID'
);

$assertSame(
    'GET',
    $record['context']['method'],
    'Log record contains the HTTP method'
);

$assertSame(
    '/reports',
    $record['context']['path'],
    'Log record contains the path without query parameters'
);

$assertSame(
    200,
    $record['context']['status'],
    'Log record contains the HTTP response status'
);

$assertTrue(
    is_float($record['context']['duration_ms'])
        || is_int($record['context']['duration_ms']),
    'Log record contains request duration in milliseconds'
);

// Verify that a second request receives a different ID.
$secondRequest = new Request('GET', '/reports');

$secondResponse = $application->handle($secondRequest);

$secondRequestId = $secondResponse->getHeaders()['X-Request-ID'] ?? null;

$assertTrue(
    is_string($secondRequestId)
        && $secondRequestId !== $requestId,
    'Separate requests receive different request IDs'
);

// Verify exception logging and propagation.
$failureRouter = new Router();

$failureRouter->get(
    '/failure',
    static function (Request $request): Response {
        throw new RuntimeException('Sensitive internal details');
    }
);

$failureApplication = new Application($failureRouter);

$failureApplication->addMiddleware(new RequestIdMiddleware());

$failureApplication->addMiddleware(
    new RequestLoggingMiddleware($logger)
);

$exceptionWasRethrown = false;

try {
    $failureApplication->handle(
        new Request('GET', '/failure')
    );
} catch (RuntimeException $exception) {
    $exceptionWasRethrown = true;

    $assertSame(
        'Sensitive internal details',
        $exception->getMessage(),
        'Original exception is preserved'
    );
}

$assertSame(
    true,
    $exceptionWasRethrown,
    'Request logging does not swallow application exceptions'
);

$errorRecord = $logState->records[2];

$assertSame(
    'error',
    $errorRecord['level'],
    'Failed request is logged at error level'
);

$assertSame(
    'RuntimeException',
    $errorRecord['context']['exception'],
    'Error log records the exception class'
);

$assertTrue(
    !array_key_exists('exception_message', $errorRecord['context']),
    'Error log does not expose the exception message'
);


// Verify that logger failures do not interrupt HTTP handling.
$failingLogger = new class implements LoggerInterface {
    public function log(
        string $level,
        string $message,
        array $context = []
    ): void {
        throw new RuntimeException('Logging service unavailable');
    }
};

// Test successful request when logging fails.
$safeRouter = new Router();

$safeRouter->get(
    '/safe',
    static fn(Request $request): Response => Response::json([
        'message' => 'Request completed',
    ])
);

$safeApplication = new Application($safeRouter);

$safeApplication->addMiddleware(new RequestIdMiddleware());

$safeApplication->addMiddleware(
    new RequestLoggingMiddleware($failingLogger)
);

$safeResponse = $safeApplication->handle(
    new Request('GET', '/safe')
);

$assertSame(
    200,
    $safeResponse->getStatusCode(),
    'Logger failure does not interrupt successful HTTP requests'
);

// Test application exception when error logging also fails.
$safeFailureRouter = new Router();

$safeFailureRouter->get(
    '/safe-failure',
    static function (Request $request): Response {
        throw new RuntimeException('Original application exception');
    }
);

$safeFailureApplication = new Application($safeFailureRouter);

$safeFailureApplication->addMiddleware(new RequestIdMiddleware());

$safeFailureApplication->addMiddleware(
    new RequestLoggingMiddleware($failingLogger)
);

$originalExceptionPreserved = false;

try {
    $safeFailureApplication->handle(
        new Request('GET', '/safe-failure')
    );
} catch (RuntimeException $exception) {
    $originalExceptionPreserved =
        $exception->getMessage() === 'Original application exception';
}

$assertTrue(
    $originalExceptionPreserved,
    'Logger failure does not replace the original application exception'
);

echo PHP_EOL . "All HTTP request logging tests passed." . PHP_EOL;
