<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CivicPulse\Framework\Http\Request;

$request = new Request(
    'GET',
    '/reports?page=5&status=pending'
);

var_dump($request->getQueryParam('page'));
var_dump($request->getQueryParam('status'));
var_dump($request->getQueryParam('category', 'all'));
