<?php

declare(strict_types=1);

return [

    'handlers' => [

        // ---- Cart Conditions ----
        'cart_value_over' => [
            'name'        => 'حداقل مبلغ سبد خرید',
            'description' => 'این شرط بر اساس مجموع مبلغ سبد خرید مشتری بررسی می‌شود.',
            'fields'      => [
                'operator' => [
                    'label'       => 'نوع مقایسه',
                    'description' => 'نحوه مقایسه مبلغ سبد خرید مشتری با مبلغ تعیین‌شده (مثلاً بیشتر یا مساوی، کمتر از و ...).',
                ],
                'value' => [
                    'label'       => 'مبلغ',
                    'description' => 'مبلغی که سبد خرید مشتری با آن مقایسه می‌شود (به ریال).',
                ],
                'include_prepayments' => [
                    'label'       => 'احتساب پیش‌پرداخت‌ها',
                    'description' => 'در صورت فعال بودن، مبلغ پیش‌پرداخت اقلام سبد خرید هم در محاسبه این شرط لحاظ می‌شود.',
                ],
            ],
        ],
        'cart_item_count_over' => [
            'name'        => 'تعداد اقلام سبد خرید',
            'description' => 'این شرط بر اساس تعداد کالاهای موجود در سبد خرید بررسی می‌شود.',
            'fields'      => [
                'min_count' => [
                    'label'       => 'حداقل تعداد',
                    'description' => 'حداقل تعداد کالاهای مورد نیاز برای برقراری این شرط.',
                ],
                'count_quantities' => [
                    'label'       => 'شمارش تعداد واحدها',
                    'description' => 'اگر فعال باشد، مجموع تعداد واحد هر کالا شمارش می‌شود، در غیر این صورت فقط تعداد ردیف‌های سبد خرید محاسبه می‌گردد.',
                ],
            ],
        ],
        'first_order_only' => [
            'name'        => 'فقط اولین سفارش',
            'description' => 'این شرط بررسی می‌کند که آیا این اولین خرید مشتری است یا خیر.',
            'fields'      => [],
        ],
        'specific_products_in_cart' => [
            'name'        => 'محصولات خاص در سبد خرید',
            'description' => 'این شرط بررسی می‌کند که آیا محصولات مشخص‌شده در سبد خرید وجود دارند.',
            'fields'      => [
                'product_ids' => [
                    'label'       => 'شناسه محصولات',
                    'description' => 'لیست شناسه‌های محصولات مورد نیاز.',
                ],
            ],
        ],
        'user_never_purchased_category' => [
            'name'        => 'عدم خرید قبلی از دسته‌بندی',
            'description' => 'این شرط بررسی می‌کند که مشتری قبلاً هیچ خریدی از دسته‌بندی‌های مشخص‌شده نداشته باشد.',
            'fields'      => [
                'category_ids' => [
                    'label'       => 'شناسه دسته‌بندی‌ها',
                    'description' => 'لیست دسته‌بندی‌هایی که مشتری نباید قبلاً از آن‌ها خرید کرده باشد.',
                ],
            ],
        ],

        // ---- Product Conditions ----
        'delivery_method_is' => [
            'name'        => 'روش تحویل محصول',
            'description' => 'این شرط بررسی می‌کند که آیا روش تحویل محصول با موارد انتخاب‌شده تطابق دارد.',
            'fields'      => [
                'delivery_methods' => [
                    'label'       => 'روش‌های تحویل',
                    'description' => 'لیست روش‌های تحویل مجاز.',
                ],
            ],
        ],

        // ---- Shared ----
        'product_in_category' => [
            'name'        => 'دسته‌بندی محصول',
            'description' => 'این شرط بررسی می‌کند که آیا محصول در دسته‌بندی‌های مشخص‌شده قرار دارد.',
            'fields'      => [
                'category_ids' => [
                    'label'       => 'دسته‌بندی‌ها',
                    'description' => 'دسته‌بندی‌هایی که محصول باید در آن‌ها باشد.',
                ],
                'match_policy' => [
                    'label'       => 'نحوه بررسی تطابق',
                    'description' => 'مشخص می‌کند که آیا کافیست محصول در یکی از دسته‌ها باشد یا همه آن‌ها.',
                ],
            ],
        ],

        // ---- Actions ----
        'add_gift_credit' => [
            'name'        => 'اعطای اعتبار هدیه',
            'description' => 'مبلغی به‌عنوان اعتبار هدیه به کیف پول مشتری اضافه می‌شود.',
            'fields'      => [
                'amount' => [
                    'label'       => 'مبلغ اعتبار هدیه',
                    'description' => 'مبلغ اعتبار به ریال.',
                ],
                'per_item' => [
                    'label'       => 'محاسبه به ازای هر کالا',
                    'description' => 'در صورت فعال بودن، به ازای هر کالا محاسبه می‌شود.',
                ],
                'expires_days' => [
                    'label'       => 'مدت اعتبار (روز)',
                    'description' => 'تعداد روزهای اعتبار (خالی بگذارید برای اعتبار دائمی).',
                ],
                'description' => [
                    'label'       => 'یادداشت تراکنش',
                    'description' => 'توضیحات در سابقه کیف پول.',
                ],
            ],
        ],
        'add_wallet_credit' => [
            'name'        => 'افزایش موجودی کیف پول',
            'description' => 'مبلغی مشخص مستقیماً به موجودی اصلی کیف پول افزوده می‌شود.',
            'fields'      => [
                'amount' => [
                    'label'       => 'مبلغ اعتبار',
                    'description' => 'مبلغ به ریال.',
                ],
                'per_item' => [
                    'label'       => 'محاسبه به ازای هر کالا',
                    'description' => 'در صورت فعال بودن، به ازای هر کالا محاسبه می‌شود.',
                ],
                'description' => [
                    'label'       => 'یادداشت تراکنش',
                    'description' => 'توضیحات در سابقه کیف پول.',
                ],
            ],
        ],
        'apply_fixed_amount_off' => [
            'name'        => 'تخفیف مبلغ ثابت روی سبد خرید',
            'description' => 'مبلغ مشخصی از مجموع سبد خرید کسر می‌شود.',
            'fields'      => [
                'amount' => [
                    'label'       => 'مبلغ تخفیف',
                    'description' => 'مبلغ تخفیف به ریال.',
                ],
            ],
        ],
        'apply_percentage_off' => [
            'name'        => 'تخفیف درصدی روی سبد خرید',
            'description' => 'درصد مشخصی از مبلغ اقلام سبد خرید کسر می‌شود.',
            'fields'      => [
                'percentage' => [
                    'label'       => 'درصد تخفیف',
                    'description' => 'درصد تخفیف (مثلاً ۱۵ برای ۱۵٪).',
                ],
            ],
        ],
        'apply_tiered_percentage_off' => [
            'name'        => 'تخفیف درصدی پلکانی',
            'description' => 'اعمال تخفیف درصدی بر اساس پله‌های تعریف شده.',
            'fields'      => [
                'tiers' => [
                    'label'       => 'پله‌های تخفیف',
                    'description' => 'تعریف پله‌های مقداری و درصد تخفیف مربوطه.',
                ],
            ],
        ],
        'gift_product' => [
            'name'        => 'هدیه محصول',
            'description' => 'افزودن یک محصول به عنوان هدیه به سبد خرید.',
            'fields'      => [
                'product_delivery_option_id' => [
                    'label'       => 'شناسه گزینه تحویل محصول',
                    'description' => 'شناسه محصول هدیه.',
                ],
            ],
        ],
        'apply_percentage_off_product' => [
            'name'        => 'تخفیف درصدی محصول',
            'description' => 'درصد مشخصی از قیمت محصول کسر می‌شود.',
            'fields'      => [
                'percentage' => [
                    'label'       => 'درصد تخفیف',
                    'description' => 'درصد تخفیف.',
                ],
            ],
        ],
        'apply_fixed_discount_product' => [
            'name'        => 'تخفیف مبلغ ثابت محصول',
            'description' => 'مبلغ مشخصی از قیمت محصول کسر می‌شود.',
            'fields'      => [
                'amount' => [
                    'label'       => 'مبلغ تخفیف',
                    'description' => 'مبلغ تخفیف به ریال.',
                ],
            ],
        ],
    ],

    'operators' => [
        'greater_than_or_equal' => 'بیشتر یا مساوی',
        'greater_than'          => 'بیشتر از',
        'less_than_or_equal'    => 'کمتر یا مساوی',
        'less_than'             => 'کمتر از',
        'equal'                 => 'برابر با',
    ],

    'types' => [
        'product_specific' => [
            'label'       => 'تخفیف مخصوص محصول',
            'description' => 'این نوع تخفیف روی محصولات انتخاب‌شده اعمال می‌شود و در صفحه محصول و لیست محصولات به مشتری نمایش داده می‌شود.',
        ],
        'cart_checkout' => [
            'label'       => 'تخفیف زمان تسویه حساب',
            'description' => 'این نوع تخفیف در مرحله نهایی خرید، بر اساس شرایط کل سبد خرید (مانند کد تخفیف یا مبلغ سبد خرید)، محاسبه و اعمال می‌شود.',
        ],
    ],

    // Reusable enum case labels, keyed by enum class basename — separate from
    // per-field labels since the same enum can be reused across handlers.
    'enum_cases' => [
        'MatchPolicyEnum' => [
            'any' => 'حداقل یکی از دسته‌ها',
            'all' => 'تمام دسته‌های انتخابی',
        ],
    ],

    'validation' => [
        'missing_required_keys'  => ':attribute باید شامل «نوع»، «شناسه قانون» و «تنظیمات» باشد.',
        'handler_not_recognized' => 'شرط یا عملیاتی با شناسه \':handler\' یافت نشد.',
        'configuration_invalid'  => 'تنظیمات وارد‌شده برای \':handler\' نامعتبر است: :errors',
        'condition_required'     => 'حداقل یک شرط باید در :attribute مشخص شود.',
        'action_required'        => 'حداقل یک عمل باید در :attribute مشخص شود.',
    ],

];
