<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CivicPulse\Framework\Http\Request;

$request = Request::createFromGlobals();

echo $request->getMethod() . PHP_EOL;
echo $request->getPath() . PHP_EOL;
