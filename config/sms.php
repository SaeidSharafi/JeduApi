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
    | This block is also the send path's fallback: `IpPanelSmsService` merges the
    | stored setting over it, so a value saved in the admin panel overrides
    | these defaults on the next send without a deployment.
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

    /*
    |--------------------------------------------------------------------------
    | SMS Notification Options
    |--------------------------------------------------------------------------
    |
    | Defaults for the admin-editable transactional notification options. A
    | stored `SettingKeyEnum::SMS_NOTIFICATIONS` row wins per option field, so a
    | never-saved option still resolves a complete `enabled` / `pattern_code`
    | pair from here. `otp` and the planned options carry a pattern;
    | `refund_completed` is sent as free text.
    |
    */

    'notifications' => [
        'otp' => [
            'enabled'      => (bool) env('SMS_OTP_ENABLED', true),
            'pattern_code' => (string) env('SMS_OTP_PATTERN', 'mdoe1j1587'),
        ],
        'refund_completed' => [
            'enabled'      => (bool) env('SMS_REFUND_ENABLED', true),
            'pattern_code' => (string) env('SMS_REFUND_PATTERN', ''),
        ],
        'order_paid' => [
            'enabled'      => (bool) env('SMS_ORDER_PAID_ENABLED', false),
            'pattern_code' => (string) env('SMS_ORDER_PAID_PATTERN', ''),
        ],
        'enrollment_ready' => [
            'enabled'      => (bool) env('SMS_ENROLLMENT_READY_ENABLED', false),
            'pattern_code' => (string) env('SMS_ENROLLMENT_READY_PATTERN', ''),
        ],
        'wallet_campaign_credited' => [
            'enabled'      => (bool) env('SMS_WALLET_CREDITED_ENABLED', false),
            'pattern_code' => (string) env('SMS_WALLET_CREDITED_PATTERN', ''),
        ],
    ],

];
