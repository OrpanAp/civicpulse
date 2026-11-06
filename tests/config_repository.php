<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CivicPulse\Framework\Configuration\ConfigRepository;

function configCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("FAILED: {$message}");
    }

    echo "PASSED: {$message}" . PHP_EOL;
}

echo "Testing configuration repository..." . PHP_EOL;

$directory = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'civicpulse-config-'
    . bin2hex(random_bytes(6));

if (!mkdir($directory, 0777, true)) {
    throw new RuntimeException(
        'Unable to create temporary configuration directory.'
    );
}

try {
    // Create a representative database configuration file.
    $databaseConfig = <<<'PHP'
<?php

return [
    'default' => 'mysql',

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'civicpulse',
            'username' => 'root',
            'password' => '',
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 5432,
        ],
    ],

    'optional' => null,
];
PHP;

    file_put_contents(
        $directory . DIRECTORY_SEPARATOR . 'database.php',
        $databaseConfig
    );

    file_put_contents(
        $directory . DIRECTORY_SEPARATOR . 'app.php',
        "<?php\n\nreturn ['name' => 'CivicPulse', 'debug' => true];\n"
    );

    $config = new ConfigRepository();

    $config->loadDirectory($directory);

    // 1. Top-level configuration
    configCheck(
        $config->get('app.name') === 'CivicPulse',
        'Top-level configuration access'
    );

    // 2. Nested configuration
    configCheck(
        $config->get('database.connections.mysql.driver') === 'mysql',
        'Nested configuration access'
    );

    // 3. Numeric values retain their types
    configCheck(
        $config->get('database.connections.mysql.port') === 3306,
        'Configuration values retain their original types'
    );

    // 4. Default values
    configCheck(
        $config->get('database.connections.unknown.host', 'localhost')
            === 'localhost',
        'Default values for missing configuration'
    );

    // 5. Existing keys
    configCheck(
        $config->has('database.connections.pgsql'),
        'Existing configuration keys'
    );

    // 6. Missing keys
    configCheck(
        !$config->has('database.connections.mongodb'),
        'Missing configuration keys'
    );

    // 7. Null values
    configCheck(
        $config->has('database.optional'),
        'Null-valued configuration keys still exist'
    );

    configCheck(
        $config->get('database.optional') === null,
        'Null configuration values are preserved'
    );

    // 8. Complete configuration
    $all = $config->all();

    configCheck(
        isset($all['database'], $all['app']),
        'Complete configuration array'
    );

    // 9. Missing configuration directory
    $missingDirectoryRejected = false;

    try {
        (new ConfigRepository())->loadDirectory(
            $directory . DIRECTORY_SEPARATOR . 'missing'
        );
    } catch (RuntimeException) {
        $missingDirectoryRejected = true;
    }

    configCheck(
        $missingDirectoryRejected,
        'Missing configuration directory is rejected'
    );

    // 10. Invalid configuration file return value
    file_put_contents(
        $directory . DIRECTORY_SEPARATOR . 'invalid.php',
        "<?php\nreturn 'not-an-array';\n"
    );

    $invalidConfigRejected = false;

    try {
        (new ConfigRepository())->loadDirectory($directory);
    } catch (RuntimeException) {
        $invalidConfigRejected = true;
    }

    configCheck(
        $invalidConfigRejected,
        'Configuration files must return arrays'
    );
} finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    rmdir($directory);
}

echo PHP_EOL . "All configuration repository tests passed." . PHP_EOL;
