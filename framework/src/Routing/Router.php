<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Routing;

use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;
use InvalidArgumentException;
use UnexpectedValueException;

class Router
{
    /**
     * Routes are grouped by path and HTTP method.
     *
     * Example:
     * [
     *     '/' => [
     *         'GET' => $handler,
     *     ],
     * ]
     */
    private array $routes = [];

    public function addRoute(
        string $method,
        string $path,
        callable $handler
    ): void {
        $method = strtoupper(trim($method));

        if ($method === '') {
            throw new InvalidArgumentException(
                'HTTP method cannot be empty.'
            );
        }

        if ($path === '' || $path[0] !== '/') {
            throw new InvalidArgumentException(
                'Route path must start with /.'
            );
        }

        $this->routes[$path][$method] = $handler;
    }

    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function hasRoute(string $method, string $path): bool
    {
        $method = strtoupper($method);

        return isset($this->routes[$path][$method]);
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // A matching method and path were found.
        if ($this->hasRoute($method, $path)) {
            $handler = $this->routes[$path][$method];

            $response = $handler($request);

            if (!$response instanceof Response) {
                throw new UnexpectedValueException(
                    'Route handlers must return a Response instance.'
                );
            }

            return $response;
        }

        // The path exists, but the requested HTTP method does not.
        $allowedMethods = array_keys($this->routes[$path] ?? []);

        if ($allowedMethods !== []) {
            sort($allowedMethods);

            return Response::json(
                [
                    'error' => 'Method Not Allowed',
                    'message' => 'The requested HTTP method is not supported for this route.',
                ],
                405,
                [
                    'Allow' => implode(', ', $allowedMethods),
                ]
            );
        }

        // Neither the path nor a matching route exists.
        return Response::json(
            [
                'error' => 'Not Found',
                'message' => 'The requested route does not exist.',
            ],
            404
        );
    }
}
