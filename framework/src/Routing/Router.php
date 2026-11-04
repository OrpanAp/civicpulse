<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Routing;

class Router
{
    // implementation of the Router class

    private static array $routes = [];

    public static function addRoute(string $method, string $path, callable $handler): void
    {
        self::$routes[$method][$path] = $handler;
    }

    public static function get(string $path, callable $handler): void
    {
        self::addRoute('GET', $path, $handler);
    }

    public static function post(string $path, callable $handler): void
    {
        self::addRoute('POST', $path, $handler);
    }

    public static function hasRoute(string $method, string $path): bool
    {
        return isset(self::$routes[strtoupper($method)][$path]);
    }

    public static function dispatch(string $method, string $path): mixed
    {
        $method = strtoupper($method);
        if (!self::hasRoute($method, $path)) {

            throw new \Exception("Route not found"); // or throw an exception for route not found
        }

        $handler = self::$routes[$method][$path];
        return $handler();
    }
}
