<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | OTP Waiting Time
    |--------------------------------------------------------------------------
    |
    | This option defines the number of seconds a user has to wait before
    | being allowed to request a new OTP. Set it to a reasonable value
    | to prevent abuse.
    |
    */
    'waiting_time' => 10,

    /*
    |--------------------------------------------------------------------------
    | OTP Time To Live (seconds)
    |--------------------------------------------------------------------------
    |
    | The actual validity lifetime of the generated OTP code.
    |
    */
    'ttl_seconds' => 300,

    /*
    |--------------------------------------------------------------------------
    | OTP Marker TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | Lifetime for send-marker metadata used for resend throttling and
    | expired-code detection. Should be greater than or equal to otp ttl.
    |
    */
    'marker_ttl_seconds' => 900,

    /*
    |--------------------------------------------------------------------------
    | OTP Code Range
    |--------------------------------------------------------------------------
    |
    | These options define the minimum and maximum range for the generated
    | OTP codes. Adjust these values as per your security requirements.
    |
    */
    'code_min' => 1111,
    'code_max' => 9999,

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configurations
    |--------------------------------------------------------------------------
    |
    | These options define the rate limiting configurations for the OTP
    | manager. Adjust these values as per your security requirements.
    |
    */
    'rate_limiting' => [
        'initiate' => [
            'max_attempts'  => 30,
            'decay_minutes' => 1,
        ],
        'resend' => [
            'max_attempts'  => 20,
            'decay_minutes' => 1,
        ],
        'verify' => [
            'max_attempts'  => 60,
            'decay_minutes' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification Attempt and Lockout Configurations
    |--------------------------------------------------------------------------
    |
    | These options control how many failed verification attempts are allowed
    | before otp invalidation.
    |
    */
    'max_verify_attempts' => 5,

    /*
    |--------------------------------------------------------------------------
    | Verification Attempt Window (seconds)
    |--------------------------------------------------------------------------
    |
    | Controls how long failed verification attempts are tracked per OTP key.
    |
    */
    'verify_attempt_window_seconds' => 300,

    /*
    |--------------------------------------------------------------------------
    | OTP Lock Settings (seconds)
    |--------------------------------------------------------------------------
    |
    | Distributed lock settings used to prevent resend / verify races.
    |
    */
    'lock_seconds'       => 5,
    'lock_block_seconds' => 1,
];
