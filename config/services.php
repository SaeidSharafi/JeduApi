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

    'ippanel' => [
        'api_key'  => env('IPPANEL_API_KEY'),
        'from'     => env('IPPANEL_FROM', '1000'),
        'sand_box' => env('IPPANEL_SANDBOX', false),
    ],

    'email' => [
        'use_fake_email' => env('EMAIL_USE_FAKE_EMAIL', false),
    ],

    'ims' => [
        'base_url' => env('IMS_BASE_URL'),
        'api_key'  => env('IMS_API_KEY'),
        'timeout'  => (int) env('IMS_TIMEOUT', 15),
    ],

    'moodle' => [
        'base_url'                      => env('MOODLE_BASE_URL'),
        'token'                         => env('MOODLE_TOKEN'),
        'auth_userkey_token'            => env('MOODLE_AUTH_USERKEY_TOKEN'),
        'default_role_id'               => (int) env('MOODLE_DEFAULT_ROLE_ID', 5),
        'default_login_redirect_script' => env('MOODLE_LOGIN_REDIRECT_SCRIPT', '/my/'),
        'timeout'                       => (int) env('MOODLE_TIMEOUT', 15),
    ],

    'spotplayer' => [
        'endpoint' => env('SPOTPLAYER_ENDPOINT', 'https://panel.spotplayer.ir/license/edit/'),
        'api_key'  => env('SPOTPLAYER_API_KEY'),
        'sandbox'  => (bool) env('SPOTPLAYER_SANDBOX', false),
        'timeout'  => (int) env('SPOTPLAYER_TIMEOUT', 15),
    ],

    'bbb' => [
        'base_url'                   => env('BBB_BASE_URL'),
        'secret'                     => env('BBB_SECRET'),
        'api_path'                   => env('BBB_API_PATH', '/bigbluebutton/api'),
        'default_attendee_password'  => env('BBB_DEFAULT_ATTENDEE_PASSWORD', 'ap'),
        'default_moderator_password' => env('BBB_DEFAULT_MODERATOR_PASSWORD', 'mp'),
        'timeout'                    => (int) env('BBB_TIMEOUT', 15),
    ],

];
