<?php

declare(strict_types=1);

use CivicPulse\Framework\Configuration\ConfigRepository;
use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Session\NativeSession;
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

$assertThrows = static function (
    callable $callback,
    string $exceptionClass,
    string $description
) use (&$passed): void {
    try {
        $callback();
    } catch (Throwable $exception) {
        if (!$exception instanceof $exceptionClass) {
            throw new RuntimeException(
                $description
                    . "\nExpected: {$exceptionClass}"
                    . "\nActual: " . $exception::class,
                0,
                $exception
            );
        }

        $passed++;
        echo "[PASS] {$description}" . PHP_EOL;

        return;
    }

    throw new RuntimeException(
        "{$description}\nExpected exception was not thrown."
    );
};

// Create isolated directories for configuration and session storage.
$tempDirectory = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'civicpulse-session-manager-'
    . bin2hex(random_bytes(8));

$configDirectory = $tempDirectory . DIRECTORY_SEPARATOR . 'config';
$sessionDirectory = $tempDirectory . DIRECTORY_SEPARATOR . 'sessions';

if (
    !mkdir($configDirectory, 0777, true)
    || !mkdir($sessionDirectory, 0777, true)
) {
    throw new RuntimeException(
        'Unable to create temporary session test directories.'
    );
}

$configFile = $configDirectory . DIRECTORY_SEPARATOR . 'session.php';

$originalSavePath = session_save_path();
$originalSessionName = session_name();

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

$originalServer = $_SERVER;

$manager = null;
$secondManager = null;
$httpsManager = null;

try {
    if (session_status() !== PHP_SESSION_NONE) {
        throw new RuntimeException(
            'Session manager tests require no active PHP session.'
        );
    }

    if (session_save_path($sessionDirectory) === false) {
        throw new RuntimeException(
            'Unable to configure temporary session storage.'
        );
    }

    ini_set('session.cache_limiter', '');

    $defaultConfiguration = [
        'enabled' => true,

        'cookie' => [
            'name' => 'CIVICPULSE_TEST_SESSION',
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

    $makeManager = static function (
        array $overrides = []
    ) use (
        $configFile,
        $configDirectory,
        $defaultConfiguration
    ): SessionManager {
        $configuration = array_replace_recursive(
            $defaultConfiguration,
            $overrides
        );

        file_put_contents(
            $configFile,
            "<?php\n\nreturn "
                . var_export($configuration, true)
                . ";\n"
        );

        $config = new ConfigRepository();
        $config->loadDirectory($configDirectory);

        return new SessionManager($config);
    };

    echo "Testing session manager..." . PHP_EOL;

    // 1. Session configuration and startup.

    $manager = $makeManager();

    $assertSame(
        true,
        $manager->isEnabled(),
        'Session manager detects enabled sessions'
    );

    $session = $manager->start(
        new Request('GET', '/session-test')
    );

    $assertSame(
        true,
        $session instanceof SessionInterface,
        'Session manager returns a session adapter'
    );

    $assertSame(
        true,
        $session instanceof NativeSession,
        'Session manager uses the native session adapter'
    );

    $assertSame(
        PHP_SESSION_ACTIVE,
        session_status(),
        'Session manager starts the PHP session'
    );

    $assertSame(
        'CIVICPULSE_TEST_SESSION',
        session_name(),
        'Session manager configures the cookie name'
    );

    $cookieParameters = session_get_cookie_params();

    $assertSame(
        0,
        $cookieParameters['lifetime'],
        'Session cookie lifetime is configured'
    );

    $assertSame(
        '/',
        $cookieParameters['path'],
        'Session cookie path is configured'
    );

    $assertSame(
        true,
        $cookieParameters['httponly'],
        'Session cookie HttpOnly is enabled'
    );

    $assertSame(
        'Lax',
        $cookieParameters['samesite'],
        'Session cookie SameSite policy is configured'
    );

    // 2. Session data and ownership.

    $session->set('test_value', 'CivicPulse works');

    $sessionId = session_id();

    $assertSame(
        true,
        $sessionId !== '',
        'PHP generates a session ID'
    );

    $otherManager = $makeManager();

    $assertThrows(
        static fn() => $otherManager->start(
            new Request('GET', '/another-session')
        ),
        LogicException::class,
        'Manager rejects a session already owned by another component'
    );

    $manager->close();

    $assertSame(
        PHP_SESSION_NONE,
        session_status(),
        'Manager closes its active session'
    );

    // Closing again must not affect unrelated session state.
    $manager->close();

    $assertSame(
        PHP_SESSION_NONE,
        session_status(),
        'Closing an already closed session is harmless'
    );

    // 3. Session persistence.

    session_id($sessionId);

    $secondManager = $makeManager();

    $secondSession = $secondManager->start(
        new Request('GET', '/session-persistence')
    );

    $assertSame(
        'CivicPulse works',
        $secondSession->get('test_value'),
        'Session data persists after closing and reopening'
    );

    $secondManager->close();

    // 4. Disabled sessions.

    $disabledManager = $makeManager([
        'enabled' => false,
    ]);

    $assertSame(
        false,
        $disabledManager->isEnabled(),
        'Session manager detects disabled sessions'
    );

    $assertSame(
        null,
        $disabledManager->start(
            new Request('GET', '/disabled-session')
        ),
        'Disabled sessions do not create a session adapter'
    );

    $assertSame(
        PHP_SESSION_NONE,
        session_status(),
        'Disabled sessions do not start PHP session handling'
    );

    // 5. HTTPS must force the Secure cookie attribute.

    $httpsManager = $makeManager([
        'cookie' => [
            'secure' => false,
        ],
    ]);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/https-session';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_HOST'] = 'localhost';

    $httpsRequest = Request::createFromGlobals();

    $httpsManager->start($httpsRequest);

    $assertSame(
        true,
        session_get_cookie_params()['secure'],
        'HTTPS forces the Secure cookie attribute'
    );

    $httpsManager->close();

    // 6. SameSite=None must require a Secure cookie.

    $invalidSameSiteManager = $makeManager([
        'cookie' => [
            'secure' => false,
            'samesite' => 'None',
        ],
    ]);

    $assertThrows(
        static fn() => $invalidSameSiteManager->start(
            new Request('GET', '/invalid-samesite')
        ),
        RuntimeException::class,
        'SameSite=None without HTTPS is rejected'
    );

    $assertSame(
        PHP_SESSION_NONE,
        session_status(),
        'Rejected session configuration does not start a session'
    );
} finally {
    // Close any session left active by a failed assertion.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    // Restore PHP session settings and server environment.
    foreach ($originalIniSettings as $setting => $value) {
        ini_set($setting, (string) $value);
    }

    session_save_path($originalSavePath);
    session_name($originalSessionName);

    $_SERVER = $originalServer;

    // Remove temporary test files and directories.
    foreach (glob($sessionDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

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
echo "All {$passed} session manager tests passed." . PHP_EOL;
