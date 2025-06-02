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
            'course' => 'دوره ها',
            'seminar' => 'سمینار ها',
            'digital_asset' => 'فایل ها',
            'user'   => 'کاربران',
            'role'   => 'نقش ها',
            'media'  => 'رسانه ها',
            'file'   => 'فایل های خصوصی',
            'category' => 'دسته بندی ها',
            'staff'  => 'کاربران',
        ],
        'action' => [
            'view_any'     => 'مشاهده همه',
            'view'         => 'مشاهده',
            'view_scoped'  => 'مشاهده با دسترسی محدود',
            'view_own'   => 'مشاهده خود',
            'create'       => 'ایجاد',
            'update'       => 'ویرایش',
            'update_own'   => 'ویرایش خود',
            'delete'       => 'حذف',
            'delete_own'   => 'حذف خود',
            'restore'      => 'بازیابی',
            'force_delete' => 'حذف دائمی',
        ],
        'custom' => [
            'staff' => [
                'manage_roles' => 'مدیریت نقش ها',
                'impersonate'  => ' ورود به عنوان کاربر',
            ],
        ]
    ],

];
