<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Http;

use InvalidArgumentException;
use JsonException;

class Response
{
    public function __construct(
        private string $content = '',
        private int $statusCode = 200,
        private array $headers = []
    ) {
        $this->validateStatusCode($statusCode);
        $this->validateHeaders($headers);
    }

    private function validateStatusCode(int $statusCode): void
    {
        if ($statusCode < 100 || $statusCode > 599) {
            throw new InvalidArgumentException(
                'HTTP status code must be between 100 and 599.'
            );
        }
    }

    private function validateHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                throw new InvalidArgumentException(
                    'Response header names and values must be strings.'
                );
            }

            if (
                !preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/D", $name)
                || str_contains($value, "\r")
                || str_contains($value, "\n")
            ) {
                throw new InvalidArgumentException(
                    'Invalid response header.'
                );
            }
        }
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @throws JsonException
     */
    public static function json(
        array $data,
        int $statusCode = 200,
        array $headers = []
    ): self {
        $content = json_encode(
            $data,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
        );

        return new self(
            $content,
            $statusCode,
            array_merge(
                [
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                $headers
            )
        );
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header($name . ':' . $value);
        }

        echo $this->content;
    }
}
