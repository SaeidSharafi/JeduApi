<?php

declare(strict_types=1);

namespace App\Services\Discounts\Cart\Actions;

use App\Attributes\DiscountHandler;
use App\Contracts\Discounts\DiscountActionContract;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Services\Discounts\Configs\ApplyPercentageDiscountConfigData;
use Spatie\LaravelData\Data;

#[DiscountHandler('apply_percentage_off', 'action')]
final class ApplyPercentageDiscountToItemsAction implements DiscountActionContract
{
    public static function getConfigClass(): string
    {
        return ApplyPercentageDiscountConfigData::class;
    }

    public function apply(OrderContextData $context, Data $configuration): void
    {
        if (!$configuration instanceof ApplyPercentageDiscountConfigData) {
            return;
        }

        $discountRate = $configuration->percentage / 100;
        $promotionName = $context->evaluating_promotion?->name ?? 'Discount';

        foreach ($context->items as $item) {
            // HERE IS THE CRITICAL LOGIC FOR PREPAYMENTS
            $paymentType = $item->payment_type;
            if ($paymentType === OrderItemPaymentTypeEnum::PRE_PAYMENT) {
                continue; // Skip and do not apply any discount to prepayment items
            }

            // Calculate the discount amount for a single unit
            $discountPerUnit = (int) round($item->price * $discountRate);

            // Cannot discount more than the item's price
            if ($discountPerUnit > $item->price) {
                $discountPerUnit = $item->price;
            }

            // Update the item's state within the context
            $item->discount_amount += ($discountPerUnit * $item->qty);
            $item->total = ($item->price * $item->qty) - $item->discount_amount;

            // Add a record for the final audit trail
            $item->applied_discount_details[] = [
                'promotion_id'   => $context->evaluating_promotion?->id,
                'promotion_name' => $promotionName,
                'applied_amount' => ($discountPerUnit * $item->qty),
                'coupon_code'    => $context->triggered_by_coupon_code,
            ];
        }
    }
}
