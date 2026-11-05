<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Application;

use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;
use CivicPulse\Framework\Routing\Router;

class Application
{
    public function __construct(
        private Router $router
    ) {}

    /**
     * Handle a supplied request.
     *
     * This method is testable without starting a web server.
     */
    public function handle(Request $request): Response
    {
        return $this->router->dispatch($request);
    }

    /**
     * Handle the real HTTP request and send its response.
     */
    public function run(): void
    {
        $request = Request::createFromGlobals();

        $response = $this->handle($request);

        $response->send();
    }
}
