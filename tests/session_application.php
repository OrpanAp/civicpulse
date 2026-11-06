<?php

declare(strict_types=1);

use CivicPulse\Framework\Application\Application;
use CivicPulse\Framework\Configuration\ConfigRepository;
use CivicPulse\Framework\Http\Middleware\SessionMiddleware;
use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;
use CivicPulse\Framework\Routing\Router;
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
    . 'civicpulse-session-application-'
    . bin2hex(random_bytes(8));

$configDirectory = $tempDirectory . DIRECTORY_SEPARATOR . 'config';
$sessionDirectory = $tempDirectory . DIRECTORY_SEPARATOR . 'sessions';

if (
    !mkdir($configDirectory, 0777, true)
    || !mkdir($sessionDirectory, 0777, true)
) {
    throw new RuntimeException(
        'Unable to create temporary application test directories.'
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
            'Application session tests require no active PHP session.'
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
            'name' => 'CIVICPULSE_APP_TEST_SESSION',
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

    // Create the real application request pipeline.
    $router = new Router();

    $routeSessionAvailable = false;
    $routeSawActiveSession = false;
    $persistedValue = null;

    $router->get(
        '/session-integration',
        static function (Request $request) use (
            &$routeSessionAvailable,
            &$routeSawActiveSession,
            &$persistedValue
        ): Response {
            $session = $request->getAttribute('session');

            $routeSessionAvailable = $session instanceof SessionInterface;
            $routeSawActiveSession = session_status() === PHP_SESSION_ACTIVE;

            if (!$session instanceof SessionInterface) {
                throw new RuntimeException(
                    'Session middleware did not attach a session to the request.'
                );
            }

            $mode = $request->getQueryParam('mode');

            if ($mode === 'write') {
                $session->set(
                    'integration_value',
                    'CivicPulse persisted'
                );

                return new Response(
                    'Session data saved.',
                    201
                );
            }

            $persistedValue = $session->get('integration_value');

            return new Response(
                'Session data recovered.',
                200
            );
        }
    );

    $application = new Application($router);

    $application->addMiddleware(
        new SessionMiddleware($sessionManager)
    );

    echo "Testing complete application session lifecycle..." . PHP_EOL;

    // 1. First request: start the session and write data.

    $firstRequest = new Request(
        'GET',
        '/session-integration?mode=write'
    );

    $firstResponse = $application->handle($firstRequest);

    $assertSame(
        true,
        $routeSessionAvailable,
        'Route receives the session through the request attribute'
    );

    $assertSame(
        true,
        $routeSawActiveSession,
        'PHP session is active while the route executes'
    );

    $assertSame(
        201,
        $firstResponse->getStatusCode(),
        'Application returns the route response status'
    );

    $assertSame(
        'Session data saved.',
        $firstResponse->getContent(),
        'Application preserves the route response content'
    );

    $sessionId = session_id();

    $assertSame(
        true,
        $sessionId !== '',
        'First application request creates a session ID'
    );

    $assertSame(
        PHP_SESSION_NONE,
        session_status(),
        'Session is closed after the first application request'
    );

    // 2. Second request: reopen the same session and recover data.

    session_id($sessionId);

    $routeSessionAvailable = false;
    $routeSawActiveSession = false;

    $secondRequest = new Request(
        'GET',
        '/session-integration?mode=read'
    );

    $secondResponse = $application->handle($secondRequest);

    $assertSame(
        true,
        $routeSessionAvailable,
        'Second route receives the session through the request attribute'
    );

    $assertSame(
        true,
        $routeSawActiveSession,
        'Session is active during the second route execution'
    );

    $assertSame(
        'CivicPulse persisted',
        $persistedValue,
        'Session data persists between application requests'
    );

    $assertSame(
        200,
        $secondResponse->getStatusCode(),
        'Second application request returns the expected status'
    );

    $assertSame(
        'Session data recovered.',
        $secondResponse->getContent(),
        'Second application request returns the expected response'
    );

    $assertSame(
        PHP_SESSION_NONE,
        session_status(),
        'Session is closed after the second application request'
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
echo "All {$passed} application session tests passed." . PHP_EOL;
