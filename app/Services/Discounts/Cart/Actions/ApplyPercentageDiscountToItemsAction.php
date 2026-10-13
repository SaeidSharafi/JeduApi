<?php

declare(strict_types=1);

namespace App\Services\Discounts\Cart\Actions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\Cart\LineMarkdownActionContract;
use App\Contracts\Discounts\DiscountActionContract;
use App\Data\Admin\Discounts\OrderContextData;
use App\Services\Discounts\Configs\ApplyPercentageDiscountConfigData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('apply_percentage_off')]
final class ApplyPercentageDiscountToItemsAction implements DiscountActionContract, LineMarkdownActionContract
{
    public static function getConfigClass(): string
    {
        return ApplyPercentageDiscountConfigData::class;
    }

    public function apply(OrderContextData $context, Data $configuration): void
    {
        if (! $configuration instanceof ApplyPercentageDiscountConfigData) {
            return;
        }

        $discountRate  = $configuration->percentage / 100;
        $promotionName = $context->evaluating_promotion->name ?? 'Discount';

        // Bundle and deposit lines are not in the discountable set, and
        // applyDiscount() is the second guarantee should one ever slip through.
        foreach ($context->discountableItems() as $item) {
            // Calculate the discount amount for a single unit
            $discountPerUnit = (int) round($item->price * $discountRate);

            // Cannot discount more than the item's price
            if ($discountPerUnit > $item->price) {
                $discountPerUnit = $item->price;
            }

            $applied = $item->applyDiscount($discountPerUnit * $item->qty);

            // Add a record for the final audit trail
            $item->applied_discount_details[] = [
                'promotion_id'   => $context->evaluating_promotion?->id,
                'promotion_name' => $promotionName,
                'applied_amount' => $applied,
                'coupon_code'    => $context->triggered_by_coupon_code,
            ];
        }
    }
}
