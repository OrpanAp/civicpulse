<?php

declare(strict_types=1);

use CivicPulse\Framework\Security\Csrf\CsrfTokenManager;
use CivicPulse\Framework\Session\SessionInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

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

// Create an isolated in-memory session for testing.
$makeSession = static function (): SessionInterface {
    return new class implements SessionInterface {
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
            // Session ID management is not needed for this test double.
        }
    };
};

$manager = new CsrfTokenManager();
$session = $makeSession();

echo "Testing CSRF token manager..." . PHP_EOL;

// 1. Generate a token.

$token = $manager->getToken($session);

$assertSame(
    true,
    is_string($token),
    'Generated token is a string'
);

$assertSame(
    64,
    strlen($token),
    'Generated token contains 64 hexadecimal characters'
);

$assertSame(
    1,
    preg_match('/\A[a-f0-9]{64}\z/', $token),
    'Generated token uses lowercase hexadecimal characters'
);

// 2. Verify that the token is stored in the session.

$assertSame(
    $token,
    $session->get('_civicpulse_csrf_token'),
    'Generated token is stored in the session'
);

// 3. Verify that subsequent calls reuse the same token.

$secondToken = $manager->getToken($session);

$assertSame(
    $token,
    $secondToken,
    'Existing token is reused within the same session'
);

// 4. Validate the correct token.

$assertSame(
    true,
    $manager->isTokenValid($session, $token),
    'Correct CSRF token is accepted'
);

// 5. Reject an incorrect token.

$assertSame(
    false,
    $manager->isTokenValid(
        $session,
        str_repeat('a', 64)
    ),
    'Incorrect CSRF token is rejected'
);

// 6. Reject validation when no token exists.

$emptySession = $makeSession();

$assertSame(
    false,
    $manager->isTokenValid(
        $emptySession,
        $token
    ),
    'Token validation fails when the session has no token'
);

// 7. Reject malformed stored token data.

$malformedSession = $makeSession();

$malformedSession->set(
    '_civicpulse_csrf_token',
    'invalid-token'
);

$assertSame(
    false,
    $manager->isTokenValid(
        $malformedSession,
        'invalid-token'
    ),
    'Malformed stored token is rejected'
);

// 8. Replace malformed stored data with a valid token.

$recoveredToken = $manager->getToken($malformedSession);

$assertSame(
    true,
    preg_match('/\A[a-f0-9]{64}\z/', $recoveredToken) === 1,
    'Malformed stored token is replaced with a valid token'
);

$assertSame(
    false,
    $recoveredToken === 'invalid-token',
    'Recovered token differs from malformed stored data'
);

echo PHP_EOL;
echo "All {$passed} CSRF token manager tests passed." . PHP_EOL;
