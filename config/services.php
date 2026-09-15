<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'email' => [
        'use_fake_email' => env('EMAIL_USE_FAKE_EMAIL', false),
    ],

    'bbb' => [
        'enabled'                    => env('BBB_ENABLED', false),
        'base_url'                   => env('BBB_BASE_URL'),
        'secret'                     => env('BBB_SECRET'),
        'api_path'                   => env('BBB_API_PATH', '/bigbluebutton/api'),
        'default_attendee_password'  => env('BBB_DEFAULT_ATTENDEE_PASSWORD', 'ap'),
        'default_moderator_password' => env('BBB_DEFAULT_MODERATOR_PASSWORD', 'mp'),
        'timeout'                    => (int) env('BBB_TIMEOUT', 15),
    ],

];
