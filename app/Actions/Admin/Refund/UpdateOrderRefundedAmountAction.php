<?php

declare(strict_types=1);

namespace App\Actions\Admin\Refund;

use App\Enums\Order\RefundStatusEnum;
use App\Models\Order;
use App\Models\Refund;

final class UpdateOrderRefundedAmountAction
{
    public function handle(Order $order): void
    {
        $total = Refund::query()
            ->whereHas('orderItem', fn ($q) => $q->where('order_id', $order->id))
            ->where('status', RefundStatusEnum::COMPLETED)
            ->sum('amount');

        $order->update(['total_refunded' => (int) $total]);
    }
}
