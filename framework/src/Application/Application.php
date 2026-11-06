<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Application;

use CivicPulse\Framework\Http\Middleware\MiddlewareInterface;
use CivicPulse\Framework\Http\Middleware\MiddlewarePipeline;
use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;
use CivicPulse\Framework\Routing\Router;
use CivicPulse\Framework\Http\TrustedProxyResolver;

class Application
{
    private MiddlewarePipeline $pipeline;
    private ?TrustedProxyResolver $trustedProxyResolver;

    public function __construct(
        Router $router,
        ?TrustedProxyResolver $trustedProxyResolver = null
    ) {
        $this->pipeline = new MiddlewarePipeline($router);
        $this->trustedProxyResolver = $trustedProxyResolver;
    }

    /**
     * Register middleware for this application instance.
     */
    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->pipeline->pipe($middleware);
    }

    /**
     * Handle a supplied request through the middleware pipeline.
     */
    public function handle(Request $request): Response
    {
        return $this->pipeline->handle($request);
    }

    /**
     * Handle the real HTTP request and send its response.
     */
    public function run(): void
    {
        $request = Request::createFromGlobals(
            $this->trustedProxyResolver
        );

        $response = $this->handle($request);

        $response->send();
    }
}
