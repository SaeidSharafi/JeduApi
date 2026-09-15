<?php

declare(strict_types=1);

return [
    'providers' => [
        'ims' => [
            'label' => 'IMS',
        ],
        'moodle' => [
            'label' => 'مودل',
        ],
        'spotplayer' => [
            'label' => 'اسپات‌پلیر',
        ],
        'skyroom' => [
            'label' => 'اسکای‌روم',
        ],
        'niliroom' => [
            'label' => 'نیلی‌روم',
        ],
    ],

    'fields' => [
        'enabled'                       => 'فعال',
        'base_url'                      => 'آدرس سرویس',
        'endpoint'                      => 'آدرس سرویس',
        'api_key'                       => 'کلید API',
        'api_token'                     => 'توکن API',
        'service_token'                 => 'توکن سرویس',
        'login_token'                   => 'توکن ورود',
        'default_role_id'               => 'شناسه نقش پیش‌فرض',
        'default_login_redirect_script' => 'مسیر بازگشت پس از ورود',
        'sandbox'                       => 'حالت آزمایشی',
        'timeout'                       => 'مهلت (ثانیه)',
    ],

    'errors' => [
        'enable_requires_field' => 'برای فعال‌سازی این سرویس، تکمیل «:field» الزامی است.',
    ],
];
