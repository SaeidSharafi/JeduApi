<?php

declare(strict_types=1);

return [
    'gateways' => [
        'ippanel' => [
            'label' => 'IPPanel',
        ],
    ],

    'fields' => [
        'enabled' => 'Enabled',
        'label'   => 'Label',
        'from'    => 'Sender Number',
        'api_key' => 'API Key',
        'sandbox' => 'Test Mode',
    ],

    'errors' => [
        'enable_requires_api_key' => 'An API key must be stored before the SMS gateway can be enabled.',
    ],
];
