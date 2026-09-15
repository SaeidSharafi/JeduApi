<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Gateways
    |--------------------------------------------------------------------------
    |
    | Defaults for the admin-editable SMS gateway settings. A value is used when
    | no database setting row exists yet, so a never-saved gateway still renders
    | a complete form. `label` holds a translation key rather than a translated
    | string, so `config:cache` cannot freeze a locale into the response.
    |
    | The environment names are unchanged from the runtime `services.ippanel`
    | block, so existing deployments keep working.
    |
    */

    'gateways' => [
        'ippanel' => [
            'enabled' => (bool) env('SMS_IPPANEL_ENABLED', true),
            'label'   => 'sms.gateways.ippanel.label',
            'from'    => env('IPPANEL_FROM', '1000'),
            'api_key' => env('IPPANEL_API_KEY'),
            'sandbox' => (bool) env('IPPANEL_SANDBOX', false),
        ],
    ],

];
