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
    | The environment names are unchanged from the runtime `services.*` blocks,
    | so existing deployments keep working and the provider adapters keep
    | resolving the same values.
    |
    */

    'providers' => [
        'ims' => [
            'enabled'  => (bool) env('IMS_ENABLED', false),
            'base_url' => env('IMS_BASE_URL'),
            'api_key'  => env('IMS_API_KEY'),
            'timeout'  => (int) env('IMS_TIMEOUT', 15),
        ],
    ],

];
