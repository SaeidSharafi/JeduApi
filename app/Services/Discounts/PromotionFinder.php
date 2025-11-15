<?php

declare(strict_types=1);

namespace App\Services\Discounts;

use App\Models\DiscountPromotion;
use Illuminate\Database\Eloquent\Builder;

final class PromotionFinder
{
    /**
     * Finds the single, active and valid promotion for a given order creation request.
     */
    public function findApplicablePromotion(
        ?string $appliedCouponCode = null,
        ?int $promotionId = null
    ): ?DiscountPromotion {
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

        if ($appliedCouponCode) {
            return $query
                ->whereHas('coupons', fn($q) => $q
                    ->where('code', $appliedCouponCode)
                    ->where('is_active', true)
                    ->where(function (Builder $q2): void {
                        $q2->whereNull('usage_limit')
                            ->orWhereColumn('usage_count', '<', 'usage_limit');
                    })

                )
                ->first();
        }

        if ($promotionId) {
            return $query->find($promotionId);
        }

        return null;
    }
}
