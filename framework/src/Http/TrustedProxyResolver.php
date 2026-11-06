<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Http;

use CivicPulse\Framework\Configuration\ConfigRepository;
use InvalidArgumentException;

final class TrustedProxyResolver
{
    /**
     * Parsed trusted proxy networks.
     *
     * Each entry contains a packed network address and prefix length.
     *
     * @var list<array{network: string, prefix: int}>
     */
    private array $trustedNetworks = [];
    private bool $trustForwardedHost = false;
    private const MAX_FORWARDED_ADDRESSES = 20;

    public function __construct(ConfigRepository $config)
    {
        $proxies = $config->get('trusted_proxies.proxies', []);

        if (!is_array($proxies) || !array_is_list($proxies)) {
            throw new InvalidArgumentException(
                'Trusted proxies configuration must be a list.'
            );
        }

        foreach ($proxies as $proxy) {
            if (!is_string($proxy) || trim($proxy) !== $proxy) {
                throw new InvalidArgumentException(
                    'Each trusted proxy entry must be a valid IP or CIDR string.'
                );
            }

            $this->trustedNetworks[] = $this->parseNetwork($proxy);
        }

        $trustForwardedHost = $config->get(
            'trusted_proxies.trust_forwarded_host',
            false
        );

        if (!is_bool($trustForwardedHost)) {
            throw new InvalidArgumentException(
                'Trusted forwarded host configuration must be a boolean.'
            );
        }

        $this->trustForwardedHost = $trustForwardedHost;
    }

    /**
     * Determine whether an IP belongs to a trusted network.
     */
    public function isTrustedProxy(string $ip): bool
    {
        $packedIp = inet_pton($ip);

        if ($packedIp === false) {
            return false;
        }

        foreach ($this->trustedNetworks as $network) {
            // IPv4 and IPv6 addresses must never match each other.
            if (strlen($packedIp) !== strlen($network['network'])) {
                continue;
            }

            if ($this->matchesNetwork(
                $packedIp,
                $network['network'],
                $network['prefix']
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether forwarded host information may be trusted.
     *
     * Forwarded host is accepted only when explicitly enabled and
     * the immediate connecting address belongs to a trusted proxy.
     */
    public function trustsForwardedHostFrom(string $remoteAddress): bool
    {
        return $this->trustForwardedHost
            && $this->isTrustedProxy($remoteAddress);
    }

    /**
     * Resolve the client IP using a trusted proxy chain.
     *
     * The immediate connection address is always authoritative unless
     * it belongs to an explicitly configured trusted proxy.
     *
     * @param string $remoteAddress Actual connecting IP from REMOTE_ADDR.
     * @param string|null $forwardedFor X-Forwarded-For header value.
     */
    public function resolveClientIp(
        string $remoteAddress,
        ?string $forwardedFor
    ): ?string {
        // The direct connection address must itself be valid.
        if (inet_pton($remoteAddress) === false) {
            return null;
        }

        // Never trust forwarding information from an untrusted connection.
        if (!$this->isTrustedProxy($remoteAddress)) {
            return $remoteAddress;
        }

        // Without a forwarded chain, the immediate peer is all we know.
        if ($forwardedFor === null || $forwardedFor === '') {
            return $remoteAddress;
        }

        $addresses = explode(',', $forwardedFor);

        // Reject excessively long chains.
        if (
            count($addresses) > self::MAX_FORWARDED_ADDRESSES
            || count($addresses) === 0
        ) {
            return $remoteAddress;
        }

        $validatedAddresses = [];

        // Validate the complete chain before trusting any part of it.
        foreach ($addresses as $address) {
            $address = trim($address, " \t");

            if ($address === '' || inet_pton($address) === false) {
                return $remoteAddress;
            }

            $validatedAddresses[] = $address;
        }

        // Walk from the nearest forwarded address toward the client.
        for ($index = count($validatedAddresses) - 1; $index >= 0; $index--) {
            $address = $validatedAddresses[$index];

            // Skip proxies that we explicitly trust.
            if ($this->isTrustedProxy($address)) {
                continue;
            }

            // The first untrusted address is the client.
            return $address;
        }

        // No untrusted client address was found.
        return $remoteAddress;
    }

    /**
     * Resolve the request scheme using trusted proxy information.
     *
     * @param string $remoteAddress Actual connecting IP from REMOTE_ADDR.
     * @param string $directScheme Scheme detected by the application server.
     * @param string|null $forwardedProto X-Forwarded-Proto header value.
     */
    public function resolveScheme(
        string $remoteAddress,
        string $directScheme,
        ?string $forwardedProto
    ): string {
        $directScheme = strtolower(trim($directScheme));

        // Only HTTP and HTTPS are supported.
        if (!in_array($directScheme, ['http', 'https'], true)) {
            $directScheme = 'http';
        }

        // Forwarded information is ignored unless the immediate
        // connection comes from a configured trusted proxy.
        if (!$this->isTrustedProxy($remoteAddress)) {
            return $directScheme;
        }

        if ($forwardedProto === null) {
            return $directScheme;
        }

        $forwardedProto = strtolower(trim($forwardedProto));

        // Accept one unambiguous scheme only.
        if (!in_array($forwardedProto, ['http', 'https'], true)) {
            return $directScheme;
        }

        return $forwardedProto;
    }

    /**
     * Parse an IP address or CIDR range into binary form.
     *
     * Examples:
     * 192.168.1.10
     * 192.168.1.0/24
     * 2001:db8::1
     * 2001:db8::/32
     *
     * @return array{network: string, prefix: int}
     */
    private function parseNetwork(string $value): array
    {
        $parts = explode('/', $value);

        if (count($parts) > 2 || $parts[0] === '') {
            throw new InvalidArgumentException(
                "Invalid trusted proxy network: {$value}"
            );
        }

        $packedNetwork = inet_pton($parts[0]);

        if ($packedNetwork === false) {
            throw new InvalidArgumentException(
                "Invalid trusted proxy IP address: {$value}"
            );
        }

        $maximumPrefix = strlen($packedNetwork) * 8;

        // A plain IP address represents one exact address.
        if (count($parts) === 1) {
            return [
                'network' => $packedNetwork,
                'prefix' => $maximumPrefix,
            ];
        }

        // CIDR prefixes must contain decimal digits only.
        if (!preg_match('/^(0|[1-9][0-9]*)$/D', $parts[1])) {
            throw new InvalidArgumentException(
                "Invalid trusted proxy CIDR prefix: {$value}"
            );
        }

        $prefix = (int) $parts[1];

        if ($prefix > $maximumPrefix) {
            throw new InvalidArgumentException(
                "Trusted proxy CIDR prefix is out of range: {$value}"
            );
        }

        return [
            'network' => $packedNetwork,
            'prefix' => $prefix,
        ];
    }

    /**
     * Compare an IP address against a parsed network.
     */
    private function matchesNetwork(
        string $ip,
        string $network,
        int $prefix
    ): bool {
        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        // Compare all complete prefix bytes.
        if (
            $fullBytes > 0
            && substr($ip, 0, $fullBytes)
            !== substr($network, 0, $fullBytes)
        ) {
            return false;
        }

        // No partial byte remains, or the prefix is /0.
        if ($remainingBits === 0) {
            return true;
        }

        // Compare only the significant bits in the next byte.
        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        $ipByte = ord($ip[$fullBytes]);
        $networkByte = ord($network[$fullBytes]);

        return ($ipByte & $mask) === ($networkByte & $mask);
    }
}
