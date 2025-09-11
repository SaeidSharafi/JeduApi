<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Discount Handler Discovery
    |--------------------------------------------------------------------------
    |
    | Define the namespaces and corresponding directories where the application
    | should scan for Discount Handler classes. The key is the base namespace
    | and the value is the directory relative to the app_path().
    |
    */
    'discovery_paths' => [
        'App\\Services\\Discounts\\' => 'Services/Discounts',
    ],
];
