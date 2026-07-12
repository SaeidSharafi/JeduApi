<?php

declare(strict_types=1);

return [
    'PublicationStatusEnum'         => [
        'draft'     => 'پیش‌نویس',
        'published' => 'منتشر شده',
        'archived'  => 'آرشیو',
    ],
    'CourseDifficultyLevelEnum'     => [
        'beginner'     => 'مبتدی',
        'intermediate' => 'متوسط',
        'advanced'     => 'پیشرفته',
        'expert'       => 'کارشناس',
    ],
    'GenderEnum'                    => [
        'male'   => 'مرد',
        'female' => 'زن',
    ],
    'TermStatusEnum'                => [
        'active'   => 'فعال',
        'inactive' => 'غیرفعال',
        'planning' => 'در حال برنامه‌ریزی',
    ],
    'MorphTypeEnum'                 => [
        'course'        => 'دوره',
        'seminar'       => 'سمینار',
        'digital_asset' => 'فایل',
        'product'       => 'محصول',
        'staff'         => 'کارمند',
        'user'          => 'کاربر',
        'teacher'       => 'مدرس',
        'vendor'        => 'فروشنده',
    ],
    'ProductableEnum'               => [
        'course'        => 'دوره',
        'seminar'       => 'سمینار',
        'digital_asset' => 'فایل',
    ],
    'FulfillmentTypeEnum'           => [
        'digital'           => 'دیجیتال',
        'physical'          => 'فیزیکی',
        'online_service'    => 'سرویس آنلاین',
        'offline_service'   => 'سرویس آفلاین',
        'in_person_service' => 'سرویس حضوری',
    ],
    'DeliveryMethodEnum'            => [
        'direct_download'           => 'دانلود مستقیم',
        'live_session_bbb'          => 'جلسه زنده با BBB',
        'live_session_skyroom'      => 'جلسه زنده با Skyroom',
        'lms_moodle'                => 'LMS Moodle',
        'video_platform_spotplayer' => 'پلتفرم ویدیویی SpotPlayer',
        'in_person'                 => 'حضوری',
    ],
    'CivilIdTypeEnum'               => [
        'national_code'  => 'کد ملی',
        'passport'       => 'پاسپورت',
        'immigrant_code' => 'کد اتباع',
    ],
    'EducationLevelEnum'            => [
        'student'       => 'دانش‌آموز',
        'under_diploma' => 'زیردیپلم',
        'diploma'       => 'دیپلم',
        'associate'     => 'کاردانی',
        'bachelor'      => 'کارشناسی',
        'master'        => 'کارشناسی ارشد',
        'doctorate'     => 'دکتری',
    ],
    'EducationStatusEnum'           => [
        'student'    => 'دانشجو',
        'university' => 'دانشگاه',
        'graduated'  => 'فارغ‌التحصیل',
        'employed'   => 'شاغل',
    ],
    'OrderItemStatusEnum'           => [
        'active'    => 'فعال',
        'cancelled' => 'لغو شده',
        'refunded'  => 'بازپرداخت شده',
    ],
    'OrderItemPaymentTypeEnum'      => [
        'pre_payment'  => 'پیش‌پرداخت',
        'full_payment' => 'پرداخت کامل',
        'invoice'      => 'صورتحساب',
    ],
    'OrderPaymentStatusEnum'        => [
        'pending'            => 'در انتظار',
        'partially_paid'     => 'پرداخت جزئی',
        'paid'               => 'پرداخت شده',
        'refunded'           => 'بازپرداخت شده',
        'partially_refunded' => 'بازپرداخت جزئی',
    ],
    'OrderStatusEnum'               => [
        'pending'    => 'در انتظار',
        'processing' => 'در حال پردازش',
        'completed'  => 'تکمیل شده',
        'cancelled'  => 'لغو شده',
        'failed'     => 'ناموفق',
        'refunded'   => 'بازپرداخت شده',
    ],
    'PaymentMethodEnum'             => [
        'bank_transfer'  => 'انتقال بانکی',
        'mellat_gateway' => 'درگاه ملت',
        'wallet'         => 'کیف پول',
        'digipay'        => 'دیجی‌پی',
    ],
    'PaymentStatusEnum'             => [
        'pending'   => 'در انتظار',
        'completed' => 'تکمیل شده',
        'failed'    => 'ناموفق',
    ],
    'MatchPolicyEnum'               => [
        'any' => 'هر کدام',
        'all' => 'همه',
    ],
    'MathOperatorEnum'              => [
        '==' => 'برابر',
        '<'  => 'کوچکتر از',
        '>'  => 'بزرگتر از',
        '<=' => 'کوچکتر یا مساوی',
        '>=' => 'بزرگتر یا مساوی',
        '<>' => 'نا برابر',
    ],
    'ProductRegistrationStatusEnum' => [
        'in_progress' => 'در حال ثبت‌نام',
        'finished'    => 'برگزار شده',
    ],
    'ProductDeliveryStatusEnum'     => [
        'online'    => 'آنلاین',
        'in_person' => 'حضوری',
        'combined'  => 'ترکیبی',
    ],
    'EnrollmentStatusEnum'          => [
        'awaiting_payment'     => 'در انتظار پرداخت',
        'pending_provisioning' => 'در حال آماده‌سازی دسترسی',
        'active'               => 'فعال',
        'suspended'            => 'معلق',
        'expired'              => 'منقضی شده',
        'cancelled'            => 'لغو شده',
        'provisioning_failed'  => 'خطا در آماده‌سازی دسترسی',
    ],
    'WalletStatusEnum'              => [
        'active'    => 'فعال',
        'suspended' => 'معلق',
        'closed'    => 'بسته شده',
    ],
    'MoodleActivityStateEnum'       => [
        'incomplete'    => 'تکمیل نشده',
        '0'             => 'تکمیل نشده',
        'complete'      => 'تکمیل شده',
        '1'             => 'تکمیل شده',
        'complete_pass' => 'قبول شده',
        '2'             => 'قبول شده',
        'complete_fail' => 'رد شده',
        '3'             => 'رد شده',
    ],
    'PaymentTransactionStatusEnum'  =>
        [
            'initiated' => 'شروع شده',
            'completed' => 'تکمیل شده',
            'failed'    => 'ناموفق',
        ],
    'PaymentPurposeEnum'            => [
        'order'        => 'سفارش',
        'wallet_topup' => 'کیف پول',
    ]
];
