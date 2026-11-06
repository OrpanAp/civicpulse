<?php

declare(strict_types=1);

use CivicPulse\Framework\Http\Contracts\RequestHandlerInterface;
use CivicPulse\Framework\Http\Middleware\CsrfMiddleware;
use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;
use CivicPulse\Framework\Security\Csrf\CsrfTokenManager;
use CivicPulse\Framework\Session\SessionInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

// In-memory session for isolated CSRF tests.
final class CsrfTestSession implements SessionInterface
{
    private array $data = [];

    public function get(
        string $key,
        mixed $default = null
    ): mixed {
        return array_key_exists($key, $this->data)
            ? $this->data[$key]
            : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function regenerateId(
        bool $deleteOldSession = true
    ): void {
        // Not required for this in-memory test.
    }
}

// Tracks whether the middleware passes a request downstream.
final class CsrfTestRequestHandler implements RequestHandlerInterface
{
    public bool $called = false;

    public function __construct(
        private Response $response
    ) {}

    public function handle(Request $request): Response
    {
        $this->called = true;

        return $this->response;
    }
}

$passed = 0;

$assertSame = static function (
    mixed $expected,
    mixed $actual,
    string $description
) use (&$passed): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $description
                . "\nExpected: " . var_export($expected, true)
                . "\nActual: " . var_export($actual, true)
        );
    }

    $passed++;

    echo "[PASS] {$description}" . PHP_EOL;
};

$manager = new CsrfTokenManager();
$middleware = new CsrfMiddleware($manager);

$makeSession = static function (): CsrfTestSession {
    return new CsrfTestSession();
};

$makeRequest = static function (
    string $method,
    array $headers = [],
    array $parsedBody = [],
    ?SessionInterface $session = null
): Request {
    $request = new Request(
        $method,
        '/csrf-test',
        $headers,
        '',
        $parsedBody
    );

    if ($session !== null) {
        $request->setAttribute('session', $session);
    }

    return $request;
};

$makeHandler = static function (): CsrfTestRequestHandler {
    return new CsrfTestRequestHandler(
        new Response('Request accepted', 200)
    );
};

echo "Testing CSRF middleware..." . PHP_EOL;

// 1. Safe HTTP methods do not require a session or token.

foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
    $request = $makeRequest($method);
    $handler = $makeHandler();

    $response = $middleware->process($request, $handler);

    $assertSame(
        200,
        $response->getStatusCode(),
        "{$method} requests pass without CSRF validation"
    );

    $assertSame(
        true,
        $handler->called,
        "{$method} requests reach the next handler"
    );
}

// 2. State-changing methods require a valid token.

foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
    $request = $makeRequest(
        $method,
        [],
        [],
        $makeSession()
    );

    $handler = $makeHandler();

    $response = $middleware->process($request, $handler);

    $assertSame(
        403,
        $response->getStatusCode(),
        "{$method} requests without a token are rejected"
    );

    $assertSame(
        false,
        $handler->called,
        "{$method} requests without a token do not reach the handler"
    );
}

// 3. A valid header token is accepted.

$headerSession = $makeSession();
$headerToken = $manager->getToken($headerSession);

$request = $makeRequest(
    'POST',
    ['X-CSRF-Token' => $headerToken],
    [],
    $headerSession
);

$handler = $makeHandler();

$response = $middleware->process($request, $handler);

$assertSame(
    200,
    $response->getStatusCode(),
    'Valid header token is accepted'
);

$assertSame(
    true,
    $handler->called,
    'Request with a valid header token reaches the handler'
);

// 4. A valid form token is accepted.

$formSession = $makeSession();
$formToken = $manager->getToken($formSession);

$request = $makeRequest(
    'POST',
    [],
    ['_token' => $formToken],
    $formSession
);

$handler = $makeHandler();

$response = $middleware->process($request, $handler);

$assertSame(
    200,
    $response->getStatusCode(),
    'Valid form token is accepted'
);

$assertSame(
    true,
    $handler->called,
    'Request with a valid form token reaches the handler'
);

// 5. Matching header and form tokens are accepted.

$matchingSession = $makeSession();
$matchingToken = $manager->getToken($matchingSession);

$request = $makeRequest(
    'POST',
    ['X-CSRF-Token' => $matchingToken],
    ['_token' => $matchingToken],
    $matchingSession
);

$handler = $makeHandler();

$response = $middleware->process($request, $handler);

$assertSame(
    200,
    $response->getStatusCode(),
    'Matching header and form tokens are accepted'
);

// 6. Conflicting header and form tokens are rejected.

$conflictingSession = $makeSession();
$validToken = $manager->getToken($conflictingSession);

$request = $makeRequest(
    'POST',
    ['X-CSRF-Token' => $validToken],
    ['_token' => str_repeat('a', 64)],
    $conflictingSession
);

$handler = $makeHandler();

$response = $middleware->process($request, $handler);

$assertSame(
    403,
    $response->getStatusCode(),
    'Conflicting header and form tokens are rejected'
);

$assertSame(
    false,
    $handler->called,
    'Conflicting tokens do not reach the handler'
);

// 7. Incorrect tokens are rejected.

$incorrectSession = $makeSession();
$manager->getToken($incorrectSession);

$request = $makeRequest(
    'POST',
    ['X-CSRF-Token' => str_repeat('a', 64)],
    [],
    $incorrectSession
);

$handler = $makeHandler();

$response = $middleware->process($request, $handler);

$assertSame(
    403,
    $response->getStatusCode(),
    'Incorrect CSRF token is rejected'
);

// 8. Non-string form tokens are rejected.

$invalidFormSession = $makeSession();
$manager->getToken($invalidFormSession);

$request = $makeRequest(
    'POST',
    [],
    ['_token' => ['invalid']],
    $invalidFormSession
);

$handler = $makeHandler();

$response = $middleware->process($request, $handler);

$assertSame(
    403,
    $response->getStatusCode(),
    'Non-string form token is rejected'
);

// 9. Missing session is rejected for state-changing requests.

$request = $makeRequest('POST');
$handler = $makeHandler();

$response = $middleware->process($request, $handler);

$assertSame(
    403,
    $response->getStatusCode(),
    'Missing session is rejected'
);

$assertSame(
    false,
    $handler->called,
    'Request without a session does not reach the handler'
);

// 10. Forbidden responses include security headers.

$assertSame(
    'no-store',
    $response->getHeaders()['Cache-Control'] ?? null,
    'Forbidden response disables caching'
);

$assertSame(
    'text/plain; charset=utf-8',
    $response->getHeaders()['Content-Type'] ?? null,
    'Forbidden response declares its content type'
);

echo PHP_EOL;
echo "All {$passed} CSRF middleware tests passed." . PHP_EOL;
