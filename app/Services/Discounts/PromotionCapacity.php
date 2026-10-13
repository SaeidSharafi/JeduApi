<?php

declare(strict_types=1);

namespace App\Services\Discounts;

/**
 * The single comparison behind every usage cap.
 *
 * A promotion's total cap, a coupon's own cap and a customer's per-promotion
 * allowance all answer the same question — "has the capacity reserved against
 * this budget reached its limit?" — and they must answer it the same way, or
 * preview, checkout, the blocking message and reporting drift apart. Callers
 * keep owning their query (the finder pre-loads one grouped count of
 * `DiscountPromotionUsage::active()` rows, the per-customer filter scopes the
 * same count by customer) and hand the resulting number in, so this stays a
 * pure decision with no N+1 risk.
 */
final readonly class PromotionCapacity
{
    /**
     * A null limit means the budget is unlimited; anything else is exhausted
     * exactly when the reserved/consumed count reaches it.
     */
    public function exhausted(?int $limit, int $count): bool
    {
        return $limit !== null && $count >= $limit;
    }
}
