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

    /*
    |--------------------------------------------------------------------------
    | Promotion Reservation Hold Window
    |--------------------------------------------------------------------------
    |
    | Minutes a discount usage reservation keeps counting toward a customer's
    | allowance before it goes stale. Defaults to the payment-retry window used
    | by the abandoned-order canceller (orders:cancel-abandoned --timeout=30 in
    | bootstrap/app.php). Reservations belonging to completed or refunded
    | orders are consumed permanently and never expire.
    |
    */

    'reservation_hold_minutes' => (int) env('DISCOUNT_RESERVATION_HOLD_MINUTES', 30),
];
