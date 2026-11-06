<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Only explicitly listed proxy IP addresses or CIDR ranges are trusted.
    | Keep this list empty unless CivicPulse is actually behind a proxy.
    |
    */

    'proxies' => [],

    /*
    |--------------------------------------------------------------------------
    | Forwarded Host
    |--------------------------------------------------------------------------
    |
    | Forwarded host information is disabled by default.
    |
    */

    'trust_forwarded_host' => false,

];
