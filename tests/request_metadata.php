<?php

declare(strict_types=1);

use CivicPulse\Framework\Configuration\ConfigRepository;
use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\TrustedProxyResolver;

require_once dirname(__DIR__) . '/vendor/autoload.php';

function requestMetadataCheck(
    bool $condition,
    string $description
): void {
    if (!$condition) {
        throw new RuntimeException("FAILED: {$description}");
    }

    echo "[PASS] {$description}" . PHP_EOL;
}

/**
 * Create a resolver using isolated temporary configuration.
 */
function createRequestMetadataResolver(
    array $proxies,
    bool $trustForwardedHost = false
): TrustedProxyResolver {
    $directory = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . 'civicpulse-request-'
        . bin2hex(random_bytes(6));

    if (!mkdir($directory, 0777, true)) {
        throw new RuntimeException(
            'Unable to create temporary configuration directory.'
        );
    }

    $file = $directory
        . DIRECTORY_SEPARATOR
        . 'trusted_proxies.php';

    try {
        $configuration = [
            'proxies' => $proxies,
            'trust_forwarded_host' => $trustForwardedHost,
        ];

        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn "
            . var_export($configuration, true)
            . ";\n";

        if (file_put_contents($file, $contents) === false) {
            throw new RuntimeException(
                'Unable to write temporary configuration.'
            );
        }

        $config = new ConfigRepository();
        $config->loadDirectory($directory);

        return new TrustedProxyResolver($config);
    } finally {
        if (is_file($file)) {
            unlink($file);
        }

        rmdir($directory);
    }
}

/**
 * Simulate a real HTTP request by temporarily replacing $_SERVER.
 */
function createRequestFromTestGlobals(
    array $server,
    ?TrustedProxyResolver $resolver = null
): Request {
    $originalServer = $_SERVER;

    try {
        $_SERVER = $server;

        return Request::createFromGlobals($resolver);
    } finally {
        $_SERVER = $originalServer;
    }
}

echo "Testing HTTP request metadata..." . PHP_EOL;

// 1. Manually constructed requests remain compatible.
$request = new Request('GET', '/reports');

requestMetadataCheck(
    $request->getClientIp() === null,
    'Manually constructed requests have no client IP by default'
);

requestMetadataCheck(
    $request->getScheme() === 'http',
    'Manually constructed requests default to HTTP'
);

requestMetadataCheck(
    $request->getHost() === null,
    'Manually constructed requests have no host by default'
);

requestMetadataCheck(
    !$request->isSecure(),
    'Manually constructed requests are not secure by default'
);

// 1.1 Conflicting duplicate forwarded headers are rejected.
$request = new Request('GET', '/', [
    'X-Forwarded-For' => '203.0.113.10',
    'x-forwarded-for' => '198.51.100.20',

    'X-Forwarded-Proto' => 'https',
    'x-forwarded-proto' => 'http',

    'X-Forwarded-Host' => 'civicpulse.example',
    'x-forwarded-host' => 'attacker.example',
]);

requestMetadataCheck(
    $request->getHeader('X-Forwarded-For') === null,
    'Conflicting duplicate X-Forwarded-For headers are rejected'
);

requestMetadataCheck(
    $request->getHeader('X-Forwarded-Proto') === null,
    'Conflicting duplicate X-Forwarded-Proto headers are rejected'
);

requestMetadataCheck(
    $request->getHeader('X-Forwarded-Host') === null,
    'Conflicting duplicate X-Forwarded-Host headers are rejected'
);

// 1.2 Identical duplicate forwarded values remain usable.
$request = new Request('GET', '/', [
    'X-Forwarded-For' => '203.0.113.10',
    'x-forwarded-for' => '203.0.113.10',
]);

requestMetadataCheck(
    $request->getHeader('X-Forwarded-For') === '203.0.113.10',
    'Identical duplicate forwarded values are accepted'
);

// 1.3 Ordinary header behavior remains unchanged.
$request = new Request('GET', '/', [
    'X-Example' => 'first',
    'x-example' => 'second',
]);

requestMetadataCheck(
    $request->getHeader('X-Example') === 'second',
    'Ordinary duplicate headers retain existing behavior'
);

// 1.4 A valid comma-separated forwarded chain remains intact.
$request = new Request('GET', '/', [
    'X-Forwarded-For' => '203.0.113.10, 10.0.0.10',
]);

requestMetadataCheck(
    $request->getHeader('X-Forwarded-For')
        === '203.0.113.10, 10.0.0.10',
    'A valid comma-separated forwarded chain is preserved'
);

// 2. Direct connection metadata.
$request = createRequestFromTestGlobals([
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/reports',
    'REMOTE_ADDR' => '198.51.100.20',
    'HTTPS' => 'off',
    'HTTP_HOST' => 'civicpulse.test:8080',
]);

requestMetadataCheck(
    $request->getClientIp() === '198.51.100.20',
    'Direct connection IP is resolved'
);

requestMetadataCheck(
    $request->getScheme() === 'http',
    'Direct HTTP connection is detected'
);

requestMetadataCheck(
    $request->getHost() === 'civicpulse.test:8080',
    'Domain host with a valid port is accepted'
);

// 3. Direct HTTPS detection.
foreach (['on', '1', 'true'] as $httpsValue) {
    $request = createRequestFromTestGlobals([
        'REMOTE_ADDR' => '198.51.100.20',
        'HTTPS' => $httpsValue,
        'HTTP_HOST' => 'example.com',
    ]);

    requestMetadataCheck(
        $request->isSecure(),
        "HTTPS value '{$httpsValue}' enables secure scheme"
    );
}

foreach (['', 'off', '0', 'yes', 'enabled', 'unexpected'] as $httpsValue) {
    $request = createRequestFromTestGlobals([
        'REMOTE_ADDR' => '198.51.100.20',
        'HTTPS' => $httpsValue,
        'HTTP_HOST' => 'example.com',
    ]);

    requestMetadataCheck(
        !$request->isSecure(),
        "HTTPS value '{$httpsValue}' does not enable secure scheme"
    );
}

// 4. Invalid remote address.
$request = createRequestFromTestGlobals([
    'REMOTE_ADDR' => 'not-an-ip',
    'HTTP_HOST' => 'example.com',
]);

requestMetadataCheck(
    $request->getClientIp() === null,
    'Invalid remote IP is rejected'
);

// 5. Untrusted proxy cannot override client IP or scheme.
$resolver = createRequestMetadataResolver([
    '10.0.0.10',
]);

$request = createRequestFromTestGlobals([
    'REMOTE_ADDR' => '198.51.100.20',
    'HTTPS' => 'off',
    'HTTP_HOST' => 'example.com',
    'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
    'HTTP_X_FORWARDED_PROTO' => 'https',
], $resolver);

requestMetadataCheck(
    $request->getClientIp() === '198.51.100.20',
    'Untrusted proxy cannot override client IP'
);

requestMetadataCheck(
    !$request->isSecure(),
    'Untrusted proxy cannot override request scheme'
);

// 6. Trusted proxy can provide client IP and scheme.
$resolver = createRequestMetadataResolver([
    '10.0.0.10',
]);

$request = createRequestFromTestGlobals([
    'REMOTE_ADDR' => '10.0.0.10',
    'HTTPS' => 'off',
    'HTTP_HOST' => 'example.com',
    'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
    'HTTP_X_FORWARDED_PROTO' => 'https',
], $resolver);

requestMetadataCheck(
    $request->getClientIp() === '203.0.113.50',
    'Trusted proxy client IP reaches Request'
);

requestMetadataCheck(
    $request->isSecure(),
    'Trusted proxy HTTPS scheme reaches Request'
);

// 7. Malformed forwarded IP falls back to the immediate proxy.
$request = createRequestFromTestGlobals([
    'REMOTE_ADDR' => '10.0.0.10',
    'HTTP_HOST' => 'example.com',
    'HTTP_X_FORWARDED_FOR' => '203.0.113.50, invalid-ip',
], $resolver);

requestMetadataCheck(
    $request->getClientIp() === '10.0.0.10',
    'Malformed forwarded IP falls back to immediate proxy'
);

// 8. Host validation.
$validHosts = [
    'example.com',
    'sub.example.com',
    'example.com:8080',
    '192.168.1.10',
    '[2001:db8::1]',
    '[2001:db8::1]:443',
];

foreach ($validHosts as $host) {
    $request = createRequestFromTestGlobals([
        'REMOTE_ADDR' => '198.51.100.20',
        'HTTP_HOST' => $host,
    ]);

    requestMetadataCheck(
        $request->getHost() === strtolower($host),
        "Valid host '{$host}' is accepted"
    );
}

$invalidHosts = [
    '',
    ' example.com',
    'example.com ',
    'example.com:80abc',
    'example.com:0',
    'example.com:65536',
    'example.com/path',
    'example.com@evil.test',
    'example.com,evil.test',
    'example.com?query=1',
    '2001:db8::1',
    '[invalid-ipv6]',
];

foreach ($invalidHosts as $host) {
    $request = createRequestFromTestGlobals([
        'REMOTE_ADDR' => '198.51.100.20',
        'HTTP_HOST' => $host,
    ]);

    requestMetadataCheck(
        $request->getHost() === null,
        "Invalid host '{$host}' is rejected"
    );
}

// 9. SERVER_NAME fallback when HTTP_HOST is absent.
$request = createRequestFromTestGlobals([
    'REMOTE_ADDR' => '198.51.100.20',
    'SERVER_NAME' => 'fallback.example.com',
]);

requestMetadataCheck(
    $request->getHost() === 'fallback.example.com',
    'SERVER_NAME is used when HTTP_HOST is absent'
);

// 10. Forwarded host is disabled by default.

$resolver = createRequestMetadataResolver([
    '10.0.0.10',
]);

$request = createRequestFromTestGlobals([
    'REMOTE_ADDR' => '10.0.0.10',
    'HTTP_HOST' => 'direct.example.com',
    'HTTP_X_FORWARDED_HOST' => 'forwarded.example.com',
], $resolver);

requestMetadataCheck(
    $request->getHost() === 'direct.example.com',
    'Forwarded host is ignored when trust is disabled'
);

// 11. Untrusted connection cannot override the host.

$resolver = createRequestMetadataResolver([
    '10.0.0.10',
], true);

$request = createRequestFromTestGlobals([
    'REMOTE_ADDR' => '198.51.100.20',
    'HTTP_HOST' => 'direct.example.com',
    'HTTP_X_FORWARDED_HOST' => 'forwarded.example.com',
], $resolver);

requestMetadataCheck(
    $request->getHost() === 'direct.example.com',
    'Untrusted connection cannot override host even when feature is enabled'
);

// 12. Trusted proxy can provide a forwarded host.

$resolver = createRequestMetadataResolver([
    '10.0.0.10',
], true);

$request = createRequestFromTestGlobals([
    'REMOTE_ADDR' => '10.0.0.10',
    'HTTP_HOST' => 'internal.example.com',
    'HTTP_X_FORWARDED_HOST' => 'public.example.com',
], $resolver);

requestMetadataCheck(
    $request->getHost() === 'public.example.com',
    'Trusted proxy forwarded host is accepted when enabled'
);

// 13. Missing forwarded host falls back to direct host.

$request = createRequestFromTestGlobals([
    'REMOTE_ADDR' => '10.0.0.10',
    'HTTP_HOST' => 'internal.example.com',
], $resolver);

requestMetadataCheck(
    $request->getHost() === 'internal.example.com',
    'Missing forwarded host falls back to direct host'
);

// 14. Invalid forwarded host falls back to direct host.

$invalidForwardedHosts = [
    'evil.example.com/path',
    'evil.example.com@attacker.test',
    'evil.example.com,attacker.test',
    'evil.example.com:65536',
    '[invalid-ipv6]',
];

foreach ($invalidForwardedHosts as $host) {
    $request = createRequestFromTestGlobals([
        'REMOTE_ADDR' => '10.0.0.10',
        'HTTP_HOST' => 'internal.example.com',
        'HTTP_X_FORWARDED_HOST' => $host,
    ], $resolver);

    requestMetadataCheck(
        $request->getHost() === 'internal.example.com',
        "Invalid forwarded host '{$host}' falls back to direct host"
    );
}

// 15. Forwarded host is normalized.

$request = createRequestFromTestGlobals([
    'REMOTE_ADDR' => '10.0.0.10',
    'HTTP_HOST' => 'internal.example.com',
    'HTTP_X_FORWARDED_HOST' => 'PUBLIC.EXAMPLE.COM:443',
], $resolver);

requestMetadataCheck(
    $request->getHost() === 'public.example.com:443',
    'Forwarded host is normalized and its valid port is preserved'
);

echo PHP_EOL . "All request metadata tests passed." . PHP_EOL;
