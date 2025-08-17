<?php

declare(strict_types=1);

namespace App\Enums\Order;

use App\Traits\AdvanceEnum;

enum DiscountTypeEnum: string
{
    use AdvanceEnum;

    case PRODUCT_SPECIFIC = 'product_specific';
    case CART_CHECKOUT = 'cart_checkout';

    public static function getListInfo()
    {
        $list = [];
        foreach (self::cases() as $case) {
            $list[] = [
                'value'       => $case->value,
                'label'       => $case->translate(),
                'description' => __("enums.DiscountTypeEnum.{$case->value}.description"),
            ];
        }
        return $list;
    }
}
