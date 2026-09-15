<?php

declare(strict_types=1);

return [
    'gateways' => [
        'ippanel' => [
            'label' => 'آی‌پی‌پنل',
        ],
    ],

    'fields' => [
        'enabled' => 'فعال',
        'label'   => 'عنوان',
        'from'    => 'شماره فرستنده',
        'api_key' => 'کلید API',
        'sandbox' => 'حالت آزمایشی',
    ],

    'errors' => [
        'enable_requires_api_key' => 'برای فعال‌سازی درگاه پیامک، ذخیره کلید API الزامی است.',
    ],
];
