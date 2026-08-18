<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Password Login Throttle
    |--------------------------------------------------------------------------
    |
    | Progressive-backoff throttling for password login, keyed per identifier
    | (global across IPs, blocking single-account stuffing) and per IP
    | (blocking distributed spray).
    |
    | Each guard defines a baseline of `max_attempts` failed attempts per
    | minute. Once the baseline is exceeded the request is rejected with 429
    | for the current lockout window. The lockout window grows with the number
    | of consecutive failures according to `tiers`: the highest tier whose
    | `failures` threshold has been reached wins. A successful login clears
    | all counters.
    |
    */

    'shop' => [
        'max_attempts' => 5,
        'tiers'        => [
            ['failures' => 5, 'decay_minutes' => 1],
            ['failures' => 10, 'decay_minutes' => 15],
            ['failures' => 15, 'decay_minutes' => 60],
        ],
        'failure_counter_ttl_seconds' => 3600,
    ],

    'staff' => [
        'max_attempts' => 3,
        'tiers'        => [
            ['failures' => 5, 'decay_minutes' => 1],
            ['failures' => 10, 'decay_minutes' => 15],
            ['failures' => 15, 'decay_minutes' => 60],
        ],
        'failure_counter_ttl_seconds' => 3600,
    ],
];
