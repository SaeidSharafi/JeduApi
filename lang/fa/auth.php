<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed'   => 'اطلاعات وارد شده غلط میباشد.',
    'password' => 'رمز عبور ارائه شده نادرست است.',
    'throttle' => 'تعداد تلاش های ناموفق زیاد بود . لطفا بعد از :seconds ثانیه ی دیگر تلاش کنید .',

    'permission' => [
        'resource' => [
            'advice_requests'          => 'درخواست‌های مشاوره',
            'audits'                   => 'گزارش‌های حسابرسی',
            'blog_categories'          => 'دسته‌بندی‌های وبلاگ',
            'blog_posts'               => 'نوشته‌های وبلاگ',
            'categories'               => 'دسته‌بندی‌ها',
            'collaboration_requests'   => 'درخواست‌های همکاری',
            'contact_us_requests'      => 'درخواست‌های تماس با ما',
            'courses'                  => 'دوره‌ها',
            'discounts'                => 'تخفیف‌ها',
            'enrollments'              => 'ثبت‌نام‌ها',
            'files'                    => 'فایل‌ها',
            'home_page_blocks'         => 'بلوک‌های صفحه اصلی',
            'orders'                   => 'سفارش‌ها',
            'partners'                 => 'شرکا',
            'payments'                 => 'پرداخت‌ها',
            'product_delivery_options' => 'گزینه‌های تحویل محصول',
            'products'                 => 'محصولات',
            'refunds'                  => 'بازپرداخت‌ها',
            'reviews'                  => 'دیدگاه‌ها',
            'roles'                    => 'نقش‌ها',
            'seminars'                 => 'سمینارها',
            'settings'                 => 'تنظیمات',
            'sliders'                  => 'اسلایدرها',
            'student_stories'          => 'داستان‌های دانشجویان',
            'teachers'                 => 'استادان',
            'terms'                    => 'ترم‌ها',
            'users'                    => 'کاربران',
            'vendors'                  => 'تأمین‌کنندگان',
            'wallet_campaigns'         => 'کمپین‌های کیف پول',
            'wallets'                  => 'کیف پول‌ها',
            'course'                   => 'دوره‌ها',
            'seminar'                  => 'سمینارها',
            'digital_asset'            => 'فایل‌ها',
            'user'                     => 'کاربران',
            'role'                     => 'نقش ها',
            'media'                    => 'رسانه‌ها',
            'file'                     => 'فایل‌های خصوصی',
            'category'                 => 'دسته‌بندی‌ها',
            'staff'                    => 'کارکنان',
        ],
        'action' => [
            'view_any'     => 'مشاهده همه',
            'view'         => 'مشاهده',
            'view_scoped'  => 'مشاهده با دسترسی محدود',
            'view_own'     => 'مشاهده خود',
            'create'       => 'ایجاد',
            'update'       => 'ویرایش',
            'update_own'   => 'ویرایش خود',
            'delete'       => 'حذف',
            'delete_own'   => 'حذف خود',
            'restore'      => 'بازیابی',
            'force_delete' => 'حذف دائمی',
        ],
        'custom' => [
            'audits' => [
                'admin_actions_view'       => 'مشاهده اقدام‌های مدیر',
                'compliance_reports_view'  => 'مشاهده گزارش‌های انطباق',
                'suspicious_activity_view' => 'مشاهده فعالیت‌های مشکوک',
            ],
            'blog_posts' => [
                'publish' => 'انتشار',
                'feature' => 'ویژه‌سازی',
            ],
            'enrollments' => [
                'diagnostics_view' => 'مشاهده گزارش فنی / دیاگنوستیک',
                'retry_provision'  => 'تلاش مجدد برای آماده‌سازی',
                'waive_provision'  => 'صرف‌نظر از این سرویس',
            ],
            'orders' => [
                'approve' => 'تأیید',
            ],
            'refunds' => [
                'skip_gateway'  => 'رد کردن درگاه',
                'update_status' => 'ویرایش وضعیت',
            ],
            'reviews' => [
                'update_featured_status' => 'ویرایش وضعیت ویژه',
            ],
            'settings' => [
                'payment_update' => 'ویرایش تنظیمات پرداخت',
                'payment_view'   => 'مشاهده تنظیمات پرداخت',
            ],
            'staff' => [
                'ban'          => 'مسدود کردن کارشناس',
                'manage_roles' => 'مدیریت نقش‌ها',
                'impersonate'  => 'ورود به‌عنوان کاربر',
            ],
            'users' => [
                'ban' => 'مسدود کردن کاربر',
            ],
            'wallet_campaigns' => [
                'allocate'      => 'تخصیص کمپین',
                'process_bonus' => 'پردازش پاداش',
            ],
            'wallets' => [
                'adjustment' => 'تنظیم موجودی',
                'deposit'    => 'واریز',
                'withdrawal' => 'برداشت',
            ],
        ],
        'custom_permission' => [
            'access_admin_panel' => 'دسترسی به پنل مدیریت',
        ],
    ],

];
