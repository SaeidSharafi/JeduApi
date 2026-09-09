<?php

declare(strict_types=1);

namespace App\Services\Discounts\Cart\Actions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\DiscountActionContract;
use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Services\Discounts\Configs\ApplyFixedAmountOffData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('apply_fixed_amount_off')]
final class ApplyFixedAmountOffAction implements DiscountActionContract
{
    public static function getConfigClass(): string
    {
        return ApplyFixedAmountOffData::class;
    }

    public function apply(OrderContextData $context, Data $configuration): void
    {
        /** @var ApplyFixedAmountOffData $configuration */
        // Distribute across non-Bundle lines only: Bundle lines keep their
        // reviewed selling price. Without bundles the eligible weight equals
        // the whole cart, preserving the historical proportional split.
        $eligibleItems = $context->items->reject(
            fn (CalculatedOrderItemData $item): bool => $item->is_bundle
        );
        $totalWeight = $eligibleItems->sum('total');
        if ($totalWeight <= 0) {
            return;
        }

        $remainingDiscount = min($configuration->amount, $totalWeight);

        // Proportional distribution across items
        foreach ($context->items as $item) {
            if ($item->is_bundle) {
                continue;
            }

            $ratio        = $item->total / $totalWeight;
            $itemDiscount = (int) round($remainingDiscount * $ratio);

            // Cap discount so it doesn't exceed the item total
            $itemDiscount = min($itemDiscount, $item->total);

            $item->discount_amount += $itemDiscount;
            $item->total -= $itemDiscount;
        }
    }
}
