<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Http;

class Request
{
    private string $method;
    private string $path;
    private array $query = [];
    private array $headers = [];
    private string $body;
    private array $parsedBody = [];
    private array $attributes = [];

    private ?string $clientIp = null;
    private string $scheme = 'http';
    private ?string $host = null;

    public function __construct(
        string $method,
        string $uri,
        array $headers = [],
        string $body = '',
        array $parsedBody = []
    ) {
        $this->method = strtoupper($method);

        $path = parse_url($uri, PHP_URL_PATH);
        $this->path = is_string($path) && $path !== '' ? $path : '/';

        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $this->query);

        $this->headers = [];

        $forwardedHeaderNames = [
            'x-forwarded-for',
            'x-forwarded-proto',
            'x-forwarded-host',
        ];

        $ambiguousForwardedHeaders = [];

        foreach ($headers as $name => $value) {
            $normalizedName = strtolower($name);

            // Once a forwarded header is found to be ambiguous,
            // ignore any further occurrences of that header.
            if (isset($ambiguousForwardedHeaders[$normalizedName])) {
                continue;
            }

            // Detect conflicting duplicates of security-sensitive headers.
            if (
                in_array($normalizedName, $forwardedHeaderNames, true)
                && array_key_exists($normalizedName, $this->headers)
                && $this->headers[$normalizedName] !== $value
            ) {
                unset($this->headers[$normalizedName]);

                $ambiguousForwardedHeaders[$normalizedName] = true;

                continue;
            }

            $this->headers[$normalizedName] = $value;
        }

        $this->body = $body;

        $this->parsedBody = $parsedBody;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function getQueryParam(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function getHeader(string $name): ?string
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? null;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Retrieve parsed request-body data.
     */
    public function getParsedBody(): array
    {
        return $this->parsedBody;
    }

    /**
     * Get the resolved client IP address.
     */
    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    /**
     * Get the resolved request scheme.
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * Get the validated request host.
     */
    public function getHost(): ?string
    {
        return $this->host;
    }

    /**
     * Determine whether the request uses HTTPS.
     */
    public function isSecure(): bool
    {
        return $this->scheme === 'https';
    }

    public static function createFromGlobals(
        ?TrustedProxyResolver $trustedProxyResolver = null
    ): self {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $headers = self::getHeadersFromGlobals();

        $body = file_get_contents('php://input') ?: '';

        // PHP automatically parses standard POST form submissions.
        $parsedBody = strtoupper($method) === 'POST'
            && isset($_POST)
            && is_array($_POST)
            ? $_POST
            : [];

        $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        $https = strtolower(trim((string) ($_SERVER['HTTPS'] ?? '')));

        $directScheme = in_array(
            $https,
            ['on', '1', 'true'],
            true
        ) ? 'https' : 'http';

        $request = new self(
            $method,
            $uri,
            $headers,
            $body,
            $parsedBody
        );

        // Resolve the client IP.
        if ($trustedProxyResolver !== null) {
            $request->clientIp = $trustedProxyResolver->resolveClientIp(
                $remoteAddress,
                $request->getHeader('X-Forwarded-For')
            );
        } else {
            $request->clientIp = inet_pton($remoteAddress) !== false
                ? $remoteAddress
                : null;
        }

        // Resolve the HTTP scheme.
        if ($trustedProxyResolver !== null) {
            $request->scheme = $trustedProxyResolver->resolveScheme(
                $remoteAddress,
                $directScheme,
                $request->getHeader('X-Forwarded-Proto')
            );
        } else {
            $request->scheme = $directScheme;
        }

        // Start with the direct host supplied by the web server.
        $directHost = $_SERVER['HTTP_HOST']
            ?? $_SERVER['SERVER_NAME']
            ?? null;

        $resolvedHost = self::normalizeHost($directHost);

        // Consider forwarded host information only when the immediate
        // connection comes from a trusted proxy and the feature is enabled.
        if (
            $trustedProxyResolver !== null
            && $trustedProxyResolver->trustsForwardedHostFrom($remoteAddress)
        ) {
            $forwardedHost = self::normalizeHost(
                $request->getHeader('X-Forwarded-Host')
            );

            // An invalid forwarded host must never replace the direct host.
            if ($forwardedHost !== null) {
                $resolvedHost = $forwardedHost;
            }
        }

        $request->host = $resolvedHost;

        return $request;
    }

    /**
     * Store request-specific data.
     */
    public function setAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Retrieve request-specific data.
     */
    public function getAttribute(
        string $name,
        mixed $default = null
    ): mixed {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * Collect HTTP headers from the current request.
     *
     * Uses getallheaders() when available and fills missing headers
     * from PHP's HTTP_* server variables.
     */
    private static function getHeadersFromGlobals(): array
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            $serverHeaders = getallheaders();

            if (is_array($serverHeaders)) {
                foreach ($serverHeaders as $name => $value) {
                    if (is_string($name) && is_string($value)) {
                        $headers[$name] = $value;
                    }
                }
            }
        }

        foreach ($_SERVER as $name => $value) {
            if (
                !is_string($name)
                || !str_starts_with($name, 'HTTP_')
                || !is_string($value)
            ) {
                continue;
            }

            $headerName = str_replace(
                ' ',
                '-',
                ucwords(
                    strtolower(
                        str_replace('_', ' ', substr($name, 5))
                    )
                )
            );

            $alreadyExists = false;

            foreach (array_keys($headers) as $existingName) {
                if (strcasecmp($existingName, $headerName) === 0) {
                    $alreadyExists = true;
                    break;
                }
            }

            if (!$alreadyExists) {
                $headers[$headerName] = $value;
            }
        }

        return $headers;
    }

    /**
     * Validate and normalize a host value.
     *
     * Supports domain names, IPv4, bracketed IPv6, and optional ports.
     */
    private static function normalizeHost(?string $host): ?string
    {
        if (
            $host === null
            || $host === ''
            || trim($host) !== $host
            || preg_match('/[\x00-\x20\x7F,\/\\\\@?#%]/', $host)
        ) {
            return null;
        }

        $hostname = $host;
        $port = null;

        // Bracketed IPv6 address.
        if (str_starts_with($host, '[')) {
            if (!preg_match(
                '/^\[([0-9a-fA-F:.]+)\](?::([0-9]{1,5}))?$/D',
                $host,
                $matches
            )) {
                return null;
            }

            $hostname = $matches[1];
            $port = $matches[2] ?? null;

            $packedAddress = inet_pton($hostname);

            if ($packedAddress === false || strlen($packedAddress) !== 16) {
                return null;
            }
        } else {
            // Separate an optional port. Unbracketed IPv6 is rejected.
            if (substr_count($host, ':') > 1) {
                return null;
            }

            if (str_contains($host, ':')) {
                [$hostname, $port] = explode(':', $host, 2);
            }

            $packedAddress = inet_pton($hostname);

            if ($packedAddress === false) {
                if (filter_var(
                    $hostname,
                    FILTER_VALIDATE_DOMAIN,
                    FILTER_FLAG_HOSTNAME
                ) === false) {
                    return null;
                }
            }
        }

        // Ports must be between 1 and 65535.
        if (
            $port !== null
            && (
                !ctype_digit($port)
                || (int) $port < 1
                || (int) $port > 65535
            )
        ) {
            return null;
        }

        return strtolower($host);
    }
}
