<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Http\Middleware;

use CivicPulse\Framework\Http\Contracts\RequestHandlerInterface;
use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;

final class MiddlewarePipeline implements RequestHandlerInterface
{
    /**
     * @var list<MiddlewareInterface>
     */
    private array $middlewares = [];

    public function __construct(
        private RequestHandlerInterface $destination
    ) {}

    /**
     * Register middleware in execution order.
     */
    public function pipe(MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Execute the middleware stack and then the destination handler.
     */
    public function handle(Request $request): Response
    {
        $handler = $this->destination;

        // Wrap the destination in reverse registration order.
        foreach (array_reverse($this->middlewares) as $middleware) {
            $handler = new MiddlewareRequestHandler(
                $middleware,
                $handler
            );
        }

        return $handler->handle($request);
    }
}
