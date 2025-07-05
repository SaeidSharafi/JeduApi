<?php

declare(strict_types=1);

return [

    'created'                => ':model با موفقیت ایجاد شد.',
    'updated'                => 'موارد :model با موفقیت به‌روزرسانی شد.',
    'deleted'                => 'موارد :model با موفقیت حذف شد.',
    'not_found'              => 'موارد :model یافت نشد.',
    'success'                => 'عملیات با موفقیت انجام شد.',
    'error'                  => 'خطا در انجام عملیات.',
    'unauthorized'           => 'شما اجازه دسترسی به این مورد را ندارید.',
    'forbidden'              => 'شما مجوز دسترسی به این مورد را ندارید.',
    'validation_error'       => 'خطا در اعتبارسنجی داده‌ها.',
    'validation_errors'      => 'خطا در اعتبارسنجی داده‌ها: :errors',
    'unauthenticated'        => 'شما وارد نشده‌اید یا اعتبارسنجی شما منقضی شده است.',
    'server_error'           => 'خطای داخلی سرور رخ داده است. لطفاً بعداً دوباره تلاش کنید.',
    'method_not_allowed'     => 'این روش برای این منبع مجاز نیست.',
    'resource_not_found'     => 'منبع مورد نظر یافت نشد.',
    'action_not_allowed'     => 'این عمل برای این منبع مجاز نیست.',
    'action_not_permitted'   => 'شما مجوز انجام این عمل را ندارید.',
    'action_not_supported'   => 'این عمل در این نسخه پشتیبانی نمی‌شود.',
    'action_not_implemented' => 'این عمل هنوز پیاده‌سازی نشده است.',
    'action_successful'      => 'عملیات با موفقیت انجام شد.',
    'action_failed'          => 'عملیات با شکست مواجه شد. لطفاً دوباره تلاش کنید.',
    'action_in_progress'     => 'عملیات در حال انجام است. لطفاً صبر کنید.',
    'action_completed'       => 'عملیات با موفقیت تکمیل شد.',
    'action_cancelled'       => 'عملیات لغو شد.',
    'action_pending'         => 'عملیات در انتظار است. لطفاً صبر کنید.',
    'media_uploaded'         => 'رسانه با موفقیت بارگذاری شد.',
    'media_deleted'          => 'رسانه با موفقیت حذف شد.',
    'media_not_found'        => 'رسانه مورد نظر یافت نشد.',
    'file_uploaded'          => 'فایل با موفقیت بارگذاری شد.',
    'file_deleted'           => 'فایل با موفقیت حذف شد.',
    'file_not_found'         => 'فایل مورد نظر یافت نشد.',
    'models'                 => [
        'seminar'       => 'سمینار',
        'staff'         => 'مدیر',
        'user'          => 'کاربر',
        'category'      => 'دسته‌بندی',
        'digital_asset' => 'دارایی دیجیتال',
        'course'        => 'دوره',
        'teacher'       => 'مدرس',
        'term'          => 'ترم',
    ],

    'errors' => [
        'model_has_relationship_data'                       => 'رکورد مورد نظر دارای داده‌های مرتبط (:related_model) است و نمی‌توان آن را حذف کرد.',
        'model_has_relationship_data_without_related_model' => 'رکورد مورد نظر دارای داده‌های مرتبط است و نمی‌توان آن را حذف کرد.',
    ],
    'order'  => [
        'items_already_purchased' => 'کاربر قبلاً موارد زیر را خریداری کرده‌ است: :products.',
        'item_already_purchased'  => 'کاربر قبلاً این مورد را خریداری کرده است.',
        'prepayment_not_available' => 'پرداخت پیش‌پرداخت برای محصول :product در دسترس نیست.',
    ],
];
