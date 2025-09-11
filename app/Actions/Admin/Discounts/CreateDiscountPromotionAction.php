<?php

declare(strict_types=1);

namespace App\Actions\Admin\Discounts;

use App\Data\Admin\Discounts\DiscountPromotionCreateData;
use App\Models\DiscountPromotion;
use Illuminate\Support\Facades\DB;

final class CreateDiscountPromotionAction
{
    public function execute(DiscountPromotionCreateData $data): DiscountPromotion
    {
        $promotion = DB::transaction(function () use ($data) {
            // Create the main promotion
            $promotion = DiscountPromotion::create([
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
                'total_usage_count'                => 0,
            ]);

            // Create rules
            foreach ($data->rules as $ruleData) {
                $promotion->rules()->create([
                    'type'          => $ruleData->type,
                    'handler'       => $ruleData->handler,
                    'configuration' => $ruleData->configuration,
                ]);
            }

            // Create coupons
            foreach ($data->coupons as $couponData) {
                $promotion->coupons()->create([
                    'code'        => $couponData->code,
                    'is_active'   => $couponData->is_active,
                    'usage_limit' => $couponData->usage_limit,
                    'usage_count' => 0,
                ]);
            }

            return $promotion->load(['rules', 'coupons']);
        });

        // Dispatch job to regenerate discount prices for this new promotion
        if ($promotion->type === \App\Enums\Order\DiscountTypeEnum::PRODUCT_SPECIFIC) {
            \App\Jobs\Discounts\RegeneratePromotionDiscountPricesJob::dispatch($promotion);
        }

        return $promotion;
    }
}
