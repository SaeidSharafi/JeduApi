<?php

declare(strict_types=1);

return [
    'providers' => [
        'ims' => [
            'label' => 'IMS',
        ],
        'moodle' => [
            'label' => 'Moodle',
        ],
        'spotplayer' => [
            'label' => 'SpotPlayer',
        ],
    ],

    'fields' => [
        'enabled'                       => 'Enabled',
        'base_url'                      => 'Service URL',
        'endpoint'                      => 'Service URL',
        'api_key'                       => 'API Key',
        'service_token'                 => 'Service Token',
        'login_token'                   => 'Login Token',
        'default_role_id'               => 'Default Role ID',
        'default_login_redirect_script' => 'Login Redirect Path',
        'sandbox'                       => 'Test Mode',
        'timeout'                       => 'Timeout (seconds)',
    ],

    'errors' => [
        'enable_requires_field' => 'The ":field" field must be filled before this provider can be enabled.',
    ],
];
