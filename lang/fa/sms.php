<?php

declare(strict_types=1);

return [
    'gateways' => [
        'ippanel' => [
            'label' => 'آی‌پی‌پنل',
        ],
    ],

    'notifications' => [
        'otp' => [
            'label' => 'کد ورود (OTP)',
        ],
        'refund_completed' => [
            'label' => 'تأیید استرداد وجه',
        ],
        'order_paid' => [
            'label' => 'پرداخت موفق سفارش',
        ],
        'enrollment_ready' => [
            'label' => 'آماده‌سازی دسترسی آموزشی',
        ],
        'wallet_campaign_credited' => [
            'label' => 'واریز هدیه/کمپین کیف پول',
        ],
    ],

    'fields' => [
        'enabled'      => 'فعال',
        'label'        => 'عنوان',
        'from'         => 'شماره فرستنده',
        'api_key'      => 'کلید API',
        'sandbox'      => 'حالت آزمایشی',
        'pattern_code' => 'کد الگو',
    ],

    'errors' => [
        'enable_requires_api_key'     => 'برای فعال‌سازی درگاه پیامک، ذخیره کلید API الزامی است.',
        'unknown_notification_option' => 'گزینه اطلاع‌رسانی پیامکی «:option» شناخته‌شده نیست.',
    ],
];
