<?php

declare(strict_types=1);

namespace App\Actions\Admin\Discounts;

use App\Data\Admin\Discounts\DiscountPromotionCreateData;
use App\Models\DiscountPromotion;
use App\Services\Discounts\ProductDeliveryOptionDiscountPriceRegenerator;
use Illuminate\Support\Facades\DB;

final class UpdateDiscountPromotionAction
{
    protected ProductDeliveryOptionDiscountPriceRegenerator $regenerator;

    public function __construct(ProductDeliveryOptionDiscountPriceRegenerator $regenerator)
    {
        $this->regenerator = $regenerator;
    }

    public function execute(DiscountPromotion $promotion, DiscountPromotionCreateData $data): DiscountPromotion
    {
        $promotion = DB::transaction(function () use ($promotion, $data) {
            // Update the main promotion
            $promotion->update([
                'name' => $data->name,
                'description' => $data->description,
                'type' => $data->type,
                'is_active' => $data->is_active,
                'starts_at' => $data->starts_at,
                'ends_at' => $data->ends_at,
                'priority' => $data->priority,
                'stop_processing_subsequent_rules' => $data->stop_processing_subsequent_rules,
                'usage_limit_total' => $data->usage_limit_total,
                'usage_limit_per_customer' => $data->usage_limit_per_customer,
            ]);

            // Delete existing rules and create new ones
            $promotion->rules()->delete();
            foreach ($data->rules as $ruleData) {
                $promotion->rules()->create([
                    'type' => $ruleData->type,
                    'handler' => $ruleData->handler,
                    'configuration' => $ruleData->configuration,
                ]);
            }

            // Delete existing coupons and create new ones
            $promotion->coupons()->delete();
            foreach ($data->coupons as $couponData) {
                $promotion->coupons()->create([
                    'code' => $couponData->code,
                    'is_active' => $couponData->is_active,
                    'usage_limit' => $couponData->usage_limit,
                    'usage_count' => 0,
                ]);
            }

            return $promotion->load(['rules', 'coupons']);
        });

        // Regenerate discount prices after promotion update
        $this->regenerator->regenerate();

        return $promotion;
    }
}
