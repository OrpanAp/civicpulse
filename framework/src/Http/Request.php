<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Http;

class Request
{
    private string $method;
    private string $path;

    public function __construct(string $method, string $uri)
    {
        $this->method = strtoupper($method);

        $path = parse_url($uri, PHP_URL_PATH);
        $this->path = is_string($path) && $path !== '' ? $path : '/';
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public static function createFromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return new self($method, $uri);
    }
}
