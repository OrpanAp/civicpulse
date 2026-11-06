<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CivicPulse\Framework\Configuration\ConfigRepository;
use CivicPulse\Framework\Http\TrustedProxyResolver;

function proxyCheck(
    bool $condition,
    string $description
): void {
    if (!$condition) {
        throw new RuntimeException("FAILED: {$description}");
    }

    echo "[PASS] {$description}" . PHP_EOL;
}

/**
 * Build a resolver using an isolated temporary configuration.
 */
function createTestResolver(
    mixed $proxies,
    mixed $trustForwardedHost = false
): TrustedProxyResolver {
    $directory = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . 'civicpulse-proxy-'
        . bin2hex(random_bytes(6));

    if (!mkdir($directory, 0777, true)) {
        throw new RuntimeException(
            'Unable to create temporary proxy configuration directory.'
        );
    }

    try {
        $configuration = [
            'proxies' => $proxies,
            'trust_forwarded_host' => $trustForwardedHost,
        ];

        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn "
            . var_export($configuration, true)
            . ";\n";

        $file = $directory . DIRECTORY_SEPARATOR . 'trusted_proxies.php';

        if (file_put_contents($file, $contents) === false) {
            throw new RuntimeException(
                'Unable to write temporary proxy configuration.'
            );
        }

        $config = new ConfigRepository();
        $config->loadDirectory($directory);

        return new TrustedProxyResolver($config);
    } finally {
        $file = $directory . DIRECTORY_SEPARATOR . 'trusted_proxies.php';

        if (is_file($file)) {
            unlink($file);
        }

        rmdir($directory);
    }
}

/**
 * Verify that invalid configuration is rejected.
 */
function proxyConfigMustFail(
    mixed $proxies,
    string $description
): void {
    $rejected = false;

    try {
        createTestResolver($proxies);
    } catch (InvalidArgumentException) {
        $rejected = true;
    }

    proxyCheck($rejected, $description);
}

function forwardedHostConfigMustFail(
    mixed $value,
    string $description
): void {
    $rejected = false;

    try {
        createTestResolver([], $value);
    } catch (InvalidArgumentException) {
        $rejected = true;
    }

    proxyCheck($rejected, $description);
}

echo "Testing trusted proxy IP and CIDR matching..." . PHP_EOL;

// 1. Empty configuration.
$resolver = createTestResolver([]);

proxyCheck(
    !$resolver->isTrustedProxy('127.0.0.1'),
    'Empty configuration trusts no addresses'
);

// 2. Exact IPv4 address.
$resolver = createTestResolver([
    '192.168.1.10',
]);

proxyCheck(
    $resolver->isTrustedProxy('192.168.1.10'),
    'Exact IPv4 address matches'
);

proxyCheck(
    !$resolver->isTrustedProxy('192.168.1.11'),
    'Different IPv4 address does not match an exact entry'
);

// 3. IPv4 CIDR.
$resolver = createTestResolver([
    '192.168.1.0/24',
]);

proxyCheck(
    $resolver->isTrustedProxy('192.168.1.1'),
    'IPv4 CIDR matches its first usable address'
);

proxyCheck(
    $resolver->isTrustedProxy('192.168.1.254'),
    'IPv4 CIDR matches an address near its upper boundary'
);

proxyCheck(
    !$resolver->isTrustedProxy('192.168.2.1'),
    'IPv4 CIDR rejects an address outside its network'
);

// 3.1 IPv4 CIDR with a partial-byte prefix (/13).
$resolver = createTestResolver([
    '10.8.0.0/13',
]);

proxyCheck(
    $resolver->isTrustedProxy('10.8.0.1'),
    'IPv4 /13 matches its lower boundary'
);

proxyCheck(
    $resolver->isTrustedProxy('10.15.255.254'),
    'IPv4 /13 matches an address near its upper boundary'
);

proxyCheck(
    !$resolver->isTrustedProxy('10.16.0.1'),
    'IPv4 /13 rejects an address above its range'
);

proxyCheck(
    !$resolver->isTrustedProxy('10.7.255.255'),
    'IPv4 /13 rejects an address below its range'
);

// 4. Exact IPv4 /32.
$resolver = createTestResolver([
    '10.0.0.5/32',
]);

proxyCheck(
    $resolver->isTrustedProxy('10.0.0.5'),
    'IPv4 /32 matches exactly one address'
);

proxyCheck(
    !$resolver->isTrustedProxy('10.0.0.6'),
    'IPv4 /32 rejects neighboring addresses'
);

// 5. IPv4 /0.
$resolver = createTestResolver([
    '0.0.0.0/0',
]);

proxyCheck(
    $resolver->isTrustedProxy('203.0.113.25'),
    'IPv4 /0 matches every IPv4 address'
);

proxyCheck(
    !$resolver->isTrustedProxy('2001:db8::1'),
    'IPv4 network never matches an IPv6 address'
);

// 6. Exact IPv6 address.
$resolver = createTestResolver([
    '2001:db8::1',
]);

proxyCheck(
    $resolver->isTrustedProxy('2001:db8::1'),
    'Exact IPv6 address matches'
);

proxyCheck(
    !$resolver->isTrustedProxy('2001:db8::2'),
    'Different IPv6 address does not match an exact entry'
);

// 7. IPv6 CIDR.
$resolver = createTestResolver([
    '2001:db8::/32',
]);

proxyCheck(
    $resolver->isTrustedProxy('2001:db8::1'),
    'IPv6 CIDR matches an address inside its network'
);

proxyCheck(
    $resolver->isTrustedProxy('2001:db8:ffff::1'),
    'IPv6 CIDR matches an address near its upper boundary'
);

proxyCheck(
    !$resolver->isTrustedProxy('2001:db9::1'),
    'IPv6 CIDR rejects an address outside its network'
);

// 7.1 IPv6 CIDR with a partial-byte prefix (/57).
$resolver = createTestResolver([
    '2001:db8:abcd:1200::/57',
]);

proxyCheck(
    $resolver->isTrustedProxy('2001:db8:abcd:1201::1'),
    'IPv6 /57 matches an address inside its range'
);

proxyCheck(
    $resolver->isTrustedProxy('2001:db8:abcd:127f::1'),
    'IPv6 /57 matches its upper boundary'
);

proxyCheck(
    !$resolver->isTrustedProxy('2001:db8:abcd:1280::1'),
    'IPv6 /57 rejects an address above its range'
);

proxyCheck(
    !$resolver->isTrustedProxy('2001:db8:abcd:11ff::1'),
    'IPv6 /57 rejects an address below its range'
);

// 8. IPv6 /128.
$resolver = createTestResolver([
    '2001:db8::5/128',
]);

proxyCheck(
    $resolver->isTrustedProxy('2001:db8::5'),
    'IPv6 /128 matches exactly one address'
);

// 9. Invalid IP input.
$resolver = createTestResolver([
    '127.0.0.1',
]);

proxyCheck(
    !$resolver->isTrustedProxy('not-an-ip-address'),
    'Invalid IP input never matches'
);

// 10. Multiple configured networks.
$resolver = createTestResolver([
    '192.168.1.0/24',
    '2001:db8::/32',
]);

proxyCheck(
    $resolver->isTrustedProxy('192.168.1.20'),
    'Multiple configured networks match IPv4'
);

proxyCheck(
    $resolver->isTrustedProxy('2001:db8::20'),
    'Multiple configured networks match IPv6'
);

// 11. Invalid configurations.
proxyConfigMustFail(
    '192.168.1.0/24',
    'Proxy configuration must be a list'
);

proxyConfigMustFail(
    ['192.168.1.0/24' => true],
    'Associative proxy configuration is rejected'
);

proxyConfigMustFail(
    [123],
    'Non-string proxy entries are rejected'
);

proxyConfigMustFail(
    ['invalid-address'],
    'Invalid proxy IP addresses are rejected'
);

proxyConfigMustFail(
    ['192.168.1.0/33'],
    'IPv4 CIDR prefix above 32 is rejected'
);

proxyConfigMustFail(
    ['2001:db8::/129'],
    'IPv6 CIDR prefix above 128 is rejected'
);

proxyConfigMustFail(
    ['192.168.1.0/-1'],
    'Negative CIDR prefixes are rejected'
);

proxyConfigMustFail(
    ['192.168.1.0/24/extra'],
    'CIDR entries containing multiple separators are rejected'
);

proxyConfigMustFail(
    [' 192.168.1.1'],
    'Whitespace around proxy entries is rejected'
);

echo PHP_EOL . "Testing trusted forwarded client IP resolution..." . PHP_EOL;

// 1. An untrusted immediate connection must ignore X-Forwarded-For.
$resolver = createTestResolver([
    '10.0.0.0/8',
]);

proxyCheck(
    $resolver->resolveClientIp(
        '192.0.2.10',
        '203.0.113.25'
    ) === '192.0.2.10',
    'Untrusted immediate proxy cannot override the client IP'
);

// 2. A trusted immediate proxy without forwarding information.
proxyCheck(
    $resolver->resolveClientIp(
        '10.0.0.10',
        null
    ) === '10.0.0.10',
    'Trusted proxy without X-Forwarded-For uses its direct IP'
);

// 3. A single forwarded client address.
proxyCheck(
    $resolver->resolveClientIp(
        '10.0.0.10',
        '203.0.113.25'
    ) === '203.0.113.25',
    'Trusted proxy can provide a single client IP'
);

// 4. Multiple trusted proxies.
$resolver = createTestResolver([
    '10.0.0.10',
    '10.0.0.20',
    '10.0.0.30',
]);

proxyCheck(
    $resolver->resolveClientIp(
        '10.0.0.30',
        '203.0.113.25, 10.0.0.10, 10.0.0.20'
    ) === '203.0.113.25',
    'Multiple trusted proxies are skipped from right to left'
);

// 5. An untrusted proxy inside the chain.
proxyCheck(
    $resolver->resolveClientIp(
        '10.0.0.30',
        '203.0.113.25, 198.51.100.10, 10.0.0.20'
    ) === '198.51.100.10',
    'First untrusted address from the right is selected'
);

// 6. If every forwarded address is trusted, use REMOTE_ADDR.
proxyCheck(
    $resolver->resolveClientIp(
        '10.0.0.30',
        '10.0.0.10, 10.0.0.20'
    ) === '10.0.0.30',
    'All-trusted forwarded chain falls back to immediate proxy'
);

// 7. Invalid immediate connection address.
proxyCheck(
    $resolver->resolveClientIp(
        'not-an-ip',
        '203.0.113.25'
    ) === null,
    'Invalid immediate connection address returns null'
);

// 8. Invalid forwarded address rejects the complete chain.
proxyCheck(
    $resolver->resolveClientIp(
        '10.0.0.30',
        '203.0.113.25, invalid-address, 10.0.0.20'
    ) === '10.0.0.30',
    'Malformed forwarded chain falls back to immediate proxy'
);

// 9. Empty forwarded entry.
proxyCheck(
    $resolver->resolveClientIp(
        '10.0.0.30',
        '203.0.113.25,,10.0.0.20'
    ) === '10.0.0.30',
    'Empty forwarded entry rejects the complete chain'
);

// 10. Whitespace around valid addresses is allowed.
proxyCheck(
    $resolver->resolveClientIp(
        '10.0.0.30',
        ' 203.0.113.25 , 10.0.0.20 '
    ) === '203.0.113.25',
    'Surrounding spaces and tabs are trimmed'
);

// 11. An empty forwarded header.
proxyCheck(
    $resolver->resolveClientIp(
        '10.0.0.30',
        ''
    ) === '10.0.0.30',
    'Empty forwarded header uses immediate proxy'
);

$resolver = createTestResolver([
    '10.0.0.0/8',
]);

// 12. Exactly 20 forwarded addresses are accepted.
$twentyAddresses = array_merge(
    ['203.0.113.25'],
    array_fill(0, 19, '10.0.0.1')
);

proxyCheck(
    $resolver->resolveClientIp(
        '10.0.0.30',
        implode(', ', $twentyAddresses)
    ) === '203.0.113.25',
    'Forwarded chain containing 20 addresses is accepted'
);

// 13. More than 20 forwarded addresses are rejected.
$twentyOneAddresses = array_merge(
    ['203.0.113.25'],
    array_fill(0, 20, '10.0.0.1')
);

proxyCheck(
    $resolver->resolveClientIp(
        '10.0.0.30',
        implode(', ', $twentyOneAddresses)
    ) === '10.0.0.30',
    'Forwarded chain exceeding 20 addresses is rejected'
);

// 14. IPv6 forwarded chain.
$resolver = createTestResolver([
    '2001:db8:ffff::/48',
]);

proxyCheck(
    $resolver->resolveClientIp(
        '2001:db8:ffff::10',
        '2001:db8:1234::25'
    ) === '2001:db8:1234::25',
    'IPv6 forwarded client address is resolved'
);

echo PHP_EOL . "Testing trusted forwarded scheme resolution..." . PHP_EOL;

// 1. An untrusted connection cannot override the scheme.
$resolver = createTestResolver([
    '10.0.0.0/8',
]);

proxyCheck(
    $resolver->resolveScheme(
        '192.0.2.10',
        'http',
        'https'
    ) === 'http',
    'Untrusted connection cannot override the direct scheme'
);

// 2. Trusted proxy can report HTTPS.
proxyCheck(
    $resolver->resolveScheme(
        '10.0.0.10',
        'http',
        'https'
    ) === 'https',
    'Trusted proxy can provide HTTPS scheme'
);

// 3. Trusted proxy can report HTTP.
proxyCheck(
    $resolver->resolveScheme(
        '10.0.0.10',
        'https',
        'http'
    ) === 'http',
    'Trusted proxy can provide HTTP scheme'
);

// 4. Missing forwarded scheme uses the direct scheme.
proxyCheck(
    $resolver->resolveScheme(
        '10.0.0.10',
        'https',
        null
    ) === 'https',
    'Missing forwarded scheme uses the direct scheme'
);

// 5. Invalid forwarded scheme is rejected.
proxyCheck(
    $resolver->resolveScheme(
        '10.0.0.10',
        'http',
        'ftp'
    ) === 'http',
    'Unsupported forwarded scheme is rejected'
);

// 6. Comma-separated schemes are rejected.
proxyCheck(
    $resolver->resolveScheme(
        '10.0.0.10',
        'http',
        'https, http'
    ) === 'http',
    'Ambiguous comma-separated forwarded scheme is rejected'
);

// 7. Empty forwarded scheme uses the direct scheme.
proxyCheck(
    $resolver->resolveScheme(
        '10.0.0.10',
        'https',
        ''
    ) === 'https',
    'Empty forwarded scheme uses the direct scheme'
);

// 8. Scheme comparison is case-insensitive.
proxyCheck(
    $resolver->resolveScheme(
        '10.0.0.10',
        'http',
        'HTTPS'
    ) === 'https',
    'Forwarded scheme is normalized to lowercase'
);

// 9. Invalid direct scheme falls back to HTTP.
proxyCheck(
    $resolver->resolveScheme(
        '192.0.2.10',
        'ftp',
        null
    ) === 'http',
    'Unsupported direct scheme falls back to HTTP'
);

// Forwarded host configuration validation.

foreach ([null, 'true', 'false', 1, 0, [], 'yes'] as $value) {
    forwardedHostConfigMustFail(
        $value,
        'Invalid forwarded host configuration is rejected'
    );
}

$resolver = createTestResolver([], true);

proxyCheck(
    $resolver->trustsForwardedHostFrom('127.0.0.1') === false,
    'Forwarded host is not trusted when the connecting proxy is untrusted'
);

echo PHP_EOL . "All trusted proxy resolver tests passed." . PHP_EOL;
