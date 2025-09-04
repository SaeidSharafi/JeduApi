<?php

declare(strict_types=1);

return [
    'promotion' => [
        'discount' => 'تخفیف',
        'credit_from_order' => 'اعتبار کیف پول از :promotion',
        'gift_from_order' => 'اعتبار هدیه از :promotion',
    ],

    'campaign' => [
        'gift_allocated' => 'اعتبار هدیه :amount به کیف پول شما از :campaign اضافه شد',
        'bonus_processed' => 'پاداش :amount به کیف پول شما از :campaign برای :event اضافه شد',
        'manual_trigger' => 'تخصیص دستی',

        'types' => [
            'registration_bonus' => 'پاداش ثبت نام',
            'birthday_gift' => 'هدیه تولد',
            'referral_bonus' => 'پاداش معرفی',
            'welcome_gift' => 'هدیه خوش آمدگویی',
            'loyalty_reward' => 'پاداش وفاداری',
            'seasonal_bonus' => 'پاداش فصلی',
            'milestone_reward' => 'پاداش دستاورد',
            'manual_allocation' => 'تخصیص دستی',
        ],

        'descriptions' => [
            'registration_bonus' => 'پاداش اعطایی به کاربران جدید هنگام ثبت نام',
            'birthday_gift' => 'اعتبار هدیه ویژه در روز تولد کاربران',
            'referral_bonus' => 'پاداش برای معرفی موفق کاربران',
            'welcome_gift' => 'اعتبار خوش آمدگویی برای کاربران تازه وارد',
            'loyalty_reward' => 'پاداش برای مشتریان وفادار بر اساس فعالیت',
            'seasonal_bonus' => 'تبلیغات و پاداش‌های ویژه فصلی',
            'milestone_reward' => 'پاداش‌های مبتنی بر دستاوردها',
            'manual_allocation' => 'اعتبارات تخصیص یافته دستی توسط مدیران',
        ],
    ],
];
