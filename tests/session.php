<?php

declare(strict_types=1);

use CivicPulse\Framework\Session\NativeSession;

require dirname(__DIR__) . '/vendor/autoload.php';

$passedTests = [];

$assertSame = static function (
    mixed $expected,
    mixed $actual,
    string $description
) use (&$passedTests): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $description
                . "\nExpected: " . var_export($expected, true)
                . "\nActual: " . var_export($actual, true)
        );
    }

    $passedTests[] = $description;
};

$assertThrows = static function (
    callable $callback,
    string $description
) use (&$passedTests): void {
    try {
        $callback();
    } catch (RuntimeException) {
        $passedTests[] = $description;

        return;
    }

    throw new RuntimeException(
        "FAILED: {$description} — expected RuntimeException."
    );
};

// Create the native session adapter.
$session = new NativeSession();

// 1. Operations must fail when no session is active.
$assertThrows(
    static fn() => $session->get('user_id'),
    'Session access is rejected when no session is active'
);

// Configure an isolated CLI session.
ini_set('session.use_cookies', '0');
ini_set('session.use_strict_mode', '0');

session_save_path(sys_get_temp_dir());

session_id(
    'cp' . bin2hex(random_bytes(8))
);

// Start the native PHP session.
if (!session_start()) {
    throw new RuntimeException(
        'Failed to start the test session.'
    );
}

try {
    // 2. Default values.
    $assertSame(
        'guest',
        $session->get('username', 'guest'),
        'Missing session key returns the supplied default'
    );

    // 3. Store and retrieve values.
    $session->set('username', 'Alex');

    $assertSame(
        'Alex',
        $session->get('username'),
        'Stored session value can be retrieved'
    );

    // 4. Check key existence.
    $assertSame(
        true,
        $session->has('username'),
        'Session has() detects an existing key'
    );

    $assertSame(
        false,
        $session->has('missing'),
        'Session has() detects a missing key'
    );

    // 5. Preserve null values.
    $session->set('nullable', null);

    $assertSame(
        true,
        $session->has('nullable'),
        'A key containing null still exists'
    );

    $assertSame(
        null,
        $session->get('nullable', 'fallback'),
        'get() preserves a stored null value'
    );

    // 6. Store structured data.
    $session->set('preferences', [
        'theme' => 'dark',
        'language' => 'en',
    ]);

    $assertSame(
        'dark',
        $session->get('preferences')['theme'],
        'Session can store and retrieve array values'
    );

    // 7. Remove a key.
    $session->remove('username');

    $assertSame(
        false,
        $session->has('username'),
        'remove() deletes the requested session key'
    );

    // 8. Regenerate the session ID.
    $oldSessionId = session_id();

    $session->regenerateId();

    $assertSame(
        true,
        session_id() !== $oldSessionId,
        'regenerateId() changes the active session ID'
    );

    // 9. Clear all session data.
    $session->clear();

    $assertSame(
        [],
        $_SESSION,
        'clear() removes all session data'
    );

    $assertSame(
        false,
        $session->has('preferences'),
        'Cleared session no longer contains previous keys'
    );
} finally {
    // Clean up the isolated test session.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    $_SESSION = [];
}

foreach ($passedTests as $description) {
    echo "[PASS] {$description}" . PHP_EOL;
}

echo PHP_EOL . "All session tests passed." . PHP_EOL;
