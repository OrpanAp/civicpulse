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
    private array $attributes = [];

    public function __construct(string $method, string $uri, array $headers = [], string $body = '')
    {
        $this->method = strtoupper($method);

        $path = parse_url($uri, PHP_URL_PATH);
        $this->path = is_string($path) && $path !== '' ? $path : '/';

        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $this->query);

        $this->headers = [];

        foreach ($headers as $name => $value) {
            $this->headers[strtolower($name)] = $value;
        }

        $this->body = $body;
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

    public static function createFromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $headers = function_exists('getallheaders')
            ? (getallheaders() ?: [])
            : [];
        $body = file_get_contents('php://input') ?: '';

        return new self($method, $uri, $headers, $body);
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
}
