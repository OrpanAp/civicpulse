<?php

declare(strict_types=1);

use CivicPulse\Framework\Application\Application;
use CivicPulse\Framework\Configuration\ConfigRepository;
use CivicPulse\Framework\Http\Middleware\CsrfMiddleware;
use CivicPulse\Framework\Http\Middleware\SessionMiddleware;
use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;
use CivicPulse\Framework\Routing\Router;
use CivicPulse\Framework\Security\Csrf\CsrfTokenManager;
use CivicPulse\Framework\Session\SessionInterface;
use CivicPulse\Framework\Session\SessionManager;

require dirname(__DIR__) . '/vendor/autoload.php';

// Buffer output because PHP sessions must start before output is sent.
ob_start();

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

// Create isolated configuration and session storage.
$tempDirectory = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'civicpulse-csrf-application-'
    . bin2hex(random_bytes(8));

$configDirectory = $tempDirectory . DIRECTORY_SEPARATOR . 'config';
$sessionDirectory = $tempDirectory . DIRECTORY_SEPARATOR . 'sessions';

if (
    !mkdir($configDirectory, 0777, true)
    || !mkdir($sessionDirectory, 0777, true)
) {
    throw new RuntimeException(
        'Unable to create temporary CSRF test directories.'
    );
}

$configFile = $configDirectory . DIRECTORY_SEPARATOR . 'session.php';

$originalSavePath = session_save_path();
$originalSessionName = session_name();
$originalSessionId = session_id();
$originalServer = $_SERVER;

$iniSettings = [
    'session.use_strict_mode',
    'session.use_only_cookies',
    'session.use_trans_sid',
    'session.use_cookies',
    'session.cache_limiter',
];

$originalIniSettings = [];

foreach ($iniSettings as $setting) {
    $originalIniSettings[$setting] = ini_get($setting);
}

try {
    if (session_status() !== PHP_SESSION_NONE) {
        throw new RuntimeException(
            'CSRF application tests require no active PHP session.'
        );
    }

    if (session_save_path($sessionDirectory) === false) {
        throw new RuntimeException(
            'Unable to configure temporary session storage.'
        );
    }

    ini_set('session.cache_limiter', '');

    // Build isolated session configuration.
    $configuration = [
        'enabled' => true,

        'cookie' => [
            'name' => 'CIVICPULSE_CSRF_TEST_SESSION',
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => null,
            'httponly' => true,
            'samesite' => 'Lax',
        ],

        'security' => [
            'use_strict_mode' => true,
            'use_only_cookies' => true,
            'use_trans_sid' => false,
        ],
    ];

    file_put_contents(
        $configFile,
        "<?php\n\nreturn "
            . var_export($configuration, true)
            . ";\n"
    );

    $config = new ConfigRepository();
    $config->loadDirectory($configDirectory);

    $sessionManager = new SessionManager($config);
    $tokenManager = new CsrfTokenManager();

    // Create the real application request pipeline.
    $router = new Router();

    $generatedToken = null;
    $postRouteCalls = 0;
    $postRouteReceivedSession = false;

    // GET route: generate a token and store it in the session.
    $router->get(
        '/csrf-integration',
        static function (Request $request) use (
            $tokenManager,
            &$generatedToken
        ): Response {
            $session = $request->getAttribute('session');

            if (!$session instanceof SessionInterface) {
                throw new RuntimeException(
                    'GET route did not receive a session.'
                );
            }

            $generatedToken = $tokenManager->getToken($session);

            return new Response(
                'CSRF token generated.',
                200
            );
        }
    );

    // POST route: this should only execute with a valid CSRF token.
    $router->post(
        '/csrf-integration',
        static function (Request $request) use (
            &$postRouteCalls,
            &$postRouteReceivedSession
        ): Response {
            $postRouteCalls++;

            $session = $request->getAttribute('session');

            $postRouteReceivedSession =
                $session instanceof SessionInterface;

            return new Response(
                'POST request accepted.',
                200
            );
        }
    );

    $application = new Application($router);

    // Middleware order is important: session before CSRF.
    $application->addMiddleware(
        new SessionMiddleware($sessionManager)
    );

    $application->addMiddleware(
        new CsrfMiddleware($tokenManager)
    );

    echo "Testing complete application CSRF lifecycle..." . PHP_EOL;

    // 1. GET request: create a session and generate a CSRF token.

    $getResponse = $application->handle(
        new Request(
            'GET',
            '/csrf-integration'
        )
    );

    $assertSame(
        200,
        $getResponse->getStatusCode(),
        'GET request reaches the route successfully'
    );

    $assertSame(
        true,
        is_string($generatedToken),
        'GET route generates a CSRF token'
    );

    $assertSame(
        true,
        is_string($generatedToken)
            && preg_match('/\A[a-f0-9]{64}\z/', $generatedToken) === 1,
        'Generated application CSRF token has the expected format'
    );

    $sessionId = session_id();

    $assertSame(
        true,
        $sessionId !== '',
        'GET request creates a session ID'
    );

    $assertSame(
        PHP_SESSION_NONE,
        session_status(),
        'Session is closed after the GET request'
    );

    // Helper to simulate another request using the same session cookie.
    $sendPost = static function (
        ?string $token
    ) use (
        $application,
        $sessionId
    ): Response {
        // Reuse the session ID from the initial GET request.
        session_id($sessionId);

        $headers = [];

        if ($token !== null) {
            $headers['X-CSRF-Token'] = $token;
        }

        return $application->handle(
            new Request(
                'POST',
                '/csrf-integration',
                $headers
            )
        );
    };

    // 2. POST request without a token must be rejected.

    $missingTokenResponse = $sendPost(null);

    $assertSame(
        403,
        $missingTokenResponse->getStatusCode(),
        'POST request without a CSRF token is rejected'
    );

    $assertSame(
        'Forbidden',
        $missingTokenResponse->getContent(),
        'Missing-token response contains the forbidden message'
    );

    $assertSame(
        0,
        $postRouteCalls,
        'Route is not executed when the CSRF token is missing'
    );

    $assertSame(
        PHP_SESSION_NONE,
        session_status(),
        'Session is closed after a rejected request'
    );

    // 3. POST request with an incorrect token must be rejected.

    $invalidTokenResponse = $sendPost(
        str_repeat('a', 64)
    );

    $assertSame(
        403,
        $invalidTokenResponse->getStatusCode(),
        'POST request with an incorrect CSRF token is rejected'
    );

    $assertSame(
        0,
        $postRouteCalls,
        'Route is not executed when the CSRF token is invalid'
    );

    $assertSame(
        PHP_SESSION_NONE,
        session_status(),
        'Session is closed after another rejected request'
    );

    // 4. POST request with the original token must succeed.

    $validTokenResponse = $sendPost($generatedToken);

    $assertSame(
        200,
        $validTokenResponse->getStatusCode(),
        'POST request with the valid CSRF token succeeds'
    );

    $assertSame(
        'POST request accepted.',
        $validTokenResponse->getContent(),
        'Valid POST request returns the route response'
    );

    $assertSame(
        1,
        $postRouteCalls,
        'Route executes exactly once for the valid CSRF token'
    );

    $assertSame(
        true,
        $postRouteReceivedSession,
        'POST route receives the session from session middleware'
    );

    $assertSame(
        PHP_SESSION_NONE,
        session_status(),
        'Session is closed after the successful POST request'
    );
} finally {
    // Close any session left active by a failed assertion.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    // Restore PHP session configuration.
    foreach ($originalIniSettings as $setting => $value) {
        ini_set($setting, (string) $value);
    }

    session_save_path($originalSavePath);
    session_name($originalSessionName);
    session_id($originalSessionId);

    $_SERVER = $originalServer;

    // Remove temporary session files.
    foreach (
        glob($sessionDirectory . DIRECTORY_SEPARATOR . '*') ?: []
        as $file
    ) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    // Remove temporary configuration and directories.
    if (is_file($configFile)) {
        unlink($configFile);
    }

    if (is_dir($sessionDirectory)) {
        rmdir($sessionDirectory);
    }

    if (is_dir($configDirectory)) {
        rmdir($configDirectory);
    }

    if (is_dir($tempDirectory)) {
        rmdir($tempDirectory);
    }
}

$output = ob_get_clean();

echo $output;
echo PHP_EOL;
echo "All {$passed} application CSRF tests passed." . PHP_EOL;
