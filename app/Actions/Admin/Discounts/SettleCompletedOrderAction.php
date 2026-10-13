<?php

declare(strict_types=1);

namespace App\Actions\Admin\Discounts;

use App\Models\Order;

final readonly class SettleCompletedOrderAction
{
    public function __construct(
        private IncrementDiscountUsageCountsAction $incrementDiscountUsageCounts,
        private GrantOrderRewardsAction $grantOrderRewards,
    ) {}

    /**
     * The single entry point for "this order became COMPLETED".
     *
     * Every completion path goes through here — the status service (payments),
     * an admin status update, and an admin order created already completed — so
     * a new completion path cannot forget an effect. Both steps are individually
     * idempotent: the usage counters are guarded by the order's
     * discount_usage_incremented_at flag, and every reward grant carries a
     * deterministic idempotency key.
     */
    public function handle(Order $order): void
    {
        $this->incrementDiscountUsageCounts->handle($order);
        $this->grantOrderRewards->handle($order);
    }
}
