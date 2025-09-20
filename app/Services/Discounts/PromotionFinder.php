<?php

declare(strict_types=1);

namespace App\Services\Discounts;

use App\Data\Admin\Order\OrderCreateData;
use App\Models\DiscountPromotion;

final class PromotionFinder
{
    /**
     * Finds the single, active and valid promotion for a given order creation request.
     */
    public function findApplicablePromotion(OrderCreateData $data): ?DiscountPromotion
    {
        // This is the exact same query logic from the old service.
        $query = DiscountPromotion::query()
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->with('rules');

        if ($data->applied_coupon_code) {
            return $query->whereHas('coupons', fn ($q) => $q->where('code', $data->applied_coupon_code))->first();
        }

        if ($data->promotion_id) {
            return $query->find($data->promotion_id);
        }

        return null;
    }
}
