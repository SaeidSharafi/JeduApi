<?php

declare(strict_types=1);

return [
    'providers' => [
        'ims' => [
            'label' => 'IMS',
        ],
    ],

    'fields' => [
        'enabled'  => 'Enabled',
        'base_url' => 'Service URL',
        'api_key'  => 'API Key',
        'timeout'  => 'Timeout (seconds)',
    ],

    'errors' => [
        'enable_requires_field' => 'The ":field" field must be filled before this provider can be enabled.',
    ],
];
