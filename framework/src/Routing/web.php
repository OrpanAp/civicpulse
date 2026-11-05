<?php

declare(strict_types=1);

use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;
use CivicPulse\Framework\Routing\Router;

return static function (Router $router): void {

    $router->get(
        '/',
        static function (Request $request): Response {
            return Response::json([
                'application' => 'CivicPulse',
                'status' => 'running',
                'message' => 'CivicPulse HTTP lifecycle is working.',
            ]);
        }
    );

    $router->get(
        '/reports',
        static function (Request $request): Response {
            return Response::json([
                'data' => [],
                'meta' => [
                    'total' => 0,
                ],
                'message' => 'Reports endpoint is ready. Database persistence is not connected yet.',
            ]);
        }
    );
};
