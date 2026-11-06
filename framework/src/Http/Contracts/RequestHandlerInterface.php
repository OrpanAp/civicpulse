<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Http\Contracts;

use CivicPulse\Framework\Http\Request;
use CivicPulse\Framework\Http\Response;

interface RequestHandlerInterface
{
    public function handle(Request $request): Response;
}
