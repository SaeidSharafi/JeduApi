<?php

declare(strict_types=1);

return [
    'gateways' => [
        'ippanel' => [
            'label' => 'IPPanel',
        ],
    ],

    'notifications' => [
        'otp' => [
            'label' => 'OTP Login Code',
        ],
        'refund_completed' => [
            'label' => 'Refund Approved',
        ],
        'order_paid' => [
            'label' => 'Order Paid',
        ],
        'enrollment_ready' => [
            'label' => 'Enrollment Access Ready',
        ],
        'wallet_campaign_credited' => [
            'label' => 'Wallet Campaign Credit',
        ],
    ],

    'fields' => [
        'enabled'      => 'Enabled',
        'label'        => 'Label',
        'from'         => 'Sender Number',
        'api_key'      => 'API Key',
        'sandbox'      => 'Test Mode',
        'pattern_code' => 'Pattern Code',
    ],

    'errors' => [
        'enable_requires_api_key'     => 'An API key must be stored before the SMS gateway can be enabled.',
        'unknown_notification_option' => 'The notification option ":option" is not recognized.',
    ],
];
