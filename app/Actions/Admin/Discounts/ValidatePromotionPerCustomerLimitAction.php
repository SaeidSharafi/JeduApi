<?php

declare(strict_types=1);

namespace App\Actions\Admin\Discounts;

use App\Services\Discounts\PromotionService;
use Illuminate\Validation\ValidationException;

/**
 * Enforces the per-customer promotion limit for an explicitly supplied coupon.
 *
 * Automatic promotions never reach this action: an exhausted allowance makes
 * them ineligible and ResolvePerCustomerPromotionEligibilityAction drops them
 * from the calculation, so they cannot block a sale. A coupon the customer (or
 * an admin) supplied on purpose is different — silently dropping it would hide
 * why the discount disappeared — so it fails visibly, naming the pending order
 * that holds the slot when one exists.
 *
 * Must run inside a DB transaction: the promotion row is locked for update so
 * concurrent checkouts cannot both slip past the limit.
 */
final readonly class ValidatePromotionPerCustomerLimitAction
{
    public function __construct(
        private PromotionService $promotionService,
        private ResolvePerCustomerPromotionEligibilityAction $perCustomerEligibility,
    ) {}

    public function handle(?string $appliedCouponCode, ?int $customerId): void
    {
        if (! $appliedCouponCode || ! $customerId) {
            return;
        }

        $promotion = $this->promotionService->findPromotionByCoupon($appliedCouponCode, lockForUpdate: true);

        if (! $promotion) {
            return;
        }

        $message = $this->perCustomerEligibility->blockingMessage($promotion, $customerId);

        if ($message !== null) {
            throw ValidationException::withMessages([
                'coupon' => [$message],
            ]);
        }
    }
}
