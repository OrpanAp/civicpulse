<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Public Asset Configuration
    |--------------------------------------------------------------------------
    */

    'public_path' => dirname(__DIR__) . '/public',

    /*
    |--------------------------------------------------------------------------
    | Asset Base URL
    |--------------------------------------------------------------------------
    |
    | Leave empty to use the current application's domain.
    | Set ASSET_BASE_URL when assets are served from a subdirectory
    | or a separate asset domain.
    |
    */

    'base_url' => getenv('ASSET_BASE_URL') ?: '',
];
