<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | HTTP Security Headers
    |--------------------------------------------------------------------------
    |
    | These headers provide browser-level security protections.
    |
    */

    'headers' => [
        'X-Content-Type-Options' => 'nosniff',

        'X-Frame-Options' => 'DENY',

        'Referrer-Policy' => 'strict-origin-when-cross-origin',

        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(self)',
    ],
];
