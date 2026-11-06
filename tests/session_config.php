<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$configFile = dirname(__DIR__) . '/config/session.php';

$environmentKeys = [
    'SESSION_ENABLED',
    'SESSION_COOKIE_NAME',
    'SESSION_COOKIE_LIFETIME',
    'SESSION_COOKIE_PATH',
    'SESSION_COOKIE_DOMAIN',
    'SESSION_COOKIE_SECURE',
    'SESSION_COOKIE_HTTPONLY',
    'SESSION_COOKIE_SAMESITE',
];

$passed = 0;

function sessionConfigCheck(bool $condition, string $message): void
{
    global $passed;

    if (!$condition) {
        throw new RuntimeException("FAILED: {$message}");
    }

    $passed++;
    echo "[PASS] {$message}" . PHP_EOL;
}

function sessionConfigClearEnvironment(): void
{
    global $environmentKeys;

    foreach ($environmentKeys as $key) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}

function sessionConfigSetEnvironment(array $values): void
{
    foreach ($values as $key => $value) {
        putenv("{$key}={$value}");
    }
}

function sessionConfigLoad(): array
{
    global $configFile;

    return require $configFile;
}

function sessionConfigExpectInvalid(
    array $environment,
    string $message
): void {
    sessionConfigClearEnvironment();
    sessionConfigSetEnvironment($environment);

    try {
        sessionConfigLoad();
    } catch (InvalidArgumentException) {
        sessionConfigCheck(true, $message);
        return;
    }

    sessionConfigCheck(false, $message);
}

echo "Testing session configuration..." . PHP_EOL;

try {
    // 1. Default configuration.
    sessionConfigClearEnvironment();

    $config = sessionConfigLoad();

    sessionConfigCheck(
        $config['enabled'] === true,
        'Sessions are enabled by default'
    );

    sessionConfigCheck(
        $config['cookie']['name'] === 'CIVICPULSE_SESSION',
        'Default session cookie name'
    );

    sessionConfigCheck(
        $config['cookie']['lifetime'] === 0,
        'Default session cookie lifetime'
    );

    sessionConfigCheck(
        $config['cookie']['path'] === '/',
        'Default session cookie path'
    );

    sessionConfigCheck(
        $config['cookie']['domain'] === '',
        'Default session cookie domain'
    );

    sessionConfigCheck(
        $config['cookie']['secure'] === null,
        'Secure cookie defaults to automatic mode'
    );

    sessionConfigCheck(
        $config['cookie']['httponly'] === true,
        'HttpOnly is enabled by default'
    );

    sessionConfigCheck(
        $config['cookie']['samesite'] === 'Lax',
        'Default SameSite policy'
    );

    // 2. Environment overrides.
    sessionConfigClearEnvironment();

    sessionConfigSetEnvironment([
        'SESSION_ENABLED' => 'false',
        'SESSION_COOKIE_NAME' => 'CUSTOM_SESSION',
        'SESSION_COOKIE_LIFETIME' => '3600',
        'SESSION_COOKIE_PATH' => '/app',
        'SESSION_COOKIE_DOMAIN' => 'example.test',
        'SESSION_COOKIE_SECURE' => 'true',
        'SESSION_COOKIE_HTTPONLY' => 'false',
        'SESSION_COOKIE_SAMESITE' => 'Strict',
    ]);

    $config = sessionConfigLoad();

    sessionConfigCheck(
        $config['enabled'] === false,
        'Environment can disable sessions'
    );

    sessionConfigCheck(
        $config['cookie']['name'] === 'CUSTOM_SESSION',
        'Environment overrides cookie name'
    );

    sessionConfigCheck(
        $config['cookie']['lifetime'] === 3600,
        'Environment lifetime is converted to integer'
    );

    sessionConfigCheck(
        $config['cookie']['path'] === '/app',
        'Environment overrides cookie path'
    );

    sessionConfigCheck(
        $config['cookie']['domain'] === 'example.test',
        'Environment overrides cookie domain'
    );

    sessionConfigCheck(
        $config['cookie']['secure'] === true,
        'Secure cookie accepts true'
    );

    sessionConfigCheck(
        $config['cookie']['httponly'] === false,
        'HttpOnly accepts false'
    );

    sessionConfigCheck(
        $config['cookie']['samesite'] === 'Strict',
        'Environment overrides SameSite policy'
    );

    // 3. Automatic and explicit secure-cookie modes.
    sessionConfigClearEnvironment();
    putenv('SESSION_COOKIE_SECURE=auto');

    $config = sessionConfigLoad();

    sessionConfigCheck(
        $config['cookie']['secure'] === null,
        'Secure cookie accepts auto mode'
    );

    sessionConfigClearEnvironment();
    putenv('SESSION_COOKIE_SECURE=false');

    $config = sessionConfigLoad();

    sessionConfigCheck(
        $config['cookie']['secure'] === false,
        'Secure cookie accepts false'
    );

    // 4. Framework-enforced security settings.
    sessionConfigClearEnvironment();

    $config = sessionConfigLoad();

    sessionConfigCheck(
        $config['security']['use_strict_mode'] === true
            && $config['security']['use_only_cookies'] === true
            && $config['security']['use_trans_sid'] === false,
        'Framework session security policies remain enabled'
    );

    // 5. Invalid environment values.
    sessionConfigExpectInvalid(
        ['SESSION_ENABLED' => 'sometimes'],
        'Invalid session enabled value is rejected'
    );

    sessionConfigExpectInvalid(
        ['SESSION_COOKIE_HTTPONLY' => 'sometimes'],
        'Invalid HttpOnly value is rejected'
    );

    sessionConfigExpectInvalid(
        ['SESSION_COOKIE_LIFETIME' => '-1'],
        'Negative cookie lifetime is rejected'
    );

    sessionConfigExpectInvalid(
        ['SESSION_COOKIE_LIFETIME' => 'abc'],
        'Non-numeric cookie lifetime is rejected'
    );

    sessionConfigExpectInvalid(
        ['SESSION_COOKIE_SECURE' => 'sometimes'],
        'Invalid secure-cookie value is rejected'
    );
} finally {
    sessionConfigClearEnvironment();
}

echo PHP_EOL;
echo "All {$passed} session configuration tests passed." . PHP_EOL;
