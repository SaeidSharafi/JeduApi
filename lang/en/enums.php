<?php

declare(strict_types=1);

return [
    'CourseStatusEnum' => [
        'draft'     => 'Draft',
        'published' => 'Published',
        'archived'  => 'Archived',
    ],
    'CourseDifficultyLevelEnum' => [
        'beginner'     => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced'     => 'Advanced',
        'expert'       => 'Expert',
    ],
    'PublicationStatusEnum' => [
        'draft'     => 'Draft',
        'published' => 'Published',
        'archived'  => 'Archived',
    ],
    'GenderEnum' => [
        'male'   => 'Male',
        'female' => 'Female',
    ],
    'TermStatusEnum' => [
        'active'   => 'Active',
        'inactive' => 'Inactive',
        'planning' => 'Planning',
    ],
    'MorphTypeEnum' => [
        'course'        => 'Course',
        'seminar'       => 'Seminar',
        'digital_asset' => 'Digital Asset',
        'product'       => 'Product',
        'staff'         => 'Staff',
        'user'          => 'User',
        'teacher'       => 'Teacher',
        'vendor'        => 'Vendor',
    ],
    'ProductableEnum' => [
        'course'        => 'Course',
        'seminar'       => 'Seminar',
        'digital_asset' => 'Digital Asset',
    ],
    'FulfillmentTypeEnum' => [
        'digital'           => 'Digital',
        'physical'          => 'Physical',
        'online_service'    => 'Online Service',
        'offline_service'   => 'Offline Service',
        'in_person_service' => 'In-person Service',
    ],
    'DeliveryMethodEnum' => [
        'direct_download'           => 'Direct Download',
        'live_session_bbb'          => 'Live Session (BBB)',
        'live_session_skyroom'      => 'Live Session (Skyroom)',
        'lms_moodle'                => 'LMS Moodle',
        'video_platform_spotplayer' => 'Video Platform (SpotPlayer)',
        'in_person'                 => 'In-person',
    ],
    'CivilIdTypeEnum' => [
        'national_code'  => 'National Code',
        'passport'       => 'Passport',
        'immigrant_code' => 'Immigrant Code',
    ],
    'EducationLevelEnum' => [
        'student'       => 'Student',
        'under_diploma' => 'Under Diploma',
        'diploma'       => 'Diploma',
        'associate'     => 'Associate',
        'bachelor'      => 'Bachelor',
        'master'        => 'Master',
        'doctorate'     => 'Doctorate',
    ],
    'EducationStatusEnum' => [
        'student'    => 'Student',
        'university' => 'University',
        'graduated'  => 'Graduated',
        'employed'   => 'Employed',
    ],
    'OrderItemStatusEnum' => [
        'active'    => 'Active',
        'cancelled' => 'Cancelled',
        'refunded'  => 'Refunded',
    ],
    'OrderItemPaymentTypeEnum' => [
        'pre_payment'  => 'Pre-payment',
        'full_payment' => 'Full Payment',
        'invoice'      => 'Invoice',
    ],
    'OrderPaymentStatusEnum' => [
        'pending'            => 'Pending',
        'partially_paid'     => 'Partially Paid',
        'paid'               => 'Paid',
        'refunded'           => 'Refunded',
        'partially_refunded' => 'Partially Refunded',
    ],
    'OrderStatusEnum' => [
        'pending'    => 'Pending',
        'processing' => 'Processing',
        'completed'  => 'Completed',
        'cancelled'  => 'Cancelled',
        'failed'     => 'Failed',
        'refunded'   => 'Refunded',
    ],
    'PaymentMethodEnum' => [
        'bank_transfer'  => 'Bank Transfer',
        'online_gateway' => 'Online Gateway',
    ],
    'PaymentStatusEnum' => [
        'pending'   => 'Pending',
        'completed' => 'Completed',
        'failed'    => 'Failed',
    ],
];
