<?php

declare(strict_types=1);

namespace App\Actions\Admin\Discounts;

use App\Models\DiscountPromotionUsage;
use App\Models\Order;

/**
 * Releases the usage slots held by an order.
 *
 * Called when an order transitions to CANCELLED or FAILED — those orders no
 * longer consume the customer's per-customer allowance. Completed and
 * refunded orders keep their slots; refunds never restore usage.
 */
final readonly class ReleasePromotionUsageSlotsAction
{
    public function handle(Order $order): void
    {
        DiscountPromotionUsage::query()
            ->where('order_id', $order->id)
            ->delete();
    }
}
