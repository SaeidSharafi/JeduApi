<?php

declare(strict_types=1);

namespace App\Services\Discounts\Cart\Conditions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\DiscountConditionContract;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderStatusEnum;
use App\Models\Order;
use App\Services\Discounts\Configs\FirstOrderOnlyData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('first_order_only')]
final class FirstOrderOnlyCondition implements DiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return FirstOrderOnlyData::class;
    }

    public function passes(OrderContextData $context, Data $configuration): bool
    {
        if (! $context->customer?->id) {
            return false;
        }

        $completedOrdersCount = Order::where('customer_id', $context->customer->id)
            ->where('status', OrderStatusEnum::COMPLETED)
            ->count();

        return $completedOrdersCount === 0;
    }
}
