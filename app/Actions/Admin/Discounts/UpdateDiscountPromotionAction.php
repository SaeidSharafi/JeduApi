<?php

declare(strict_types=1);

namespace App\Actions\Admin\Discounts;

use App\Data\Admin\Discounts\DiscountPromotionCreateData;
use App\Enums\Order\DiscountTypeEnum;
use App\Jobs\Discounts\RegeneratePromotionDiscountPricesJob;
use App\Models\DiscountPromotion;
use Illuminate\Support\Facades\DB;

final class UpdateDiscountPromotionAction
{
    public function execute(DiscountPromotion $promotion, DiscountPromotionCreateData $data): DiscountPromotion
    {
        $promotion = DB::transaction(function () use ($promotion, $data) {
            $isCartSpecific = $data->type === DiscountTypeEnum::CART_CHECKOUT;
            $promotion->update([
                'name'                             => $data->name,
                'description'                      => $data->description,
                'type'                             => $data->type,
                'is_active'                        => $data->is_active,
                'starts_at'                        => $data->starts_at,
                'ends_at'                          => $data->ends_at,
                'priority'                         => $data->priority,
                'stop_processing_subsequent_rules' => $data->stop_processing_subsequent_rules,
                'usage_limit_total'                => $data->usage_limit_total,
                'usage_limit_per_customer'         => $data->usage_limit_per_customer,
                'requires_coupon'                  => $isCartSpecific && ! empty($data->coupons),
            ]);

            // Delete existing rules and create new ones
            $promotion->rules()->delete();
            foreach ($data->rules as $ruleData) {
                $promotion->rules()->create([
                    'type'          => $ruleData->type,
                    'handler'       => $ruleData->handler,
                    'configuration' => $ruleData->configuration,
                ]);
            }

            if ($isCartSpecific) {
                $promotion->coupons()->delete();
                foreach ($data->coupons as $couponData) {
                    $promotion->coupons()->create([
                        'code'        => $couponData->code,
                        'is_active'   => $couponData->is_active,
                        'usage_limit' => $couponData->usage_limit,
                        'usage_count' => 0,
                    ]);
                }
            }

            return $promotion->load(['rules', 'coupons']);
        });

        // Dispatch job to regenerate discount prices for this updated promotion
        if ($promotion->type === DiscountTypeEnum::PRODUCT_SPECIFIC) {
            RegeneratePromotionDiscountPricesJob::dispatch($promotion);
        }

        return $promotion;
    }
}
