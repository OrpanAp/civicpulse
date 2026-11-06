<?php

declare(strict_types=1);

// Read boolean environment variables without treating "false"
// as a truthy PHP string.
$readBoolean = static function (
    string $name,
    bool $default
): bool {
    $value = getenv($name);

    if ($value === false) {
        return $default;
    }

    $parsed = filter_var(
        $value,
        FILTER_VALIDATE_BOOLEAN,
        FILTER_NULL_ON_FAILURE
    );

    if (!is_bool($parsed)) {
        throw new InvalidArgumentException(
            "Environment variable [{$name}] must be a boolean."
        );
    }

    return $parsed;
};

// Read non-negative integer environment variables.
$readInteger = static function (
    string $name,
    int $default
): int {
    $value = getenv($name);

    if ($value === false) {
        return $default;
    }

    $parsed = filter_var($value, FILTER_VALIDATE_INT);

    if (!is_int($parsed) || $parsed < 0) {
        throw new InvalidArgumentException(
            "Environment variable [{$name}] must be a non-negative integer."
        );
    }

    return $parsed;
};

// Determine whether the session cookie should use HTTPS.
$secureValue = getenv('SESSION_COOKIE_SECURE');

if ($secureValue === false) {
    $cookieSecure = null;
} else {
    $cookieSecure = match (strtolower(trim($secureValue))) {
        'auto' => null,
        'true', '1', 'yes', 'on' => true,
        'false', '0', 'no', 'off' => false,
        default => throw new InvalidArgumentException(
            'SESSION_COOKIE_SECURE must be true, false, or auto.'
        ),
    };
}

return [
    'enabled' => $readBoolean('SESSION_ENABLED', true),

    'cookie' => [
        'name' => getenv('SESSION_COOKIE_NAME') !== false
            ? getenv('SESSION_COOKIE_NAME')
            : 'CIVICPULSE_SESSION',

        'lifetime' => $readInteger(
            'SESSION_COOKIE_LIFETIME',
            0
        ),

        'path' => getenv('SESSION_COOKIE_PATH') !== false
            ? getenv('SESSION_COOKIE_PATH')
            : '/',

        'domain' => getenv('SESSION_COOKIE_DOMAIN') !== false
            ? getenv('SESSION_COOKIE_DOMAIN')
            : '',

        'secure' => $cookieSecure,

        'httponly' => $readBoolean(
            'SESSION_COOKIE_HTTPONLY',
            true
        ),

        'samesite' => getenv('SESSION_COOKIE_SAMESITE') !== false
            ? getenv('SESSION_COOKIE_SAMESITE')
            : 'Lax',
    ],

    // Framework-enforced session security policies.
    'security' => [
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'use_trans_sid' => false,
    ],
];
