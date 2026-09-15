<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Provisioning Providers
    |--------------------------------------------------------------------------
    |
    | Defaults for the admin-editable provisioning provider settings. A value is
    | used when no database setting row exists yet, so a never-saved provider
    | still renders a complete form. A stored setting wins field by field.
    |
    | The environment names mirror the runtime `services.*` blocks wherever an
    | adapter reads them, so existing deployments keep working and the provider
    | adapters keep resolving the same values. Niliroom's names are new because
    | it is settings-only until its adapter lands.
    |
    */

    'providers' => [
        'ims' => [
            'enabled'  => (bool) env('IMS_ENABLED', false),
            'base_url' => env('IMS_BASE_URL'),
            'api_key'  => env('IMS_API_KEY'),
            'timeout'  => (int) env('IMS_TIMEOUT', 15),
        ],

        'moodle' => [
            'enabled'                       => (bool) env('MOODLE_ENABLED', false),
            'base_url'                      => env('MOODLE_BASE_URL'),
            'token'                         => env('MOODLE_TOKEN'),
            'auth_userkey_token'            => env('MOODLE_AUTH_USERKEY_TOKEN'),
            'default_role_id'               => (int) env('MOODLE_DEFAULT_ROLE_ID', 5),
            'default_login_redirect_script' => env('MOODLE_LOGIN_REDIRECT_SCRIPT', '/my/'),
            'timeout'                       => (int) env('MOODLE_TIMEOUT', 15),
        ],

        'spotplayer' => [
            'enabled'  => (bool) env('SPOTPLAYER_ENABLED', false),
            'endpoint' => env('SPOTPLAYER_ENDPOINT', 'https://panel.spotplayer.ir/license/edit/'),
            'api_key'  => env('SPOTPLAYER_API_KEY'),
            'sandbox'  => (bool) env('SPOTPLAYER_SANDBOX', false),
            'timeout'  => (int) env('SPOTPLAYER_TIMEOUT', 15),
        ],

        'skyroom' => [
            'enabled'  => (bool) env('SKYROOM_ENABLED', false),
            'base_url' => env('SKYROOM_BASE_URL', 'https://www.skyroom.online/skyroom/api'),
            'api_key'  => env('SKYROOM_API_KEY'),
        ],

        'niliroom' => [
            'enabled'   => (bool) env('NILIROOM_ENABLED', false),
            'base_url'  => env('NILIROOM_BASE_URL'),
            'api_token' => env('NILIROOM_API_TOKEN'),
        ],
    ],

];
