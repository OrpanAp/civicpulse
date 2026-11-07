<?php

declare(strict_types=1);

use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;
use CivicPulse\Framework\Routing\Router;
use CivicPulse\Framework\View\View;

return static function (
    Router $router,
    View $view
): void {

    // Existing JSON endpoint.
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

    // New HTML page using the shared application layout.
    $router->get(
        '/home',
        static function (Request $request) use ($view): Response {
            $html = $view->render(
                'home',
                [
                    'title' => 'CivicPulse - Community Infrastructure',
                    'year' => date('Y'),
                    'heading' => 'Building Better Communities Together',
                    'description' => 'CivicPulse helps communities report and track local infrastructure problems, making it easier to identify issues and improve public services.',
                    'status' => 'Platform is running',
                ]
            );

            return Response::html($html);
        }
    );

    // Existing JSON reports endpoint.
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
