<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Order Increment ID Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration controls how order increment IDs are generated.
    | Supported patterns:
    |   - 'simple': Padded number (e.g., 100000001)
    |   - 'dated': Verta date + number (e.g., 14040802-000001)
    |   - 'prefixed': Custom prefix + number (e.g., ORD-100000001)
    |
    */

    'increment_id' => [
        /*
         | Pattern: 'simple', 'dated', or 'prefixed'
         */
        'pattern' => env('ORDER_INCREMENT_PATTERN', 'simple'),

        /*
         | Prefix to use when pattern is 'prefixed'
         */
        'prefix' => env('ORDER_INCREMENT_PREFIX', 'ORD-'),

        /*
         | Number of digits for zero-padding
         */
        'padding' => (int) env('ORDER_INCREMENT_PADDING', 9),

        /*
         | Starting number for the first order
         */
        'start_from' => (int) env('ORDER_INCREMENT_START', 100000001),
    ],
];
