<?php

declare(strict_types=1);

return [
    'providers' => [
        'ims' => [
            'label' => 'IMS',
        ],
    ],

    'fields' => [
        'enabled'  => 'فعال',
        'base_url' => 'آدرس سرویس',
        'api_key'  => 'کلید API',
        'timeout'  => 'مهلت (ثانیه)',
    ],

    'errors' => [
        'enable_requires_field' => 'برای فعال‌سازی این سرویس، تکمیل «:field» الزامی است.',
    ],
];
