<?php

declare(strict_types=1);

use CivicPulse\Framework\Configuration\ConfigRepository;
use CivicPulse\Framework\Http\Contracts\RequestHandlerInterface;
use CivicPulse\Framework\Http\Middleware\SessionMiddleware;
use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;
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

// Create isolated configuration and session directories.
$tempDirectory = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'civicpulse-session-middleware-'
    . bin2hex(random_bytes(8));

$configDirectory = $tempDirectory . DIRECTORY_SEPARATOR . 'config';
$sessionDirectory = $tempDirectory . DIRECTORY_SEPARATOR . 'sessions';

if (
    !mkdir($configDirectory, 0777, true)
    || !mkdir($sessionDirectory, 0777, true)
) {
    throw new RuntimeException(
        'Unable to create temporary middleware test directories.'
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

try {
    if (session_status() !== PHP_SESSION_NONE) {
        throw new RuntimeException(
            'Session middleware tests require no active PHP session.'
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
            'name' => 'CIVICPULSE_MIDDLEWARE_TEST',
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

    $makeMiddleware = static function (
        array $overrides = []
    ) use (
        $configFile,
        $configDirectory,
        $defaultConfiguration
    ): SessionMiddleware {
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

        $manager = new SessionManager($config);

        return new SessionMiddleware($manager);
    };

    echo "Testing session middleware..." . PHP_EOL;

    // 1. Normal request: session is available downstream.

    $middleware = $makeMiddleware();
    $request = new Request('GET', '/session-test');

    $expectedResponse = new Response(
        'Session middleware response',
        200
    );

    $handler = new class(
        static function (Request $request) use (
            $assertSame,
            $expectedResponse
        ): Response {
            $session = $request->getAttribute('session');

            $assertSame(
                true,
                $session instanceof SessionInterface,
                'Session is available through the request attribute'
            );

            $session->set('user_id', 42);

            $assertSame(
                42,
                $session->get('user_id'),
                'Downstream handler can read and write session data'
            );

            return $expectedResponse;
        }
    ) implements RequestHandlerInterface {
        public function __construct(
            private Closure $callback
        ) {}

        public function handle(Request $request): Response
        {
            return ($this->callback)($request);
        }
    };

    $response = $middleware->process($request, $handler);

    $assertSame(
        $expectedResponse,
        $response,
        'Middleware returns the downstream response unchanged'
    );

    $assertSame(
        'Session middleware response',
        $response->getContent(),
        'Response content is preserved'
    );

    $assertSame(
        PHP_SESSION_NONE,
        session_status(),
        'Session is closed after normal request handling'
    );

    // 2. Disabled sessions bypass PHP session handling.

    $disabledMiddleware = $makeMiddleware([
        'enabled' => false,
    ]);

    $disabledRequest = new Request('GET', '/session-disabled');

    $disabledResponse = new Response('Sessions disabled');

    $disabledHandler = new class(
        static function (Request $request) use (
            $assertSame,
            $disabledResponse
        ): Response {
            $assertSame(
                null,
                $request->getAttribute('session'),
                'Disabled sessions are not attached to the request'
            );

            return $disabledResponse;
        }
    ) implements RequestHandlerInterface {
        public function __construct(
            private Closure $callback
        ) {}

        public function handle(Request $request): Response
        {
            return ($this->callback)($request);
        }
    };

    $actualDisabledResponse = $disabledMiddleware->process(
        $disabledRequest,
        $disabledHandler
    );

    $assertSame(
        $disabledResponse,
        $actualDisabledResponse,
        'Disabled-session request still returns its response'
    );

    $assertSame(
        PHP_SESSION_NONE,
        session_status(),
        'Disabled sessions do not start PHP session handling'
    );

    // 3. Downstream exceptions must not leave a session active.

    $exceptionMiddleware = $makeMiddleware();

    $exceptionRequest = new Request(
        'GET',
        '/session-exception'
    );

    $exceptionHandler = new class implements RequestHandlerInterface {
        public function handle(Request $request): Response
        {
            $session = $request->getAttribute('session');

            if (!$session instanceof SessionInterface) {
                throw new RuntimeException(
                    'Session was not attached before exception test.'
                );
            }

            $session->set('exception_test', true);

            throw new RuntimeException(
                'Original handler failure'
            );
        }
    };

    $caughtException = null;

    try {
        $exceptionMiddleware->process(
            $exceptionRequest,
            $exceptionHandler
        );
    } catch (Throwable $exception) {
        $caughtException = $exception;
    }

    $assertSame(
        true,
        $caughtException instanceof RuntimeException,
        'Original downstream exception is preserved'
    );

    $assertSame(
        'Original handler failure',
        $caughtException?->getMessage(),
        'Original exception message is preserved'
    );

    $assertSame(
        PHP_SESSION_NONE,
        session_status(),
        'Session is closed after a downstream exception'
    );

    echo PHP_EOL . "All {$passed} session middleware tests passed."
        . PHP_EOL;
} finally {
    // Ensure no test session remains active.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    // Restore the PHP session environment.
    session_name($originalSessionName);
    session_save_path($originalSavePath);

    foreach ($originalIniSettings as $setting => $value) {
        if ($value !== false) {
            ini_set($setting, (string) $value);
        }
    }

    $_SERVER = $originalServer;

    // Remove temporary test files.
    foreach (glob($sessionDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    if (is_file($configFile)) {
        unlink($configFile);
    }

    rmdir($sessionDirectory);
    rmdir($configDirectory);
    rmdir($tempDirectory);

    // Print test output only after PHP session cleanup.
    $output = ob_get_clean();

    echo $output;
}
