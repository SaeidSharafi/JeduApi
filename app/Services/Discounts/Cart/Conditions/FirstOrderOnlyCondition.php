<?php

namespace App\Services\Discounts\Cart\Conditions;

use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderStatusEnum;
use App\Services\Discounts\Configs\FirstOrderOnlyData;
use App\Contracts\Discounts\DiscountConditionContract;
use App\Models\Order;
use App\Attributes\DiscountHandlerKey;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('first_order_only')]
class FirstOrderOnlyCondition implements DiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return FirstOrderOnlyData::class;
    }

    public function passes(OrderContextData $context, Data $configuration): bool
    {
        if (!$context->customer?->id) {
            return false;
        }

        $completedOrdersCount = Order::where('customer_id', $context->customer->id)
            ->where('status', OrderStatusEnum::COMPLETED)
            ->count();

        return $completedOrdersCount === 0;
    }
}
