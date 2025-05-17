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
    'waiting_time' => 120,

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
        'max_attempts' => 5,
        'decay_minutes' => 1,
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
];
