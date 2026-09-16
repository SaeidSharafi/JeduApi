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
        // Bundle lines keep their reviewed selling price, so the flat amount is
        // distributed over the other lines only. A bundle-free cart keeps the
        // exact historical split over `subtotal_all_items`; once a Bundle is
        // present the eligible weight is the sum of the remaining lines.
        $eligibleItems = $context->items->reject(
            fn (CalculatedOrderItemData $item): bool => $item->is_bundle
        );

        $hasBundle   = $context->items->contains(fn (CalculatedOrderItemData $item): bool => $item->is_bundle);
        $totalWeight = $hasBundle ? $eligibleItems->sum('total') : $context->subtotal_all_items;
        if ($totalWeight <= 0) {
            return;
        }

        $remainingDiscount = min($configuration->amount, $totalWeight);

        foreach ($eligibleItems as $item) {
            $ratio        = $item->total / $totalWeight;
            $itemDiscount = (int) round($remainingDiscount * $ratio);

            // Cap discount so it doesn't exceed the item total
            $itemDiscount = min($itemDiscount, $item->total);

            $item->discount_amount += $itemDiscount;
            $item->total -= $itemDiscount;
        }
    }
}
