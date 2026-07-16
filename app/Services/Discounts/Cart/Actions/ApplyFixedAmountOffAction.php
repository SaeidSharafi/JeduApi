<?php

namespace App\Services\Discounts\Cart\Actions;

use App\Contracts\Discounts\DiscountActionContract;
use App\Data\Admin\Discounts\OrderContextData;
use App\Services\Discounts\Configs\ApplyFixedAmountOffData;
use App\Attributes\DiscountHandlerKey;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('apply_fixed_amount_off')]
class ApplyFixedAmountOffAction implements DiscountActionContract
{
    public static function getConfigClass(): string { return ApplyFixedAmountOffData::class; }

    public function apply(OrderContextData $context, Data $configuration): void
    {
        /** @var ApplyFixedAmountOffData $configuration */
        $totalWeight = $context->subtotal_all_items;
        if ($totalWeight <= 0) return;

        $remainingDiscount = min($configuration->amount, $totalWeight);

        // Proportional distribution across items
        foreach ($context->items as $item) {
            $ratio = $item->total / $totalWeight;
            $itemDiscount = (int) round($remainingDiscount * $ratio);

            // Cap discount so it doesn't exceed the item total
            $itemDiscount = min($itemDiscount, $item->total);

            $item->discount_amount += $itemDiscount;
            $item->total -= $itemDiscount;
        }
    }
}
