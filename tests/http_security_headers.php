<?php

declare(strict_types=1);

use CivicPulse\Framework\Configuration\ConfigRepository;
use CivicPulse\Framework\Http\Contracts\RequestHandlerInterface;
use CivicPulse\Framework\Http\Middleware\SecurityHeadersMiddleware;
use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;

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

$assertThrows = static function (
    callable $callback,
    string $exceptionClass,
    string $description
): void {
    try {
        $callback();
    } catch (Throwable $exception) {
        if (!$exception instanceof $exceptionClass) {
            throw new RuntimeException(
                $description
                    . "\nExpected exception: {$exceptionClass}"
                    . "\nActual exception: " . $exception::class,
                0,
                $exception
            );
        }

        echo "[PASS] {$description}" . PHP_EOL;

        return;
    }

    throw new RuntimeException(
        $description . "\nExpected exception was not thrown: {$exceptionClass}"
    );
};

// Load the real application configuration.
$config = new ConfigRepository();

$config->loadDirectory(dirname(__DIR__) . '/config');

$middleware = new SecurityHeadersMiddleware($config);

// Create a handler that returns an ordinary application response.
$handler = new class implements RequestHandlerInterface {
    public function handle(Request $request): Response
    {
        return new Response(
            'Security test response',
            200,
            [
                'Content-Type' => 'text/plain',
                'X-App-Version' => '1.0',
                'x-frame-options' => 'SAMEORIGIN',
            ]
        );
    }
};

// Execute the middleware.
$response = $middleware->process(
    new Request('GET', '/security-test'),
    $handler
);

$headers = $response->getHeaders();

// Verify configured security headers.
$assertSame(
    'nosniff',
    $headers['X-Content-Type-Options'] ?? null,
    'X-Content-Type-Options is added'
);

$assertSame(
    'DENY',
    $headers['X-Frame-Options'] ?? null,
    'X-Frame-Options is added'
);

$assertSame(
    'strict-origin-when-cross-origin',
    $headers['Referrer-Policy'] ?? null,
    'Referrer-Policy is added'
);

$assertSame(
    'camera=(), microphone=(), geolocation=(self)',
    $headers['Permissions-Policy'] ?? null,
    'Permissions-Policy is added'
);

// Verify unrelated application headers are preserved.
$assertSame(
    'text/plain',
    $headers['Content-Type'] ?? null,
    'Existing Content-Type header is preserved'
);

$assertSame(
    '1.0',
    $headers['X-App-Version'] ?? null,
    'Unrelated application headers are preserved'
);

// Verify existing security headers are replaced case-insensitively.
$assertSame(
    1,
    count(
        array_filter(
            array_keys($headers),
            static fn(string $name): bool =>
            strcasecmp($name, 'X-Frame-Options') === 0
        )
    ),
    'Only one X-Frame-Options header exists'
);

$assertSame(
    'DENY',
    $headers['X-Frame-Options'],
    'Configured X-Frame-Options replaces the existing value'
);

// Verify that a response returned without calling another handler
// still receives security headers.
$shortCircuitHandler = new class implements RequestHandlerInterface {
    public function handle(Request $request): Response
    {
        return Response::json(
            ['error' => 'Access denied'],
            401
        );
    }
};

$shortCircuitResponse = $middleware->process(
    new Request('GET', '/private'),
    $shortCircuitHandler
);

$assertSame(
    401,
    $shortCircuitResponse->getStatusCode(),
    'Short-circuit response retains its original HTTP status'
);

$assertSame(
    'DENY',
    $shortCircuitResponse->getHeaders()['X-Frame-Options'] ?? null,
    'Short-circuit response receives security headers'
);

// Create an isolated invalid configuration.
$tempDirectory = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'civicpulse-security-test-'
    . bin2hex(random_bytes(8));

if (!mkdir($tempDirectory) && !is_dir($tempDirectory)) {
    throw new RuntimeException(
        'Unable to create temporary security test directory.'
    );
}

try {
    $configFile = $tempDirectory . DIRECTORY_SEPARATOR . 'security.php';

    file_put_contents(
        $configFile,
        "<?php\nreturn ['headers' => 'invalid'];\n"
    );

    $invalidConfig = new ConfigRepository();
    $invalidConfig->loadDirectory($tempDirectory);

    $invalidMiddleware = new SecurityHeadersMiddleware($invalidConfig);

    $assertThrows(
        static fn() => $invalidMiddleware->process(
            new Request('GET', '/invalid-config'),
            $handler
        ),
        RuntimeException::class,
        'Invalid security headers configuration is rejected'
    );
} finally {
    if (isset($configFile) && is_file($configFile)) {
        unlink($configFile);
    }

    if (is_dir($tempDirectory)) {
        rmdir($tempDirectory);
    }
}

echo PHP_EOL . "All HTTP security header tests passed." . PHP_EOL;
