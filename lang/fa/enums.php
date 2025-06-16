<?php

declare(strict_types=1);

return [
    'PublicationStatusEnum'     => [
        'draft'     => 'پیش‌نویس',
        'published' => 'منتشر شده',
        'archived'  => 'آرشیو',
    ],
    'CourseDifficultyLevelEnum' => [
        'beginner'     => 'مبتدی',
        'intermediate' => 'متوسط',
        'advanced'     => 'پیشرفته',
        'expert'       => 'کارشناس',
    ],
    'GenderEnum'                => [
        'male'   => 'مرد',
        'female' => 'زن',
    ],
    'TermStatusEnum'            => [
        'active'   => 'فعال',
        'inactive' => 'غیرفعال',
        'planning' => 'در حال برنامه‌ریزی',
    ],
    'MorphTypeEnum'             => [
        'course'        => 'دوره',
        'seminar'       => 'سمینار',
        'digital_asset' => 'فایل',
        'product'       => 'محصول',
        'staff'         => 'کارمند',
        'user'          => 'کاربر',
        'teacher'       => 'مدرس',
        'vendor'        => 'فروشنده',
    ],
    'ProductableEnum'           => [
        'course'        => 'دوره',
        'seminar'       => 'سمینار',
        'digital_asset' => 'فایل',
    ],
    'FulfillmentTypeEnum'       => [
        'digital'           => 'دیجیتال',
        'physical'          => 'فیزیکی',
        'online_service'    => 'سرویس آنلاین',
        'offline_service'   => 'سرویس آفلاین',
        'in_person_service' => 'سرویس حضوری',
    ],
    'DeliveryMethodEnum'        => [
        'direct_download'           => 'دانلود مستقیم',
        'live_session_bbb'          => 'جلسه زنده با BBB',
        'live_session_skyroom'      => 'جلسه زنده با Skyroom',
        'lms_moodle'                => 'LMS Moodle',
        'video_platform_spotplayer' => 'پلتفرم ویدیویی SpotPlayer',
        'in_person'                 => 'حضوری',
    ],

];
