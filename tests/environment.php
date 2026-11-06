<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CivicPulse\Framework\Configuration\Environment;

$environment = new Environment();

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("FAILED: {$message}");
    }

    echo "PASSED: {$message}" . PHP_EOL;
}

echo "Testing environment configuration..." . PHP_EOL;

// 1. Basic parsing
$variables = $environment->parse(
    "APP_NAME=CivicPulse\nAPP_DEBUG=true\nDB_PASSWORD="
);

check(
    $variables['APP_NAME'] === 'CivicPulse',
    'Basic environment values'
);

check(
    $variables['APP_DEBUG'] === 'true',
    'Boolean-like values remain strings'
);

check(
    $variables['DB_PASSWORD'] === '',
    'Empty environment values'
);

// 2. Quoted values
$variables = $environment->parse(
    "APP_NAME=\"Civic Pulse Platform\"\nAPP_ENV='local environment'"
);

check(
    $variables['APP_NAME'] === 'Civic Pulse Platform',
    'Double-quoted values'
);

check(
    $variables['APP_ENV'] === 'local environment',
    'Single-quoted values'
);

// 3. Comments
$variables = $environment->parse(
    "# Application settings\nAPP_NAME=CivicPulse # Application name"
);

check(
    $variables['APP_NAME'] === 'CivicPulse',
    'Comments are ignored'
);

// 4. Escaped characters
$variables = $environment->parse(
    'APP_MESSAGE="First line\nSecond line"'
);

check(
    $variables['APP_MESSAGE'] === "First line\nSecond line",
    'Double-quoted escape sequences'
);

// 5. Existing environment variables take precedence
$testKey = 'CIVICPULSE_ENV_PRECEDENCE_TEST';

putenv("{$testKey}=system-value");

$tempFile = tempnam(sys_get_temp_dir(), 'civicpulse-env-');

if ($tempFile === false) {
    throw new RuntimeException('Unable to create temporary test file.');
}

try {
    file_put_contents(
        $tempFile,
        "{$testKey}=file-value"
    );

    $environment->load($tempFile, true);

    check(
        getenv($testKey) === 'system-value',
        'Existing environment values are preserved'
    );
} finally {
    unlink($tempFile);
    putenv($testKey);
    unset($_ENV[$testKey], $_SERVER[$testKey]);
}

// 6. Optional missing file
$environment->load(
    sys_get_temp_dir() . '/civicpulse-file-that-does-not-exist'
);

check(
    true,
    'Missing optional environment file is allowed'
);

// 7. Required missing file
$requiredFileFailed = false;

try {
    $environment->load(
        sys_get_temp_dir() . '/civicpulse-required-file-that-does-not-exist',
        true
    );
} catch (RuntimeException) {
    $requiredFileFailed = true;
}

check(
    $requiredFileFailed,
    'Missing required environment file is rejected'
);

// 8. Malformed configuration
$malformedConfigurationRejected = false;

try {
    $environment->parse("APP_NAME=CivicPulse\nINVALID LINE");
} catch (InvalidArgumentException) {
    $malformedConfigurationRejected = true;
}

check(
    $malformedConfigurationRejected,
    'Malformed environment configuration is rejected'
);

// 9. No variable interpolation
$variables = $environment->parse(
    'APP_URL="${ANOTHER_VARIABLE}"'
);

check(
    $variables['APP_URL'] === '${ANOTHER_VARIABLE}',
    'Variable interpolation is not performed'
);

echo PHP_EOL . "All environment tests passed." . PHP_EOL;
